<?php
// src/lib/core/User.class.php
class User {
    private $user;
    private $db;

    public function __construct($email) {
        $this->db = DatabaseConnection::getDefaultDatabase();
        $this->user = $this->db->users->findOne(['email' => $email]);
    }

    public function __call($method, $args) {
        if (substr($method, 0, 3) == "get") {
            // Converts "getUserId" to "user_id"
            $property = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', substr($method, 3)));
            
            // Handle MongoDB BSON objects vs Arrays
            if (isset($this->user[$property])) {
                return $this->user[$property];
            }
        }
        return null;
    }
    // Inside src/lib/core/User.class.php
    public function getLabHash($labName) {
        $email = $this->getEmail(); 
        // Use the secret key from your env.json for security
        $salt = "8b51626f3a468904e8b6f83747f2fcf1"; 
        
        if (!$email) return md5("guest" . $labName . $salt);
        return md5($email . $labName . $salt);
    }


    public function getLabData($labName) {
        $hash = $this->getLabHash($labName);
        return $this->db->deployed_labs->findOne(['instance_hash' => $hash]);
    }
}