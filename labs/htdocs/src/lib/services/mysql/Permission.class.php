<?php

namespace TomLabs\Services\Mysql;

require_once __DIR__ . '/../BaseServicePermission.class.php';

use TomLabs\Services\BaseServicePermission;
use TomLabs\Services\DatabaseInfoDTO;
use TomLabs\Services\ServiceResponse;
use TomLabs\Services\ServiceStatusCodes;

/**
 * MySQL Service Permission
 *
 * Handles user and database management for the MySQL service
 * via the `labsctl service mysql` CLI.
 */
class Permission extends BaseServicePermission
{
    public function __construct($user)
    {
        parent::__construct($user, 'mysql');
    }

    private const MAX_USERS = 5;
    private const MAX_DATABASES_PER_USER = 10;
    private const DEFAULT_CHARSET = 'utf8mb4';
    private const DEFAULT_COLLATION = 'utf8mb4_0900_ai_ci';

    /**
     * Get maximum databases per user
     */
    protected function getMaxResourcesPerUser(): int
    {
        return self::MAX_DATABASES_PER_USER;
    }

    /**
     * Create database
     *
     * @param string $databaseName Database name
     * @param string $username Database username
     * @param string $charset Character set (optional)
     * @param string $collation Collation (optional)
     * @return ServiceResponse
     */
    public function createDatabase(
        string $databaseName,
        string $username,
        string $charset = self::DEFAULT_CHARSET,
        string $collation = self::DEFAULT_COLLATION
    ): ServiceResponse {
        // Validate database name and username length combined
        if ((strlen($databaseName) + strlen($username)) > 64) {
            return ServiceResponse::error(
                ServiceStatusCodes::DATABASE_NAME_INVALID,
                'Combined database name and username exceeds 64 characters'
            );
        }

        $finalDbName = $username . '_' . $databaseName;

        // Validate database name
        $nameValidation = $this->validateName($finalDbName, 5, 64);
        if ($nameValidation !== ServiceStatusCodes::SUCCESS) {
            return ServiceResponse::error($nameValidation);
        }

        $charset = preg_match('/^[a-zA-Z0-9_]+$/', $charset) ? $charset : self::DEFAULT_CHARSET;
        $collation = preg_match('/^[a-zA-Z0-9_]+$/', $collation) ? $collation : self::DEFAULT_COLLATION;

        try {
            $userRecord = $this->db()->{$this->config['users_collection']}->findOne([
                'user_id' => $this->userId(),
                'email' => $this->email,
                $this->config['username_field'] => $username
            ]);

            if (!$userRecord) {
                return ServiceResponse::error(
                    ServiceStatusCodes::USER_NOT_FOUND,
                    'MySQL User not found or permission denied.'
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

            // Execute labsctl command
            $command = "labsctl service mysql --email={$this->email} createdb"
                . " --username={$username} --database={$finalDbName}"
                . " --charset={$charset} --collation={$collation}";
            $response = $this->executeLabsctl($command, "Cannot create database {$finalDbName}");

            if ($response->isSuccess()) {
                $dbRecord = [
                    'user_id' => $this->userId(),
                    'email' => $this->email,
                    $this->config['user_id_field'] => (string) $userRecord['_id'],
                    'db_name' => $finalDbName,
                    'collation' => $collation,
                    'created_at' => \TimeHelper::now()
                ];
                $this->db()->{$this->config['resources_collection']}->insertOne($dbRecord);

                // Clear cache on successful creation
                \Cache::clear("mysql_db_list_{$this->email}_{$username}");
                // Also clear user list cache since it contains database counts
                \Cache::clear("mysql_user_list_{$this->email}");
            }

            return $response;
        } catch (\Throwable $e) {
            return $this->internalError($e, 'db_create');
        }
    }

    /**
     * Remove database
     *
     * @param string $databaseName Database name
     * @param string $username Database username (optional — resolved from the record owner)
     * @return ServiceResponse
     */
    public function removeDatabase(string $databaseName, string $username = ''): ServiceResponse
    {
        try {
            $dbRecord = $this->db()->{$this->config['resources_collection']}->findOne([
                'user_id' => $this->userId(),
                'email' => $this->email,
                'db_name' => $databaseName
            ]);

            if (!$dbRecord) {
                return ServiceResponse::error(
                    ServiceStatusCodes::USER_NOT_FOUND,
                    'Database not found or permission denied.'
                );
            }

            $username = $this->resolveOwnerUsername($dbRecord, $username);
            if ($username === '') {
                return ServiceResponse::error(
                    ServiceStatusCodes::USER_NOT_FOUND,
                    'Database not found or permission denied.'
                );
            }

            $command = "labsctl service mysql --email={$this->email} deletedb"
                . " --username={$username} --database={$databaseName}";
            $response = $this->executeLabsctl($command, "Cannot delete database {$databaseName}");

            if ($response->isSuccess()) {
                $this->db()->{$this->config['resources_collection']}->deleteOne(['_id' => $dbRecord['_id']]);

                // Clear cache on successful deletion
                \Cache::clear("mysql_db_list_{$this->email}_{$username}");
                // Also clear user list cache since it contains database counts
                \Cache::clear("mysql_user_list_{$this->email}");
            }

            return $response;
        } catch (\Throwable $e) {
            return $this->internalError($e, 'db_delete');
        }
    }

    /**
     * Get list of databases for a user
     *
     * @param string $username Username
     * @return ServiceResponse
     */
    public function getDatabaseList(string $username): ServiceResponse
    {
        if ($this->validateName($username) !== ServiceStatusCodes::SUCCESS) {
            return ServiceResponse::error(
                ServiceStatusCodes::USER_NOT_FOUND,
                'MySQL user not found.'
            );
        }

        $cacheKey = "mysql_db_list_{$this->email}_{$username}";
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
                    'MySQL user not found.'
                );
            }

            $databases = $this->db()->{$this->config['resources_collection']}->find([
                $this->config['user_id_field'] => (string) $serviceUser['_id'],
                'user_id' => $this->userId(),
                'email' => $this->email
            ])->toArray();

            $rows = [];
            foreach ($databases as $dbObj) {
                $rows[] = DatabaseInfoDTO::fromMongo($dbObj)->toArray();
            }

            if ($rows) {
                \Cache::set($cacheKey, $rows, 300); // Cache for 5 minutes
            }

            return ServiceResponse::result($rows);
        } catch (\Throwable $e) {
            return $this->internalError($e, 'check');
        }
    }

    /**
     * Get available collations for MySQL
     *
     * @return ServiceResponse
     */
    public function getCollations(): ServiceResponse
    {
        $cacheKey = "mysql_collations_{$this->email}";
        $cached = \Cache::get($cacheKey);

        // Only use cache if it's valid data (not false/null)
        if ($cached !== null && $cached !== false && is_array($cached)) {
            return ServiceResponse::success('Collations retrieved from cache', $cached);
        }

        $command = "labsctl service mysql --email={$this->email} getcollation";
        $response = $this->executeLabsctl($command, "Cannot list collations");

        if ($response->isSuccess()) {
            if (is_array($response->data) && $response->data) {
                \Cache::set($cacheKey, $response->data, 3600); // Cache for 1 hour
                return ServiceResponse::success('Collations retrieved', $response->data);
            }

            return ServiceResponse::success('No collations found', []);
        }

        return $response;
    }
}
