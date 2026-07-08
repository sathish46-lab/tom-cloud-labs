<?php
namespace Auth;

use DatabaseConnection;
use Exception;

class EmailAuth {
    private $usersCollection;

    public function __construct() {
        try {
            $db = DatabaseConnection::getDefaultDatabase();
            $this->usersCollection = $db->users;
        } catch (Exception $e) {
            error_log("EmailAuth DB Error: " . $e->getMessage());
        }
    }

    /**
     * Generate a reset token for the given email or username.
     */
    public function requestReset($emailOrUsername) {
        if (!$this->usersCollection || empty($emailOrUsername)) {
            return false;
        }

        try {
            $user = $this->usersCollection->findOne([
                '$or' => [
                    ['email' => $emailOrUsername],
                    ['username' => $emailOrUsername]
                ]
            ]);

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $this->usersCollection->updateOne(
                    ['_id' => $user['_id']],
                    ['$set' => [
                        'reset_token' => $token,
                        'reset_expires' => time() + 3600
                    ]]
                );
                
                if (function_exists('send_password_reset_email')) {
                    send_password_reset_email($user['email'], $user['username'] ?? $user['email'], $token);
                }
                
                return $token;
            }
        } catch (Exception $e) {
            error_log("requestReset Error: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Reset the password using the provided reset token.
     */
    public function resetPassword($token, $newPassword) {
        if (!$this->usersCollection || empty($token) || empty($newPassword)) {
            return false;
        }

        try {
            $user = $this->usersCollection->findOne([
                'reset_token' => $token,
                'reset_expires' => ['$gt' => time()]
            ]);

            if ($user) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $this->usersCollection->updateOne(
                    ['_id' => $user['_id']],
                    [
                        '$set' => ['password' => $hashedPassword],
                        '$unset' => ['reset_token' => '', 'reset_expires' => '']
                    ]
                );
                return true;
            }
        } catch (Exception $e) {
            error_log("resetPassword Error: " . $e->getMessage());
        }

        return false;
    }
}
