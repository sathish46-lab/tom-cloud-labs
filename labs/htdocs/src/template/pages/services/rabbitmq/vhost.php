<?php
$service_slug = 'rabbitmq';
$service_meta = require dirname(__DIR__) . '/services_meta.php';
$meta = $service_meta[$service_slug];
$service_meta = $meta;

Session::addMetaTag('<title>' . $meta['entities_browser_title'] . ' - ' . Session::siteName() . '</title>');

$user = Session::getUser();
$db = VhostConnection::selectVhost('tom_labs_db');

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
