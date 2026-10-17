<?php

namespace TomLabs\Services;

require_once __DIR__ . '/ServiceResponse.class.php';
require_once __DIR__ . '/../exceptions/ServiceValidationException.class.php';
require_once __DIR__ . '/../exceptions/UsernamePasswordRequiredException.class.php';
require_once __DIR__ . '/../exceptions/UsernameInvalidException.class.php';
require_once __DIR__ . '/../exceptions/UsernameRequiredException.class.php';
require_once __DIR__ . '/../exceptions/DatabaseNameRequiredException.class.php';
require_once __DIR__ . '/../exceptions/VhostNameRequiredException.class.php';

class ServiceValidator
{
    public static function input(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public static function usernamePassword(array $data): array
    {
        $rawUsername = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';

        if ($rawUsername === '' || $password === '') {
            throw new \UsernamePasswordRequiredException();
        }

        $username = preg_replace('/[^a-zA-Z0-9_]/', '', strtolower($rawUsername));
        if ($username !== $rawUsername) {
            throw new \UsernameInvalidException();
        }

        return [$username, $password];
    }

    public static function requiredField(array $data, string $key, string $label): string
    {
        $value = trim($data[$key] ?? '');
        if ($value === '') {
            switch ($label) {
                case 'Username':
                    throw new \UsernameRequiredException();
                case 'Database name':
                    throw new \DatabaseNameRequiredException();
                case 'Vhost name':
                    throw new \VhostNameRequiredException();
                default:
                    throw new \ServiceValidationException();
            }
        }
        return $value;
    }

    public static function collation(string $collation, string $default): string
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $collation)) {
            return $default;
        }
        return $collation;
    }
}
