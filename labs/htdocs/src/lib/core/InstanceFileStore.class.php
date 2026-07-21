<?php

/**
 * Instance File Store (Copy-on-Write template inheritance)
 *
 * Storage model:
 *   - Base layer:  MinIO at labassets/instances/base/<template>/<path>
 *                  Fallback: /opt/labs-control-panel/lab-templates/<template>/
 *   - User layer:  tom_labs_files_db.files where layer = "user"
 *                  Created copy-on-write when a user edits/creates a file.
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

    public static function versionsCollection() {
        return self::db()->selectCollection('files_versions');
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
            return true; // already seeded
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
     * List all base files for a template from MinIO, with filesystem fallback.
     * Returns flat array of relative paths.
     */
    public static function listBaseFiles($templateFolder) {
        // Try MinIO first
        $prefix = self::S3_BASE_PREFIX . $templateFolder . '/';
        $keys = Storage::listObjects($prefix);
        if ($keys !== false && count($keys) > 0) {
            return array_map(function ($key) use ($prefix) {
                return ltrim(substr($key, strlen($prefix)), '/');
            }, $keys);
        }

        // Fallback: list from filesystem
        $basePath = self::TEMPLATES_DIR . '/' . $templateFolder;
        if (!is_dir($basePath)) return [];

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
        return $files;
    }

    /**
     * Read base file content from MinIO (or filesystem fallback).
     * Returns ['content' => string, 'is_binary' => bool, 's3_key' => string|null] or null.
     */
    public static function readBaseFile($templateFolder, $path) {
        // Try MinIO first
        $s3Key = self::S3_BASE_PREFIX . $templateFolder . '/' . $path;
        $content = Storage::download($s3Key);
        if ($content !== false) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $isBinary = !in_array($ext, self::TEXT_EXT, true)
                && filesize($path) > 256 * 1024; // can't use filesize on S3, treat as binary if unknown ext
            return ['content' => $content, 'is_binary' => false, 's3_key' => $s3Key];
        }

        // Fallback: read from filesystem
        $filePath = self::TEMPLATES_DIR . '/' . $templateFolder . '/' . $path;
        if (file_exists($filePath)) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $isText = in_array($ext, self::TEXT_EXT, true)
                || filesize($filePath) <= 256 * 1024;
            if ($isText) {
                return ['content' => file_get_contents($filePath), 'is_binary' => false, 's3_key' => null];
            } else {
                $s3Key2 = self::S3_BASE_PREFIX . $templateFolder . '/' . $path;
                return ['content' => null, 'is_binary' => true, 's3_key' => $s3Key2];
            }
        }

        return null;
    }

    /**
     * Build a merged tree (base from MinIO + user overrides from Mongo) for an instance.
     */
    public static function getTree($instanceId, $templateFolder) {
        //1. Collect ALL paths (base from MinIO/filesystem + user-only from Mongo)
        $allPaths = [];

        $baseFiles = self::listBaseFiles($templateFolder);
        foreach ($baseFiles as $relPath) {
            if ($relPath === '' || $relPath === '/') continue;
            $allPaths[$relPath] = false;
        }

        $userDocs = self::collection()->find([
            'layer' => 'user',
            'instance_id' => $instanceId,
        ])->toArray();

        $userMap = [];
        foreach ($userDocs as $doc) {
            $doc = (array) $doc;
            $userMap[$doc['base_path']] = $doc;
            if (!$doc['is_dir']) {
                $allPaths[$doc['base_path']] = true;
            }
        }

        //2. Collect ALL folder paths from all files
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
        foreach ($userMap as $path => $doc) {
            if (!empty($doc['is_dir'])) {
                $allFolders[$path] = true;
            }
        }

        //3. Build node map and parent→children index
        $nodes = [];
        $childrenOf = []; // parent_path => [child_path, ...]

        foreach ($allFolders as $folderPath => $_) {
            $nodes[$folderPath] = [
                'path' => $folderPath,
                'name' => basename($folderPath),
                'is_dir' => true,
                'modified' => !empty($userMap[$folderPath]),
                'children' => [],
            ];
            $dir = dirname($folderPath);
            $dir = ($dir === '.' || $dir === '/') ? '' : $dir;
            $childrenOf[$dir][] = $folderPath;
        }

        foreach ($allPaths as $filePath => $isUser) {
            $doc = $userMap[$filePath] ?? null;
            $nodes[$filePath] = [
                'path' => $filePath,
                'name' => basename($filePath),
                'is_dir' => false,
                'size' => $isUser ? ($doc['size'] ?? 0) : 0,
                'mime' => $isUser ? ($doc['mime'] ?? null) : null,
                'modified' => (bool) $isUser,
                'is_binary' => $isUser ? !empty($doc['s3_key']) : false,
            ];
            $dir = dirname($filePath);
            $dir = ($dir === '.' || $dir === '/') ? '' : $dir;
            $childrenOf[$dir][] = $filePath;
        }

        //4. Recursively build tree from root
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
     * Get file content — user layer from Mongo, base layer from MinIO/filesystem.
     */
    public static function getFile($instanceId, $templateFolder, $path) {
        // Check user layer first
        $user = self::collection()->findOne([
            'layer' => 'user',
            'instance_id' => $instanceId,
            'base_path' => $path,
        ]);
        if ($user) {
            return self::rowToArray($user);
        }

        // Fall back to base layer (MinIO → filesystem)
        $base = self::readBaseFile($templateFolder, $path);
        if ($base) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            return [
                'id' => null,
                'layer' => 'base',
                'base_path' => $path,
                'name' => basename($path),
                'is_dir' => false,
                'size' => strlen($base['content'] ?? ''),
                'mime' => mime_content_type(self::TEMPLATES_DIR . '/' . $templateFolder . '/' . $path) ?: 'application/octet-stream',
                'content' => $base['content'],
                's3_key' => $base['s3_key'],
                'version' => 1,
                'modified' => false,
            ];
        }
        return null;
    }

    /**
     * Copy-on-write save. Creates/updates the user layer doc.
     */
    public static function saveFile($instanceId, $templateFolder, $path, $content, $username, $email) {
        $existing = self::collection()->findOne([
            'layer' => 'user',
            'instance_id' => $instanceId,
            'base_path' => $path,
        ]);

        if ($existing) {
            $existing = self::rowToArray($existing);
            self::versionsCollection()->insertOne([
                'instance_id' => $instanceId,
                'base_path' => $path,
                'name' => $existing['name'],
                'content' => $existing['content'],
                's3_key' => $existing['s3_key'],
                'version' => $existing['version'],
                'username' => $username,
                'email' => $email,
                'created_at' => new MongoDB\BSON\UTCDateTime(),
            ]);
            $newVersion = ($existing['version'] ?? 1) + 1;
            self::collection()->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($existing['id'])],
                ['$set' => [
                    'content' => $content,
                    's3_key' => null,
                    'size' => strlen($content),
                    'mime' => 'text/plain',
                    'version' => $newVersion,
                    'updated_at' => new MongoDB\BSON\UTCDateTime(),
                ]]
            );
            return $newVersion;
        }

        $name = basename($path);
        $doc = [
            'layer' => 'user',
            'instance_id' => $instanceId,
            'template' => $templateFolder,
            'base_path' => $path,
            'name' => $name,
            'is_dir' => false,
            'size' => strlen($content),
            'mime' => 'text/plain',
            'content' => $content,
            's3_key' => null,
            'username' => $username,
            'email' => $email,
            'version' => 1,
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'updated_at' => new MongoDB\BSON\UTCDateTime(),
        ];
        self::collection()->insertOne($doc);
        return 1;
    }

    /**
     * Create a new file or folder in the user layer.
     */
    public static function createNode($instanceId, $templateFolder, $path, $isDir, $username, $email, $content = '') {
        $path = ltrim($path, '/');
        $existing = self::collection()->findOne([
            'instance_id' => $instanceId,
            'base_path' => $path,
            'layer' => 'user',
        ]);
        if ($existing) {
            return ['status' => 'error', 'error' => 'Path already exists'];
        }
        $doc = [
            'layer' => 'user',
            'instance_id' => $instanceId,
            'template' => $templateFolder,
            'base_path' => $path,
            'name' => basename($path),
            'is_dir' => $isDir,
            'size' => $isDir ? 0 : strlen($content),
            'mime' => $isDir ? null : 'text/plain',
            'content' => $isDir ? null : $content,
            's3_key' => null,
            'username' => $username,
            'email' => $email,
            'version' => 1,
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'updated_at' => new MongoDB\BSON\UTCDateTime(),
        ];
        self::collection()->insertOne($doc);
        return ['status' => 'success'];
    }

    /**
     * Delete a user-layer node. Base nodes revert to base view.
     */
    public static function deleteNode($instanceId, $path) {
        if (substr($path, -1) === '/') $path = rtrim($path, '/');
        self::collection()->deleteMany([
            'layer' => 'user',
            'instance_id' => $instanceId,
            '$or' => [
                ['base_path' => $path],
                ['base_path' => ['$regex' => '^' . preg_quote($path, '/') . '(/|$)']],
            ],
        ]);
        return ['status' => 'success'];
    }

    /**
     * List versions for a file.
     */
    public static function getVersions($instanceId, $path) {
        $cursor = self::versionsCollection()->find(
            ['instance_id' => $instanceId, 'base_path' => $path],
            ['sort' => ['version' => -1]]
        );
        return array_map(function ($v) {
            $v = (array) $v;
            return [
                'version' => $v['version'],
                'username' => $v['username'],
                'email' => $v['email'],
                'created_at' => $v['created_at'] instanceof MongoDB\BSON\UTCDateTime
                    ? $v['created_at']->toDateTime()->format('Y-m-d H:i:s') : null,
            ];
        }, $cursor->toArray());
    }

    protected static function rowToArray($row) {
        $row = (array) $row;
        return [
            'id' => (string) ($row['_id'] ?? ''),
            'layer' => $row['layer'],
            'base_path' => $row['base_path'],
            'name' => $row['name'],
            'is_dir' => (bool) $row['is_dir'],
            'size' => $row['size'] ?? 0,
            'mime' => $row['mime'] ?? null,
            'content' => $row['content'] ?? null,
            's3_key' => $row['s3_key'] ?? null,
            'version' => $row['version'] ?? 1,
            'modified' => ($row['layer'] === 'user'),
        ];
    }
}
