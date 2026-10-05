<?php
/** One-time migration: user_stats.user_email -> user_stats.email (all DBs). */
require_once __DIR__ . '/tests/bootstrap.php';

$client = DatabaseConnection::getClient();
foreach (iterator_to_array($client->listDatabaseNames()) as $name) {
    if (in_array($name, ['admin', 'local', 'config'], true)) {
        continue;
    }
    $db = $client->selectDatabase($name);
    try {
        $pending = $db->user_stats->countDocuments(['user_email' => ['$exists' => true]]);
        if ($pending === 0) {
            $withEmail = $db->user_stats->countDocuments(['email' => ['$exists' => true]]);
            printf("%-22s nothing to rename (email docs=%d)\n", $name, $withEmail);
            continue;
        }
        $res = $db->user_stats->updateMany(
            ['user_email' => ['$exists' => true]],
            ['$rename' => ['user_email' => 'email']]
        );
        printf("%-22s renamed=%-5d left_old=%d with_email=%d\n",
            $name,
            $res->getModifiedCount(),
            $db->user_stats->countDocuments(['user_email' => ['$exists' => true]]),
            $db->user_stats->countDocuments(['email' => ['$exists' => true]]));
    } catch (Throwable $e) {
        printf("%-22s skip: %s\n", $name, $e->getMessage());
    }
}
