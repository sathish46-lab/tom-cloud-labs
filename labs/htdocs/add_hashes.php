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

$cats = json_decode(file_get_contents('src/data/quiz_categories.json'), true);
foreach ($cats as &$cat) {
    if (!isset($cat['hash'])) {
        $cat['hash'] = generate_uuid();
    }
}
file_put_contents('src/data/quiz_categories.json', json_encode($cats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Hashes added to categories.\n";
