<?php
$subtopics = json_decode(file_get_contents('src/data/quiz_subtopics.json'), true);
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
    'tech-linux-cli' => 'networking', // Fallback or mapping
    'tech-git' => 'programming',
    'tech-iot' => 'hardware', // If I added hardware? No.
    'prog-generic' => 'programming',
    'prog-html-css' => 'web-development',
    'prog-micropython' => 'programming',
    'tech-ai' => 'artificial-intelligence',
    'tech-os' => 'networking', // Or system?
    'tech-networks' => 'networking',
    'tech-ml' => 'artificial-intelligence'
];

foreach ($subtopics as &$sub) {
    if (isset($mappings[$sub['category_id']])) {
        $sub['category_id'] = $mappings[$sub['category_id']];
    }
}

file_put_contents('src/data/quiz_subtopics.json', json_encode($subtopics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Migration complete.\n";
