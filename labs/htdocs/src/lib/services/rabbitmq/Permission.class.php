<?php

namespace TomLabs\Services\Rabbitmq;

require_once __DIR__ . '/../BaseServicePermission.class.php';

use TomLabs\Services\BaseServicePermission;
use TomLabs\Services\DatabaseInfoDTO;
use TomLabs\Services\ServiceResponse;
use TomLabs\Services\ServiceStatusCodes;

/**
 * RabbitMQ Service Permission
 *
 * Handles user and vhost management for the RabbitMQ service
 * via the `labsctl service rabbitmq` CLI.
 */
class Permission extends BaseServicePermission
{
    public function __construct($user)
    {
        parent::__construct($user, 'rabbitmq');
    }

    private const MAX_USERS = 5;
    private const MAX_VHOSTS_PER_USER = 10;
    private const DEFAULT_COLLATION = 'utf8mb4_0900_ai_ci';

    /**
     * Get maximum vhosts per user
     */
    protected function getMaxResourcesPerUser(): int
    {
        return self::MAX_VHOSTS_PER_USER;
    }

    /**
     * Create vhost
     *
     * @param string $serviceUsername Service username
     * @param string $vhostName Vhost name
     * @param string $collation Collation (optional, stored with the record)
     * @return ServiceResponse
     */
    public function createVhost(
        string $serviceUsername,
        string $vhostName,
        string $collation = self::DEFAULT_COLLATION
    ): ServiceResponse {
        if ($serviceUsername === '' || $vhostName === '') {
            return ServiceResponse::error(
                ServiceStatusCodes::DATABASE_NAME_MISSING,
                'RabbitMQ username and vhost name are required.'
            );
        }

        $nameValidation = $this->validateName($vhostName, 1, 64);
        if ($nameValidation !== ServiceStatusCodes::SUCCESS) {
            return ServiceResponse::error(
                $nameValidation,
                'Vhost name can only contain lowercase letters, numbers, and underscores.'
            );
        }

        $collation = preg_match('/^[a-zA-Z0-9_]+$/', $collation) ? $collation : self::DEFAULT_COLLATION;

        try {
            $userRecord = $this->db()->{$this->config['users_collection']}->findOne([
                'user_id' => $this->userId(),
                'email' => $this->email,
                $this->config['username_field'] => $serviceUsername
            ]);

            if (!$userRecord) {
                return ServiceResponse::error(
                    ServiceStatusCodes::USER_NOT_FOUND,
                    'RabbitMQ User not found or permission denied.'
                );
            }

            $quota = $this->assertResourceQuota([
                $this->config['user_id_field'] => (string) $userRecord['_id'],
                'email' => $this->email,
                'user_id' => $this->userId()
            ]);
            if ($quota->isError()) {
                return $quota;
            }

            $finalVhostName = $serviceUsername . '_' . $vhostName;

            // Execute labsctl command
            $command = "labsctl service rabbitmq --email={$this->email} createvhost"
                . " --username={$serviceUsername} --vhost={$finalVhostName}";
            $response = $this->executeLabsctl($command, "Cannot create vhost {$finalVhostName}");

            if ($response->isSuccess()) {
                $dbRecord = [
                    'user_id' => $this->userId(),
                    'email' => $this->email,
                    $this->config['user_id_field'] => (string) $userRecord['_id'],
                    'vhost_name' => $finalVhostName,
                    'collation' => $collation,
                    'created_at' => \TimeHelper::now()
                ];
                $this->db()->{$this->config['resources_collection']}->insertOne($dbRecord);

                // Clear cache on successful creation
                \Cache::clear("rabbitmq_db_list_{$this->email}_{$serviceUsername}");
                // Also clear user list cache since it contains vhost counts
                \Cache::clear("rabbitmq_user_list_{$this->email}");
            }

            return $response;
        } catch (\Throwable $e) {
            return $this->internalError($e, 'vhost_create');
        }
    }

    /**
     * Remove vhost
     *
     * @param string $vhostName Vhost name
     * @param string $username Service username (optional — resolved from the record owner)
     * @return ServiceResponse
     */
    public function removeVhost(string $vhostName, string $username = ''): ServiceResponse
    {
        try {
            $dbRecord = $this->db()->{$this->config['resources_collection']}->findOne([
                'user_id' => $this->userId(),
                'email' => $this->email,
                'vhost_name' => $vhostName
            ]);

            if (!$dbRecord) {
                return ServiceResponse::error(
                    ServiceStatusCodes::USER_NOT_FOUND,
                    'Vhost not found or permission denied.'
                );
            }

            $username = $this->resolveOwnerUsername($dbRecord, $username);
            if ($username === '') {
                return ServiceResponse::error(
                    ServiceStatusCodes::USER_NOT_FOUND,
                    'Vhost not found or permission denied.'
                );
            }

            $command = "labsctl service rabbitmq --email={$this->email} deletevhost"
                . " --username={$username} --vhost={$vhostName}";
            $response = $this->executeLabsctl($command, "Cannot delete vhost {$vhostName}");

            if ($response->isSuccess()) {
                $this->db()->{$this->config['resources_collection']}->deleteOne(['_id' => $dbRecord['_id']]);

                // Clear cache on successful deletion
                \Cache::clear("rabbitmq_db_list_{$this->email}_{$username}");
                // Also clear user list cache since it contains vhost counts
                \Cache::clear("rabbitmq_user_list_{$this->email}");
            }

            return $response;
        } catch (\Throwable $e) {
            return $this->internalError($e, 'vhost_delete');
        }
    }

    /**
     * Get list of vhosts for a user
     *
     * @param string $username Username
     * @return ServiceResponse
     */
    public function getDatabaseList(string $username): ServiceResponse
    {
        if ($this->validateName($username) !== ServiceStatusCodes::SUCCESS) {
            return ServiceResponse::error(
                ServiceStatusCodes::USER_NOT_FOUND,
                'RabbitMQ user not found.'
            );
        }

        $cacheKey = "rabbitmq_db_list_{$this->email}_{$username}";
        $cached = \Cache::get($cacheKey);

        // Only use cache if it's valid data (not false/null)
        if ($cached !== null && $cached !== false && is_array($cached)) {
            return ServiceResponse::result($cached);
        }

        try {
            $serviceUser = $this->db()->{$this->config['users_collection']}->findOne([
                'user_id' => $this->userId(),
                'email' => $this->email,
                $this->config['username_field'] => $username
            ]);

            if (!$serviceUser) {
                return ServiceResponse::error(
                    ServiceStatusCodes::USER_NOT_FOUND,
                    'RabbitMQ user not found.'
                );
            }

            $vhosts = $this->db()->{$this->config['resources_collection']}->find([
                $this->config['user_id_field'] => (string) $serviceUser['_id'],
                'user_id' => $this->userId(),
                'email' => $this->email
            ])->toArray();

            $rows = [];
            foreach ($vhosts as $vhostObj) {
                $row = DatabaseInfoDTO::fromMongo($vhostObj)->toArray();
                $row['db_name'] = $vhostObj['vhost_name'] ?? ($row['db_name'] ?? '');
                $rows[] = $row;
            }

            if ($rows) {
                \Cache::set($cacheKey, $rows, 300); // Cache for 5 minutes
            }

            return ServiceResponse::result($rows);
        } catch (\Throwable $e) {
            return $this->internalError($e, 'check');
        }
    }
}
