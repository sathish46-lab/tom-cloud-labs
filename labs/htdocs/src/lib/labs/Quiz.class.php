<?php
namespace TomLabs\Labs;

use DatabaseConnection;
use MongoDB\BSON\ObjectId;
use Exception;

/**
 * Quiz Model: Handles data persistence and AI generation triggers.
 */
class Quiz {
    
    private static $cachedCats = null;
    private static $cachedSubs = null;

    /**
     * Internal helper to load categories from JSON source
     */
    private static function loadCategories() {
        if (self::$cachedCats === null) {
            $jsonPath = __DIR__ . '/../../data/quiz_categories.json';
            if (file_exists($jsonPath)) {
                $content = file_get_contents($jsonPath);
                self::$cachedCats = json_decode($content, true);
            } else {
                self::$cachedCats = [];
            }
        }
        return self::$cachedCats;
    }

    /**
     * Internal helper to load subtopics from JSON source
     */
    private static function loadSubtopics() {
        if (self::$cachedSubs === null) {
            $jsonPath = __DIR__ . '/../../data/quiz_subtopics.json';
            if (file_exists($jsonPath)) {
                $content = file_get_contents($jsonPath);
                self::$cachedSubs = json_decode($content, true);
            } else {
                self::$cachedSubs = [];
            }
        }
        return self::$cachedSubs;
    }

    /**
     * Get all categories organized by section from JSON source
     */
    public static function getAllCategories() {
        $cats = self::loadCategories();
        $sections = [];
        
        foreach ($cats as &$cat) {
            $cat['_id'] = $cat['id']; // Map for backward compatibility
            $section = isset($cat['section']) ? $cat['section'] : 'General';
            $sections[$section][] = $cat;
        }
        
        // Sort sections alphabetically
        ksort($sections);
        return $sections;
    }

    /**
     * Find a category by ID from JSON source
     */
    public static function getCategory($id) {
        $cats = self::loadCategories();
        foreach ($cats as &$cat) {
            if ($cat['id'] === $id) {
                $cat['_id'] = $cat['id']; // Map for backward compatibility
                return $cat;
            }
        }
        return null;
    }

    /**
     * Find a subtopic by ID from JSON source
     */
    public static function getSubtopic($id) {
        $subs = self::loadSubtopics();
        foreach ($subs as &$sub) {
            if ($sub['id'] === $id) {
                $sub['_id'] = $sub['id']; // Map for backward compatibility
                return $sub;
            }
        }
        return null;
    }

    /**
     * Get all subtopics for a specific category from JSON source
     */
    public static function getSubtopicsForCategory($categoryId) {
        $subs = self::loadSubtopics();
        $filtered = [];
        foreach ($subs as &$sub) {
            if ($sub['category_id'] === $categoryId) {
                $sub['_id'] = $sub['id']; // Map for backward compatibility
                $filtered[] = $sub;
            }
        }
        return $filtered;
    }

    /**
     * Fetch a specific quiz by its industry-standard URL hash (Database required)
     */
    public static function getByHash($hash) {
        return DatabaseConnection::getDefaultDatabase()->quizzes->findOne(['hash' => $hash]);
    }

    /**
     * Get recent quizzes for a subtopic with pagination support
     */
    public static function getRecentForSubtopic($subtopicId, $limit = 8, $offset = 0) {
        return DatabaseConnection::getDefaultDatabase()->quizzes->find(
            ['subtopic_id' => $subtopicId],
            [
                'sort' => ['created_at' => -1],
                'limit' => $limit,
                'skip' => $offset
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
     * Record a user's quiz attempt results in the database
     */
    public static function recordAttempt($userEmail, $quizHash, $score, $total, $status = 'completed') {
        if (!$userEmail) return false;
        $db = DatabaseConnection::getDefaultDatabase();
        return $db->quiz_attempts->insertOne([
            'user_email' => $userEmail,
            'quiz_hash' => $quizHash,
            'score' => (int)$score,
            'total' => (int)$total,
            'status' => $status,
            'attempted_at' => time()
        ]);
    }

    /**
     * Check if a user has already attempted a specific quiz
     */
    public static function hasAttempted($userEmail, $quizHash) {
        if (!$userEmail) return false;
        $db = DatabaseConnection::getDefaultDatabase();
        $attempt = $db->quiz_attempts->findOne([
            'user_email' => $userEmail,
            'quiz_hash' => $quizHash
        ]);
        return $attempt !== null;
    }

    /**
     * Record a unique view for a quiz
     */
    public static function recordView($userEmail, $quizHash) {
        if (!$userEmail || !$quizHash) return false;
        $db = DatabaseConnection::getDefaultDatabase();
        
        // Only increment if user hasn't viewed before
        return $db->quizzes->updateOne(
            ['hash' => $quizHash, 'viewers' => ['$ne' => $userEmail]],
            ['$addToSet' => ['viewers' => $userEmail], '$inc' => ['view_count' => 1]]
        );
    }

    /**
     * Get user statistics (Zeal and Jolt)
     */
    public static function getUserStats($userEmail) {
        if (!$userEmail) return ['zeal' => 0, 'jolt' => 0];
        $db = DatabaseConnection::getDefaultDatabase();
        $stats = $db->user_stats->findOne(['user_email' => $userEmail]);
        if (!$stats) {
            $stats = ['user_email' => $userEmail, 'zeal' => 0, 'jolt' => 10]; // 10 starter jolt
            $db->user_stats->insertOne($stats);
        }
        return $stats;
    }

    /**
     * Update user statistics by adding Zeal and Jolt
     */
    public static function updateUserStats($userEmail, $zeal, $jolt) {
        if (!$userEmail) return false;
        $db = DatabaseConnection::getDefaultDatabase();
        return $db->user_stats->updateOne(
            ['user_email' => $userEmail],
            ['$inc' => ['zeal' => (int)$zeal, 'jolt' => (int)$jolt]]
        );
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
