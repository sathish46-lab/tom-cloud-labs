<?php
require_once __DIR__ . '/../../../src/load.php';

use MongoDB\BSON\ObjectId;

$user = AuthMiddleware::requireAuth();

$hash = $_POST['hash'] ?? '';
$versionId = $_POST['version_id'] ?? '';

if (empty($hash) || empty($versionId)) {
    api_json(['status' => 'error', 'error' => 'Missing hash or version_id'], 400);
}

try {
    $instDb = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');

    $version = $instDb->instance_versions->findOne([
        '_id' => new ObjectId($versionId),
        'instance_hash' => $hash
    ]);

    if (!$version) {
        api_json(['status' => 'error', 'error' => 'Version not found'], 400);
    }

    $filesDb = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');
    $filesCol = $filesDb->instance_files;

    $snapshot = $version['files_snapshot'];
    if ($snapshot instanceof MongoDB\Model\BSONDocument) {
        $snapshot = $snapshot->getArrayCopy();
    } elseif (is_object($snapshot)) {
        $snapshot = (array)$snapshot;
    }

    $filesCol->updateOne(
        ['instance_id' => $hash],
        ['$set' => ['files' => $snapshot, 'updated_at' => new MongoDB\BSON\UTCDateTime()]],
        ['upsert' => true]
    );

    $configSnap = $version['config_snapshot'];
    if ($configSnap instanceof MongoDB\Model\BSONDocument) {
        $configSnap = $configSnap->getArrayCopy();
    } elseif (is_object($configSnap)) {
        $configSnap = (array)$configSnap;
    }

    $instDb->instances->updateOne(
        ['instance_hash' => $hash],
        ['$set' => array_merge($configSnap, ['updated_at' => time()])]
    );

    api_json(['status' => 'success', 'message' => 'Version restored'], 400);
} catch (Exception $e) {
    api_json(['status' => 'error', 'error' => errors_from_exception($e, 'instances/restore_version')['error']], 500);
}
