<?php

namespace TomLabs\Services\Redis;

require_once __DIR__ . '/../BaseServicePermission.class.php';

/**
 * Redis Service Permission
 *
 * Handles user management for the Redis service (users only — no databases)
 * via the `labsctl service redis` CLI.
 */
use TomLabs\Services\BaseServicePermission;

class Permission extends BaseServicePermission
{
    public function __construct($user)
    {
        parent::__construct($user, 'redis');
    }

    private const MAX_USERS = 5;
    private const MAX_DATABASES_PER_USER = 0;

    /**
     * Get maximum Redis users per account
     */
    protected function getMaxUsers(): int
    {
        return self::MAX_USERS;
    }

    /**
     * Redis has no databases — users only
     */
    protected function getMaxResourcesPerUser(): int
    {
        return self::MAX_DATABASES_PER_USER;
    }
}
