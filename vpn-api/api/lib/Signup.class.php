<?php
// Fix for CLI/Terminal: If DOCUMENT_ROOT is not set, calculate it relative to this file
if (!isset($_SERVER['DOCUMENT_ROOT']) or empty($_SERVER['DOCUMENT_ROOT'])) {
    $_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../');
}

require_once($_SERVER['DOCUMENT_ROOT'].'/api/lib/Database.class.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/api/lib/traits/MongoGetterSetter.trait.php');
require_once __DIR__ . '/../../vendor/autoload.php';

//TODO Homework: find why ../vendor? it is the same reason why we use ../../env.json in config.

class Signup {
    use MongoGetterSetter;
    
    private $username;
    private $password;
    private $email;
    private $token;
    private $id;
    
    private $db;
    private $db_name;
    public $collection;
    public $data;

    public function __construct($username, $password, $email, $active=0){
        $this->db = Database::getConnection();
        $this->collection = $this->db->auth;
        $this->username = $username;
        $this->password = $password;
        $this->email = $email;

        if($this->userExists()){
            throw new Exception("User already exists");
        }

        $bytes = random_bytes(16);
        $this->token = $token = bin2hex($bytes);
        $password = $this->hashPassword();
        $this->data = $this->collection->insertOne([
            'username' => $this->username,
            'password' => $password,
            'email' => $this->email,
            'active' => $active,
            'token' => $this->token
        ]);
        if($this->data->getInsertedCount() == 0){
            throw new Exception("Unable to signup, user account might already exist.");
        } else {
            $this->id = $this->data->getInsertedId();
            // $this->sendVerificationMail();
        }
    }
    
    function sendVerificationMail(){
        $config_json = file_get_contents($_SERVER['DOCUMENT_ROOT'].'/../env.json');
        $config = json_decode($config_json, true);
        $token = $this->token;
        $email = new \SendGrid\Mail\Mail();
        $email->setFrom("noreply@selfmade.ninja", "API Course by Selfmade");
        $email->setSubject("Verify your account");
        $email->addTo($this->email, $this->username);
        $email->addContent("text/plain", "Please verify your account at: https://vpn.selfmade.ninja/verify?token=$token");
        $email->addContent(
            "text/html", "<strong>Please verify your account by <a href=\"https://vpn.selfmade.ninja/verify?token=$token\">clicking here</a> or open this URL manually: <a href=\"https://vpn.selfmade.ninja/verify?token=$token\">https://vpn.selfmade.ninja/verify?token=$token</a></strong>"
        );
        $sendgrid = new \SendGrid($config['email_api_key']);
        try {
            $response = $sendgrid->send($email);
            // print $response->statusCode() . "\n";
            // print_r($response->headers());
            // print $response->body() . "\n";
        } catch (Exception $e) {
            echo 'Caught exception: '. $e->getMessage() ."\n";
        }
        
    }
    
    public function getInsertID(){
        return $this->id;
    }
    
    public function userExists(){
        //TODO: Write the code to check if user exists.
        return false;
    }
    
    public function hashPassword($cost = 10){
        //echo $this->password;
        $options = [
            "cost" => $cost
        ];
        return password_hash($this->password, PASSWORD_BCRYPT, $options);
    }

    public static function verifyAccount($token){
        $db = Database::getConnection();
        
        $user = $db->auth->findOne(['token' => $token]);

        if($user){
            if($user['active'] == 1){
                throw new Exception("Already Verified");
            }
            $user->updateOne(['_id' => $user->_id], ['$set'=>["active"=>"1"]]);
            return true;
        } else {
            return false;
        }
    }
    
}