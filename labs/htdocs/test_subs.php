<?php
require_once 'src/load.php';
$subs = \TomLabs\Labs\Quiz::getSubtopicsForCategory('c-programming');
echo "Found " . count($subs) . " subtopics for c-programming.\n";
foreach ($subs as $s) {
    echo "- " . $s['title'] . " (" . $s['id'] . ")\n";
}
