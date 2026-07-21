<?php

/**
 * Instance File Store (Copy-on-Write template inheritance)
 *
 * Storage model:
 *   - Base layer:  MinIO at labassets/instances/base/<template>/<path>
 *                  Fallback: /opt/labs-control-panel/lab-templates/<template>/
 *   - User layer:  ONE document per instance in tom_labs_files_db.files
 *                  { instance_id, template, files: { "path": {content, size, ...} } }
 *                  Created/updated copy-on-write when a user edits/creates a file.
 *
 * Rendering merges base + user overrides. Saves always write to the user layer.
 */

class InstanceFileStore {

    const TEMPLATES_DIR = '/opt/labs-control-panel/lab-templates';
    const S3_BASE_PREFIX = 'labassets/instances/base/';

    const TEXT_EXT = [
        'txt', 'php', 'html', 'htm', 'css', 'js', 'json', 'md', 'sh', 'py', 'yml',
        'yaml', 'ini', 'conf', 'cfg', 'Dockerfile', 'env', 'log', 'xml', 'sql',
        'toml', 'csv', 'rst', 'gitignore', 'service', 'socket', 'mount', 'path', 'link'
    ];

    /** @var MongoDB\Database */
    protected static $filesDb = null;

    public static function db() {
        if (self::$filesDb === null) {
            self::$filesDb = DatabaseConnection::getClient()->selectDatabase('tom_labs_files_db');
        }
        return self::$filesDb;
    }

    public static function collection() {
        return self::db()->selectCollection('files');
    }

    /**
     * Get or create the single user-layer document for an instance.
     * Returns the MongoDB document array (with _id).
     */
    protected static function getOrCreateUserDoc($instanceId, $templateFolder, $username = '', $email = '') {
        $doc = self::collection()->findOne(['instance_id' => $instanceId]);
        if (!$doc) {
            $newDoc = [
                'instance_id' => $instanceId,
                'template' => $templateFolder,
                'username' => $username,
                'email' => $email,
                'files' => new \stdClass(),
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime(),
            ];
            self::collection()->insertOne($newDoc);
            $doc = self::collection()->findOne(['instance_id' => $instanceId]);
        }
        return (array) $doc;
    }

    /**
     * Map an instance's template name to a lab-templates folder.
     */
    public static function resolveTemplateFolder($instance) {
        if (!empty($instance['template']) && is_dir(self::TEMPLATES_DIR . '/' . $instance['template'])) {
            return $instance['template'];
        }
        $candidate = $instance['lab_type'] ?? ($instance['type'] ?? '');
        if (!empty($candidate) && is_dir(self::TEMPLATES_DIR . '/' . $candidate)) {
            return $candidate;
        }
        if (is_dir(self::TEMPLATES_DIR . '/essentials')) {
            return 'essentials';
        }
        return null;
    }

    /**
     * Ensure an instance's base layer exists in MinIO, then return the template folder.
     */
    public static function ensureBaseForInstance($instance) {
        $folder = self::resolveTemplateFolder($instance);
        if ($folder) {
            self::seedBaseToMinIO($folder);
        }
        return $folder;
    }

    /**
     * Seed base files from filesystem to MinIO (idempotent — skips if already uploaded).
     */
    public static function seedBaseToMinIO($templateFolder) {
        $prefix = self::S3_BASE_PREFIX . $templateFolder . '/';
        $existing = Storage::listObjects($prefix);
        if ($existing !== false && count($existing) > 0) {
            return true;
        }

        $basePath = self::TEMPLATES_DIR . '/' . $templateFolder;
        if (!is_dir($basePath)) return false;

        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($rii as $fileInfo) {
            if ($fileInfo->isDir()) continue;
            $relative = ltrim(substr($fileInfo->getPathname(), strlen($basePath) + 1), '/');
            if ($relative === '') continue;
            $s3Key = self::S3_BASE_PREFIX . $templateFolder . '/' . $relative;
            Storage::upload($fileInfo->getPathname(), $s3Key);
        }
        return true;
    }

    /**
     * List all base files for a template.
     */
    public static function listBaseFiles($templateFolder) {
        // Filesystem
        $basePath = self::TEMPLATES_DIR . '/' . $templateFolder;
        if (is_dir($basePath)) {
            $files = [];
            $rii = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($rii as $fileInfo) {
                if ($fileInfo->isDir()) continue;
                $relative = ltrim(substr($fileInfo->getPathname(), strlen($basePath) + 1), '/');
                if ($relative !== '') $files[] = $relative;
            }
            if (!empty($files)) return $files;
        }
        // MinIO fallback
        try {
            $prefix = self::S3_BASE_PREFIX . $templateFolder . '/';
            $keys = Storage::listObjects($prefix);
            if ($keys !== false && count($keys) > 0) {
                return array_map(function ($key) use ($prefix) {
                    return ltrim(substr($key, strlen($prefix)), '/');
                }, $keys);
            }
        } catch (Exception $e) {
            error_log('listBaseFiles MinIO error: ' . $e->getMessage());
        }
        return [];
    }

    /**
     * Read base file content. Filesystem first, MinIO fallback.
     */
    public static function readBaseFile($templateFolder, $path) {
        // Filesystem first
        $filePath = self::TEMPLATES_DIR . '/' . $templateFolder . '/' . $path;
        if (file_exists($filePath) && is_file($filePath)) {
            $content = file_get_contents($filePath);
            if ($content !== false) {
                return ['content' => $content, 'size' => strlen($content)];
            }
        }
        // MinIO fallback
        try {
            $s3Key = self::S3_BASE_PREFIX . $templateFolder . '/' . $path;
            $content = Storage::download($s3Key);
            if ($content !== false && strlen($content) > 0) {
                return ['content' => $content, 'size' => strlen($content)];
            }
        } catch (Exception $e) {
            error_log('readBaseFile MinIO error for ' . $path . ': ' . $e->getMessage());
        }
        return null;
    }

    /**
     * Build a merged tree (base from filesystem + user overrides from Mongo) for an instance.
     */
    public static function getTree($instanceId, $templateFolder) {
        $allPaths = [];

        $baseFiles = self::listBaseFiles($templateFolder);
        foreach ($baseFiles as $relPath) {
            if ($relPath === '' || $relPath === '/') continue;
            $allPaths[$relPath] = false;
        }

        // Single user doc with all overrides
        $userDoc = self::getOrCreateUserDoc($instanceId, $templateFolder);
        $userFiles = (array)($userDoc['files'] ?? []);
        $userMap = [];
        foreach ($userFiles as $filePath => $fileData) {
            $fileData = (array) $fileData;
            $userMap[$filePath] = $fileData;
            if (empty($fileData['is_dir'])) {
                $allPaths[$filePath] = true;
            }
        }

        // Collect all folder paths
        $allFolders = [];
        foreach (array_keys($allPaths) as $filePath) {
            $parts = explode('/', $filePath);
            array_pop($parts);
            $cumulative = '';
            foreach ($parts as $part) {
                $cumulative = $cumulative === '' ? $part : $cumulative . '/' . $part;
                $allFolders[$cumulative] = true;
            }
        }
        foreach ($userMap as $path => $data) {
            if (!empty($data['is_dir'])) {
                $allFolders[$path] = true;
            }
        }

        // Build node map and parent→children index
        $nodes = [];
        $childrenOf = [];

        foreach ($allFolders as $folderPath => $_) {
            $isUser = isset($userMap[$folderPath]);
            $nodes[$folderPath] = [
                'path' => $folderPath,
                'name' => basename($folderPath),
                'is_dir' => true,
                'modified' => $isUser,
                'children' => [],
            ];
            $dir = dirname($folderPath);
            $dir = ($dir === '.' || $dir === '/') ? '' : $dir;
            $childrenOf[$dir][] = $folderPath;
        }

        foreach ($allPaths as $filePath => $isUser) {
            $data = $userMap[$filePath] ?? null;
            $nodes[$filePath] = [
                'path' => $filePath,
                'name' => basename($filePath),
                'is_dir' => false,
                'size' => $isUser ? ($data['size'] ?? 0) : 0,
                'modified' => (bool) $isUser,
            ];
            $dir = dirname($filePath);
            $dir = ($dir === '.' || $dir === '/') ? '' : $dir;
            $childrenOf[$dir][] = $filePath;
        }

        // Recursively build tree from root
        $build = function ($parentPath) use (&$build, &$nodes, &$childrenOf) {
            $children = $childrenOf[$parentPath] ?? [];
            $list = [];
            foreach ($children as $childPath) {
                $node = $nodes[$childPath];
                if ($node['is_dir']) {
                    $node['children'] = $build($childPath);
                }
                $list[] = $node;
            }
            usort($list, function ($a, $b) {
                if ($a['is_dir'] !== $b['is_dir']) return $a['is_dir'] ? -1 : 1;
                return strcasecmp($a['name'], $b['name']);
            });
            return $list;
        };

        return $build('');
    }

    /**
     * Get file content — user layer from single doc, base layer from MinIO/filesystem.
     */
    public static function getFile($instanceId, $templateFolder, $path) {
        $userDoc = self::getOrCreateUserDoc($instanceId, $templateFolder);
        $userFiles = (array)($userDoc['files'] ?? []);

        if (isset($userFiles[$path])) {
            $data = (array) $userFiles[$path];
            return [
                'base_path' => $path,
                'name' => basename($path),
                'is_dir' => !empty($data['is_dir']),
                'size' => $data['size'] ?? 0,
                'content' => $data['content'] ?? null,
                's3_key' => $data['s3_key'] ?? null,
                'modified' => true,
            ];
        }

        // Fall back to base layer
        $base = self::readBaseFile($templateFolder, $path);
        if ($base) {
            return [
                'base_path' => $path,
                'name' => basename($path),
                'is_dir' => false,
                'size' => $base['size'] ?? strlen($base['content'] ?? ''),
                'content' => $base['content'],
                'modified' => false,
            ];
        }
        return null;
    }

    /**
     * Copy-on-write save. Updates the single user doc's files map.
     */
    public static function saveFile($instanceId, $templateFolder, $path, $content, $username, $email) {
        self::getOrCreateUserDoc($instanceId, $templateFolder, $username, $email);
        $now = new MongoDB\BSON\UTCDateTime();

        // Read current files, merge, then set entire files object
        $doc = self::collection()->findOne(['instance_id' => $instanceId]);
        $currentFiles = $doc ? (array)($doc['files'] ?? []) : [];
        $currentFiles[$path] = ['content' => $content, 'size' => strlen($content)];

        $result = self::collection()->updateOne(
            ['instance_id' => $instanceId],
            ['$set' => [
                'files' => $currentFiles,
                'updated_at' => $now,
            ]]
        );

        $modified = $result->getModifiedCount();
        $matched = $result->getMatchedCount();
        error_log("saveFile: instance_id=$instanceId path=$path matched=$matched modified=$modified content_len=" . strlen($content));

        return $modified;
    }

    /**
     * Create a new file or folder in the user layer.
     */
    public static function createNode($instanceId, $templateFolder, $path, $isDir, $username, $email, $content = '') {
        $path = ltrim($path, '/');
        self::getOrCreateUserDoc($instanceId, $templateFolder, $username, $email);

        $doc = self::collection()->findOne(['instance_id' => $instanceId]);
        $currentFiles = $doc ? (array)($doc['files'] ?? []) : [];

        if (isset($currentFiles[$path])) {
            return ['status' => 'error', 'error' => 'Path already exists'];
        }

        $now = new MongoDB\BSON\UTCDateTime();
        $currentFiles[$path] = $isDir
            ? ['is_dir' => true]
            : ['content' => $content, 'size' => strlen($content)];

        self::collection()->updateOne(
            ['instance_id' => $instanceId],
            ['$set' => [
                'files' => $currentFiles,
                'username' => $username,
                'email' => $email,
                'updated_at' => $now,
            ]]
        );

        return ['status' => 'success'];
    }

    /**
     * Delete a user-layer node (and its children if dir).
     */
    public static function deleteNode($instanceId, $path) {
        $path = rtrim($path, '/');
        $doc = self::collection()->findOne(['instance_id' => $instanceId]);
        if (!$doc) return ['status' => 'success'];

        $currentFiles = (array)($doc['files'] ?? []);
        $changed = false;
        foreach ($currentFiles as $filePath => $_) {
            if ($filePath === $path || strpos($filePath, $path . '/') === 0) {
                unset($currentFiles[$filePath]);
                $changed = true;
            }
        }

        if ($changed) {
            $now = new MongoDB\BSON\UTCDateTime();
            self::collection()->updateOne(
                ['instance_id' => $instanceId],
                ['$set' => ['files' => $currentFiles, 'updated_at' => $now]]
            );
        }

        return ['status' => 'success'];
    }
}
