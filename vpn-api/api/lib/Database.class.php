<?php

require_once __DIR__ . '/../../vendor/autoload.php';

class Database {
    static $db;
    
    public static function getConnection(){
        // Use an absolute path to the centralized config
        $config_json = file_get_contents('/var/www/env.json');
        $config = json_decode($config_json, true);
        
        if (self::$db != NULL) {
            return self::$db;
        } else {
            try {
                // Use the URI from your env.json
                $mongoClient = new MongoDB\Client($config['database_file']);
                
                // Select the VPN database
                self::$db = $mongoClient->selectDatabase($config['vpn_db']);
                return self::$db;
            } catch (Exception $e) {
                die("MongoDB Connection failed: " . $e->getMessage());
            }
        }
    }

    public static function getArray($doc){
        return json_decode(json_encode($doc), true);
    }
}