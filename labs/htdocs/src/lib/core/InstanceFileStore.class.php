<?php

/**
 * Instance File Store (Copy-on-Write template inheritance)
 *
 * Storage model (Mongo + MinIO):
 *   - Base layer:  tom_labs_files_db.files where layer = "base"
 *                  seeded once from opt/labs-control-panel/lab-templates/<template>/
 *                  text files -> content field; binaries -> uploaded to MinIO (s3_key)
 *   - User layer:  tom_labs_files_db.files where layer = "user"
 *                  created copy-on-write when a user edits/creates a file
 *                  keyed by instance_id + username + email
 *
 * Rendering merges base + user overrides. Saves always write to the user layer.
 */

class InstanceFileStore {

    // Local disk location of the shared lab templates
    const TEMPLATES_DIR = '/opt/labs-control-panel/lab-templates';

    // Text extensions we store inline in Mongo (everything else goes to MinIO)
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
     * Map an instance's stored template name to a lab-templates folder.
     */
    public static function resolveTemplateFolder($instance) {
        // Explicit template folder stored on the instance takes precedence
        if (!empty($instance['template']) && is_dir(self::TEMPLATES_DIR . '/' . $instance['template'])) {
            return $instance['template'];
        }
        // Fallback: type/lab_type might already be a folder name
        $candidate = $instance['lab_type'] ?? ($instance['type'] ?? '');
        if (!empty($candidate) && is_dir(self::TEMPLATES_DIR . '/' . $candidate)) {
            return $candidate;
        }
        // Default base template for instances created before the file-manager existed
        if (is_dir(self::TEMPLATES_DIR . '/essentials')) {
            return 'essentials';
        }
        return null;
    }

    /**
     * Seed the BASE layer for a template folder (idempotent).
     * Skips if base docs for this template already exist.
     */
    public static function seedBaseLayer($templateFolder) {
        $basePath = self::TEMPLATES_DIR . '/' . $templateFolder;
        if (!is_dir($basePath)) {
            return false;
        }

        $already = self::collection()->countDocuments([
            'layer' => 'base',
            'template' => $templateFolder,
        ]);
        if ($already > 0) {
            return true;
        }

        $basePath = rtrim($basePath, '/');
        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $count = 0;
        foreach ($rii as $fileInfo) {
            $relative = ltrim(substr($fileInfo->getPathname(), strlen($basePath) + 1), '/');
            if ($relative === '') {
                continue;
            }
            $isDir = $fileInfo->isDir();
            $ext = strtolower($fileInfo->getExtension());
            $isText = in_array($ext, self::TEXT_EXT, true)
                || in_array($relative, self::TEXT_EXT, true)
                // extensionless (e.g. "entrypoints", "Dockerfile") or small unknown files -> treat as text
                || (($ext === '' || !in_array($ext, self::TEXT_EXT, true))
                    && $fileInfo->getSize() <= 256 * 1024);

            $doc = [
                'layer'        => 'base',
                'template'     => $templateFolder,
                'base_path'    => $relative,
                'name'         => $fileInfo->getFilename(),
                'is_dir'       => $isDir,
                'size'         => $isDir ? 0 : $fileInfo->getSize(),
                'mime'         => $isDir ? null : (mime_content_type($fileInfo->getPathname()) ?: 'application/octet-stream'),
                'content'      => null,
                's3_key'       => null,
                'username'     => null,
                'email'        => null,
                'version'      => 1,
                'created_at'   => new MongoDB\BSON\UTCDateTime(),
                'updated_at'   => new MongoDB\BSON\UTCDateTime(),
            ];

            if (!$isDir) {
                if ($isText && $fileInfo->getSize() <= 1024 * 1024) {
                    $doc['content'] = @file_get_contents($fileInfo->getPathname());
                } else {
                    // Binary or too large -> push to MinIO
                    $s3Key = 'labassets/instances/base/' . $templateFolder . '/' . $relative;
                    $doc['s3_key'] = $s3Key;
                    Storage::upload($fileInfo->getPathname(), $s3Key);
                }
            }

            self::collection()->insertOne($doc);
            $count++;
        }

        return $count;
    }

    /**
     * Ensure an instance's base layer exists, then return the template folder used.
     */
    public static function ensureBaseForInstance($instance) {
        $folder = self::resolveTemplateFolder($instance);
        if ($folder) {
            self::seedBaseLayer($folder);
        }
        return $folder;
    }

    /**
     * Build a merged tree (base + user overrides) for an instance.
     * Returns nested array of root-level nodes (folders + files at the root).
     */
    public static function getTree($instanceId, $templateFolder) {
        $pipeline = [
            ['$match' => [
                '$or' => [
                    ['layer' => 'base', 'template' => $templateFolder],
                    ['layer' => 'user', 'instance_id' => $instanceId],
                ]
            ]],
            // Prefer user layer over base when base_path collides
            ['$sort' => ['layer' => -1, 'base_path' => 1]],
            ['$group' => [
                '_id' => '$base_path',
                'doc' => ['$first' => '$$ROOT'],
            ]],
            ['$replaceRoot' => ['newRoot' => '$doc']],
        ];

        $rows = self::collection()->aggregate($pipeline)->toArray();

        // Build a flat map of folder nodes, ensuring all ancestor folders exist.
        $folders = [];   // path => node
        $ensureFolder = function ($path) use (&$folders, &$ensureFolder) {
            if ($path === '' || isset($folders[$path])) {
                return;
            }
            // recursively ensure parent first
            $parent = dirname($path);
            $parent = ($parent === '.' || $parent === '/') ? '' : $parent;
            if ($parent !== '' && !isset($folders[$parent])) {
                $ensureFolder($parent);
            }
            $folders[$path] = [
                'path' => $path,
                'name' => basename($path),
                'is_dir' => true,
                'modified' => false,
                'children' => [],
            ];
        };

        foreach ($rows as $row) {
            $row = (array) $row;
            if ($row['is_dir']) {
                $ensureFolder($row['base_path']);
                if ($row['layer'] === 'user') {
                    $folders[$row['base_path']]['modified'] = true;
                }
            }
        }

        // Attach files (and any folder that only existed because of a nested file)
        // to their parent folder node (or root if no parent).
        $root = [];
        $attach = function ($node) use (&$folders, &$root, &$ensureFolder) {
            $dir = dirname($node['path']);
            $dir = ($dir === '.' || $dir === '/') ? '' : $dir;
            if ($dir === '') {
                $root[] = $node;
            } else {
                $ensureFolder($dir);
                $exists = false;
                foreach ($folders[$dir]['children'] as $c) {
                    if ($c['path'] === $node['path']) { $exists = true; break; }
                }
                if (!$exists) {
                    $folders[$dir]['children'][] = $node;
                }
            }
        };

        foreach ($rows as $row) {
            $row = (array) $row;
            if ($row['is_dir']) {
                continue; // folder nodes already added via $folders
            }
            $fileNode = [
                'path' => $row['base_path'],
                'name' => $row['name'],
                'is_dir' => false,
                'size' => $row['size'],
                'mime' => $row['mime'],
                'modified' => ($row['layer'] === 'user'),
                'is_binary' => !empty($row['s3_key']),
            ];
            $attach($fileNode);
        }

        // Also attach declared folder nodes to their parents
        foreach ($folders as $path => $node) {
            if ($path === '') {
                continue;
            }
            $attach($node);
        }

        // Recursively sort: folders first, then files, alpha
        $sortFn = function (&$n) use (&$sortFn) {
            usort($n['children'], function ($a, $b) {
                if ($a['is_dir'] !== $b['is_dir']) {
                    return $a['is_dir'] ? -1 : 1;
                }
                return strcasecmp($a['name'], $b['name']);
            });
            foreach ($n['children'] as &$c) {
                if ($c['is_dir']) {
                    $sortFn($c);
                }
            }
        };
        foreach ($root as &$n) {
            if ($n['is_dir']) {
                $sortFn($n);
            }
        }
        usort($root, function ($a, $b) {
            if ($a['is_dir'] !== $b['is_dir']) {
                return $a['is_dir'] ? -1 : 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        return $root;
    }

    /**
     * Get file content (text) or s3_key (binary) for an instance,
     * preferring the user layer override.
     */
    public static function getFile($instanceId, $templateFolder, $path) {
        $user = self::collection()->findOne([
            'layer' => 'user',
            'instance_id' => $instanceId,
            'base_path' => $path,
        ]);
        if ($user) {
            return self::rowToArray($user);
        }
        $base = self::collection()->findOne([
            'layer' => 'base',
            'template' => $templateFolder,
            'base_path' => $path,
        ]);
        if ($base) {
            return self::rowToArray($base);
        }
        return null;
    }

    /**
     * Copy-on-write save. Creates/updates the user layer doc.
     * Snapshots the previous user version into files_versions.
     */
    public static function saveFile($instanceId, $templateFolder, $path, $content, $username, $email) {
        $existing = self::collection()->findOne([
            'layer' => 'user',
            'instance_id' => $instanceId,
            'base_path' => $path,
        ]);

        if ($existing) {
            $existing = self::rowToArray($existing);
            // snapshot previous version
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
                ['_id' => $existing['_id']],
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

        // No user doc yet -> create (CoW). Inherit metadata from base if present.
        $base = self::collection()->findOne([
            'layer' => 'base',
            'template' => $templateFolder,
            'base_path' => $path,
        ]);
        $name = basename($path);
        $isDir = $base ? (bool) $base['is_dir'] : false;
        $mime = $base ? $base['mime'] : 'text/plain';

        $doc = [
            'layer' => 'user',
            'instance_id' => $instanceId,
            'template' => $templateFolder,
            'base_path' => $path,
            'name' => $name,
            'is_dir' => $isDir,
            'size' => strlen($content),
            'mime' => $mime,
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
            '$or' => [
                ['layer' => 'user'],
                ['layer' => 'base', 'template' => $templateFolder],
            ],
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
     * Delete a user-layer node (file or folder). Base nodes cannot be deleted
     * (they revert to base view).
     */
    public static function deleteNode($instanceId, $path) {
        $path = ltrim($path, '/');
        // If folder, delete all user docs under this path prefix
        if (substr($path, -1) === '/') {
            $path = rtrim($path, '/');
        }
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
