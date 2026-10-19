<?php

namespace TomLabs\Services;

class ServiceStatusCodes
{
    const SUCCESS = 200;
    const CREATED = 201;
    const VALIDATION_ERROR = 400;
    const USERNAME_MISSING = 410;
    const USERNAME_INVALID = 411;
    const PASSWORD_MISSING = 412;
    const DATABASE_NAME_MISSING = 420;
    const DATABASE_NAME_INVALID = 421;
    const USER_NOT_FOUND = 404;
    const USER_ALREADY_EXISTS = 409;
    const DATABASE_ALREADY_EXISTS = 410;
    const USER_LIMIT_EXCEEDED = 429;
    const DATABASE_LIMIT_EXCEEDED = 430;
    const LIMIT_EXCEEDED = 431;
    const OPERATION_FAILED = 500;
    const INTERNAL_ERROR = 501;
    const SERVICE_UNAVAILABLE = 503;
    const SERVICE_DISABLED = 1010;

    public static function message(int $code): string
    {
        switch ($code) {
            case self::SUCCESS:                 return 'Success';
            case self::CREATED:                 return 'Created';
            case self::VALIDATION_ERROR:        return 'Validation error.';
            case self::USERNAME_MISSING:        return 'Username is required.';
            case self::USERNAME_INVALID:        return 'Username can only contain lowercase letters, numbers, and underscores.';
            case self::PASSWORD_MISSING:        return 'Password is required.';
            case self::DATABASE_NAME_MISSING:   return 'Database name is required.';
            case self::DATABASE_NAME_INVALID:   return 'Database name can only contain lowercase letters, numbers, and underscores.';
            case self::USER_NOT_FOUND:          return 'User not found.';
            case self::USER_ALREADY_EXISTS:     return 'User already exists.';
            case self::DATABASE_ALREADY_EXISTS: return 'Database already exists.';
            case self::USER_LIMIT_EXCEEDED:     return 'User limit exceeded.';
            case self::DATABASE_LIMIT_EXCEEDED: return 'Database limit exceeded.';
            case self::LIMIT_EXCEEDED:          return 'Limit exceeded.';
            case self::OPERATION_FAILED:        return 'Operation failed.';
            case self::SERVICE_UNAVAILABLE:     return 'Service unavailable.';
            case 1004:                          return 'The submitted input is not valid.';
            case 1005:                          return 'Username and password are required.';
            case 1006:                          return 'Username can only contain lowercase letters, numbers, and underscores.';
            case 1007:                          return 'Username is required.';
            case 1008:                          return 'Database name is required.';
            case 1009:                          return 'Vhost name is required.';
            case 1010:                          return 'This service has been disabled by an administrator.';
            default:                            return 'Internal error.';
        }
    }
}
