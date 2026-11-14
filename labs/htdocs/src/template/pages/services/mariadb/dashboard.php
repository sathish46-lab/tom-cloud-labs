<?php
$service_slug = 'mariadb';
$service_meta = require dirname(__DIR__) . '/services_meta.php';
$meta = $service_meta[$service_slug];
$service_meta = $meta;

Session::addMetaTag('<title>' . $meta['browser_title'] . ' - ' . Session::siteName() . '</title>');
Session::addCustomJs('/assets/js/copy.js');

$user = Session::getUser();
$db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');

$service_users = $db->{$meta['users']['collection']}->find(['user_id' => $user->getUserId()])->toArray();
if (!empty($meta['users']['count_key'])) {
    foreach ($service_users as &$svcUser) {
        $svcUser[$meta['users']['count_key']] = $db->{$meta['create']['collection']}->countDocuments([$meta['create']['user_id_key'] => (string)$svcUser['_id']]);
    }
    unset($svcUser);
}

$current_tab = 'dashboard';
require_once __DIR__ . '/../partials/service_header.php';
require_once __DIR__ . '/../partials/service_dashboard.php';
