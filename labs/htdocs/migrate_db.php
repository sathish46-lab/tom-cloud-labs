<?php
require_once 'src/load.php';

$mappings = [
    'learn-roadmap' => 'roadmap',
    'learn-lesson' => 'lesson',
    'learn-syllabus' => 'syllabus',
    '9114b868-78c8-4de4-b773-609f5d844268' => 'cybersecurity',
    'prog-c' => 'c-programming',
    'prog-python' => 'python-programming',
    'prog-php' => 'php-programming',
    'prog-mongodb' => 'mongodb',
    'prog-socket' => 'socket-programming',
    'tech-linux-cli' => 'networking',
    'tech-git' => 'programming',
    'prog-generic' => 'programming',
    'prog-html-css' => 'web-development',
    'tech-ai' => 'artificial-intelligence',
    'tech-networks' => 'networking',
    'tech-ml' => 'artificial-intelligence'
];

$db = DatabaseConnection::getDefaultDatabase();

foreach ($mappings as $old => $new) {
    echo "Migrating $old to $new...\n";
    $db->quizzes->updateMany(
        ['category_id' => $old],
        ['$set' => ['category_id' => $new]]
    );
    
    // Also update attempts if necessary (though usually they join by hash)
    // But some logic might use category_id if it's cached in attempts.
    // Actually, attempts only store quiz_hash.
}

echo "Database migration complete.\n";
