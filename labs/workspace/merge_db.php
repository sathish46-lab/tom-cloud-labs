<?php
require_once '/var/www/labs/htdocs/src/load.php';

$db = DatabaseConnection::getDefaultDatabase();

// Find all distinct lesson_ids that are not empty
$lessonIds = $db->ai_chat_history->distinct('lesson_id');

foreach ($lessonIds as $lessonId) {
    if (empty($lessonId)) continue;
    
    // Find all documents for this lesson_id, sorted by _id to keep chronological order of creation
    $docs = $db->ai_chat_history->find(
        ['lesson_id' => $lessonId],
        ['sort' => ['_id' => 1]]
    )->toArray();
    
    if (count($docs) > 1) {
        echo "Found " . count($docs) . " duplicate documents for lesson_id " . $lessonId . "\n";
        
        $mergedMessages = [];
        $firstDoc = $docs[0];
        
        foreach ($docs as $doc) {
            if (isset($doc['messages']) && is_array($doc['messages'])) {
                foreach ($doc['messages'] as $msg) {
                    $mergedMessages[] = $msg;
                }
            }
        }
        
        // Ensure chronological order by timestamp
        usort($mergedMessages, function($a, $b) {
            $ta = (int)($a['timestamp'] ?? 0);
            $tb = (int)($b['timestamp'] ?? 0);
            return $ta - $tb;
        });
        
        // Update the first document with merged messages
        $db->ai_chat_history->updateOne(
            ['_id' => $firstDoc['_id']],
            ['$set' => ['messages' => $mergedMessages]]
        );
        
        // Delete the duplicates
        for ($i = 1; $i < count($docs); $i++) {
            $db->ai_chat_history->deleteOne(['_id' => $docs[$i]['_id']]);
        }
        
        echo "Merged into " . $firstDoc['_id'] . "\n";
    }
}
echo "Done merging.\n";
