<?php
require_once __DIR__ . '/../../src/load.php';

if (Session::getAuthStatus() !== Constants::STATUS_LOGGEDIN) {
    header("Location: /signin");
    exit;
}

$lesson_id = $_GET['id'] ?? null;
if (!$lesson_id) {
    header("Location: /learn");
    exit;
}

Session::$pageTitle = "Lesson Details | Learn AI";
Session::set('is_learn_ai', true);
// Session::set('footer', true);
Session::addCustomJs('/js/learnAI/layout.js');

// Populate labs_list so the Required Lab card has real data
if (!Session::get('labs_list')) {
    $user = Session::getUser();
    $db = DatabaseConnection::getDefaultDatabase();
    $labTemplates = [
        ['id' => 'essentials', 'name' => 'Essentials', 'icon' => 'tux', 'badges' => ['free', 'beta']],
        ['id' => 'minio', 'name' => 'MinIO S3 Storage', 'icon' => 'docker', 'badges' => ['S3', 'beta']],
        ['id' => 'n8n', 'name' => 'n8n Workflow Lab', 'icon' => 'git-repo-forked', 'badges' => ['workflow', 'beta']],
        ['id' => 'docker_lab', 'name' => 'Tom Docker Lab', 'icon' => 'docker', 'badges' => ['docker', 'beta']]
    ];
    $labsList = [];
    foreach ($labTemplates as $tmpl) {
        $hash = $user->getLabHash($tmpl['id']);
        $data = $db->deployed_labs->findOne(['instance_hash' => $hash]);
        $labsList[] = [
            'id'        => $tmpl['id'],
            'name'      => $tmpl['name'],
            'icon'      => $tmpl['icon'],
            'badges'    => $tmpl['badges'],
            'hash'      => $hash,
            'status'    => ($data && $data['status'] === 'running') ? 'running' : 'offline',
            'ip'        => ($data && $data['status'] === 'running' && isset($data['internal_ip'])) ? $data['internal_ip'] : 'Instance Down',
            'is_public' => ($data && isset($data['is_public'])) ? 'public' : 'private'
        ];
    }
    Session::set('labs_list', $labsList);
}

Session::loadMaster();
