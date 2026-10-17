<?php

namespace TomLabs\Services;

class DatabaseUserDTO
{
    public string $userId;
    public string $email;
    public string $username;
    public string $password;

    public function __construct(string $userId, string $email, string $username, string $password)
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
            'created_at' => new \MongoDB\BSON\UTCDateTime()
        ];
    }
}
