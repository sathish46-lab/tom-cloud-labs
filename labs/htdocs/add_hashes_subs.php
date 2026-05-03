<?php
function generate_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

$subs = json_decode(file_get_contents('src/data/quiz_subtopics.json'), true);
foreach ($subs as &$sub) {
    if (!isset($sub['hash'])) {
        $sub['hash'] = generate_uuid();
    }
}
file_put_contents('src/data/quiz_subtopics.json', json_encode($subs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Hashes added to subtopics.\n";
