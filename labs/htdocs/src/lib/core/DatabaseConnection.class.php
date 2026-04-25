<?php

class DatabaseConnection {
    public static $client = null; 
    private static $db = null;

    /**
     * Centralized place for the connection string and client instantiation
     */
    public static function getClient() {
    if (self::$client === null) {
        $uri = get_config('database_file'); 
        
        try {
            self::$client = new MongoDB\Client($uri);
            
            // PROFESSIONAL CONNECTION TEST: 
            // Avoids deprecated Driver constants by using a raw command
            self::$client->selectDatabase('admin')->command(['ping' => 1]);
            
        } catch (Exception $e) {
            error_log("DB CONNECTION ERROR: " . $e->getMessage());
            http_response_code(500);
            die("Critical: Database connection failed. Please check logs.");
        }
    }
    return self::$client;
}

    /**
     * The primary method to get the database instance
     */
    public static function getDefaultDatabase() {
        if (self::$db === null) {
            // Pull the database name from the centralized config
            $dbName = get_config('main_db') ?? 'tom_labs_db';
            self::$db = self::getClient()->selectDatabase($dbName);
        }
        return self::$db;
    }
    public static function getDeploymentsCollection() {
        // CRITICAL: Must use 'labs' database to match labctl.py expectations
        // labctl.py hardcodes: self.db = self.mongo_client.labs
        return self::getClient()->selectDatabase('labs')->selectCollection('deployed_labs');
    }
    public static function getStatsDatabase() {
        return self::getClient()->selectDatabase('tom_labs_stats_db');
    }
    public static function getPassiveDatabase() {
        return self::getClient()->selectDatabase('tom_labs_passive_db');
    }
    public static function getFilesDatabase() {
        return self::getClient()->selectDatabase('tom_labs_files_db');
    }
    public static function getNextSequence($name) {
    $db = self::getDefaultDatabase();
    
    // Initialize if it doesn't exist
    $db->counters->updateOne(
        ['_id' => $name],
        ['$setOnInsert' => ['sequence_value' => 1000]],
        ['upsert' => true]
    );

    $result = $db->counters->findOneAndUpdate(
        ['_id' => $name],
        ['$inc' => ['sequence_value' => 1]],
        ['returnDocument' => MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
    );
    return $result->sequence_value;
}
}