<?php
$service_slug = 'mysql';
$service_meta = require dirname(__DIR__) . '/services_meta.php';
$meta = $service_meta[$service_slug];
$service_meta = $meta;

Session::addMetaTag('<title>' . $meta['entities_browser_title'] . ' - ' . Session::siteName() . '</title>');

$user = Session::getUser();
$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');

require_once $_SERVER['DOCUMENT_ROOT'] . '/src/lib/services/' . $service_slug . '/Permission.class.php';

$service_collations = null;
if (!empty($meta['create']['extra']['permission_class'])) {
    $service_collations = ['utf8mb4' => [$meta['create']['extra']['default']]];
    try {
        $collationResp = (new $meta['create']['extra']['permission_class']($user))->getCollations();
        if ($collationResp->isSuccess() && is_array($collationResp->data) && $collationResp->data) {
            $service_collations = $collationResp->data;
        }
    } catch (\Throwable $e) {
        error_log('getCollations(' . $service_slug . '): ' . $e->getMessage());
    }
}
$service_users = $db->{$meta['users']['collection']}->find(['user_id' => $user->getUserId()])->toArray();

$selectedUsername = $_GET['user'] ?? '';
$selectedUserObj = null;

if (!empty($selectedUsername)) {
    foreach ($service_users as $mu) {
        if ($mu[$meta['users']['user_key']] === $selectedUsername) {
            $selectedUserObj = $mu;
            break;
        }
    }
} else if (!empty($service_users)) {
    $selectedUserObj = $service_users[0];
    $selectedUsername = $selectedUserObj[$meta['users']['user_key']];
}

$service_entities = [];
if ($selectedUserObj) {
    $service_entities = $db->{$meta['create']['collection']}->find([$meta['create']['user_id_key'] => (string)$selectedUserObj['_id']])->toArray();
}

$current_tab = $meta['create']['entity_tab'];
require_once __DIR__ . '/../partials/service_header.php';
require_once __DIR__ . '/../partials/service_entities.php';
