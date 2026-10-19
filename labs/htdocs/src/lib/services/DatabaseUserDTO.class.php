<?php

namespace TomLabs\Services;

class DatabaseUserDTO
{
    /** @var int|string */
    public $userId;
    public string $email;
    public string $username;
    public string $password;

    /**
     * @param int|string $userId Preserved as-is so Mongo records keep the
     *                           same type the UI queries with (int for user_id).
     */
    public function __construct($userId, string $email, string $username, string $password)
    {
        $this->userId = $userId;
        $this->email = $email;
        $this->username = $username;
        $this->password = $password;
    }

    public function toMongoRecord(string $usernameField): array
    {
        return [
            'user_id' => $this->userId,
            'email' => $this->email,
            $usernameField => $this->username,
            str_replace('_username', '_password', $usernameField) => base64_encode($this->password),
            'created_at' => \TimeHelper::now()
        ];
    }
}
