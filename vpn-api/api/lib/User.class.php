<?php

require_once($_SERVER['DOCUMENT_ROOT'].'/api/lib/Database.class.php');
require_once __DIR__ . '/../../vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/api/lib/traits/MongoGetterSetter.trait.php'; 
class User {
    private $db;
    private $data;
    public $username;
    public $collection;
    use MongoGetterSetter;

    public function __construct($username){
        $this->username = $username;
        $this->db = Database::getConnection();
        // $query = "SELECT * FROM auth WHERE username='$this->username' OR email='$this->username'";
        
        //echo $query;
        // $result = mysqli_query($this->db, $query);
        
        $this->collection = $this->db->auth;
        $this->data = $this->collection->findOne([
            '$or' => [
                ['username' => $this->username],
                ['email' => $this->username]
            ]
        ]);
        
        // if(mysqli_num_rows($result) == 1){
        //     $this->user = mysqli_fetch_assoc($result);
        if($this->data == null){
            throw new Exception("User not found");
        }
    }

    // public function getUsername(){
    //     // return $this->user['username'];
    //     return $this->username;
    // }

    // public function getPasswordHash(){
    //     // return $this->user['password'];
    //     return $this->password;
    // }

    // public function getEmail(){
    //     return $this->user['email'];
    // }

    // public function isActive(){
    //     return $this->user['active'];
    // }
}