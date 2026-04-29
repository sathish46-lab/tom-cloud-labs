<?php
namespace TomLabs\Labs;

use DatabaseConnection;
use MongoDB\BSON\ObjectId;
use Exception;

/**
 * Quiz Model: Handles data persistence and AI generation triggers.
 */
class Quiz {
    
    /**
     * Get all active categories organized by section
     */
    public static function getAllCategories() {
        $cursor = DatabaseConnection::getDefaultDatabase()->quiz_categories->find([], [
            'sort' => ['section' => 1, 'title' => 1]
        ]);
        
        $sections = [];
        foreach ($cursor as $cat) {
            $sections[$cat['section']][] = $cat;
        }
        return $sections;
    }

    public static function getCategory($id) {
        return DatabaseConnection::getDefaultDatabase()->quiz_categories->findOne(['_id' => $id]);
    }

    public static function getSubtopic($id) {
        return DatabaseConnection::getDefaultDatabase()->quiz_subtopics->findOne(['_id' => $id]);
    }

    public static function getSubtopicsForCategory($categoryId) {
        return DatabaseConnection::getDefaultDatabase()->quiz_subtopics->find(['category_id' => $categoryId])->toArray();
    }

    /**
     * Fetch a specific quiz by its industry-standard URL hash
     */
    public static function getByHash($hash) {
        return DatabaseConnection::getDefaultDatabase()->quizzes->findOne(['hash' => $hash]);
    }

    /**
     * Get recent quizzes for a subtopic
     */
    public static function getRecentForSubtopic($subtopicId, $limit = 8) {
        return DatabaseConnection::getDefaultDatabase()->quizzes->find(
            ['subtopic_id' => $subtopicId],
            [
                'sort' => ['created_at' => -1],
                'limit' => $limit
            ]
        )->toArray();
    }

    /**
     * Trigger the AI generation process via labsctl orchestrator and capture PID
     */
    public static function startGeneration($topicId, $subtopicId, $difficulty = 'normal') {
        $db = DatabaseConnection::getDefaultDatabase();
        $jobId = (string) new ObjectId();

        // 1. Initialize professional job state
        $jobData = [
            '_id' => $jobId,
            'topic_id' => $topicId,
            'subtopic_id' => $subtopicId,
            'difficulty' => $difficulty,
            'status' => 'processing',
            'percentage' => 5,
            'status_text' => 'Initializing AI Generation Hub...',
            'available' => false,
            'generation_started' => null,
            'generation_attempt' => 1,
            'generation_success' => false,
            'generation_failed' => false,
            'generation_started_at' => time(),
            'generation_success_at' => false,
            'generation_failed_at' => false,
            'created_at' => time(),
            'updated_at' => time()
        ];

        $db->quiz_jobs->insertOne($jobData);

        // 2. Trigger labsctl and capture PID
        $cmd = "sudo /usr/bin/python3 /opt/labs-control-panel/labsctl.py quiz generate " .
               "--topic=" . escapeshellarg($topicId) . " " .
               "--subtopic=" . escapeshellarg($subtopicId) . " " .
               "--diff=" . escapeshellarg($difficulty) . " " .
               "--job=" . escapeshellarg($jobId) . " > /dev/null 2>&1 & echo $!";
        
        $pid = trim(exec($cmd));

        // 3. Update job with PID
        if ($pid) {
            $db->quiz_jobs->updateOne(
                ['_id' => $jobId],
                ['$set' => ['generation_started' => $pid]]
            );
        }

        return [
            'status' => 'success',
            'message' => 'Generating Spot Quiz',
            'id' => $jobId,
            'pid' => $pid
        ];
    }

    /**
     * Get the live status of a generation job
     */
    public static function getJobStatus($jobId) {
        return DatabaseConnection::getDefaultDatabase()->quiz_jobs->findOne(['_id' => $jobId]);
    }

    /**
     * Delete old/stale jobs
     */
    public static function cleanupJobs($maxAge = 3600) {
        $threshold = time() - $maxAge;
        DatabaseConnection::getDefaultDatabase()->quiz_jobs->deleteMany([
            'created_at' => ['$lt' => $threshold]
        ]);
    }
}
