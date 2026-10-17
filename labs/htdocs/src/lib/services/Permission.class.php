<?php

namespace TomLabs\Services;

require_once __DIR__ . '/ServiceResponse.class.php';
require_once __DIR__ . '/ServiceValidator.class.php';
require_once __DIR__ . '/ServiceConfig.class.php';
require_once __DIR__ . '/ServiceManager.class.php';
require_once __DIR__ . '/DatabaseInfoDTO.class.php';
require_once __DIR__ . '/DatabaseUserDTO.class.php';

/**
 * Service permission and operations manager (all services, config-driven)
 *
 * Handles user, database and vhost management per service slug
 */
class Permission
{
    private string $service;
    private array $config;
    private $user;
    private ?object $driver = null;

    public function __construct($user, string $service)
    {
        $this->user = $user;
        $this->service = $service;
        $this->config = ServiceConfig::get($service);
    }

    private function driver(): object
    {
        if ($this->driver === null) {
            $this->driver = ServiceManager::driver($this->service);
        }
        return $this->driver;
    }

    private function label(): string
    {
        return $this->config['label'];
    }

    private function userId(): string
    {
        return $this->user->getUserId();
    }

    private function email(): string
    {
        return $this->user->getEmail();
    }

    private function db()
    {
        $client = $this->config['vhost_connection']
            ? \VhostConnection::getClient()
            : \DatabaseConnection::getClient();
        return $client->selectDatabase('tom_labs_db');
    }

    private function validateName(string $name, int $min = 1, int $max = 64): int
    {
        if ($name === '') {
            return ServiceStatusCodes::DATABASE_NAME_MISSING;
        }
        if (!preg_match('/^[a-z0-9_]+$/', $name) || strlen($name) < $min || strlen($name) > $max) {
            return ServiceStatusCodes::DATABASE_NAME_INVALID;
        }
        return ServiceStatusCodes::SUCCESS;
    }

    private function assertUserQuota(): ServiceResponse
    {
        $count = (int) $this->db()->{$this->config['users_collection']}->countDocuments([
            'user_id' => $this->userId(),
            'email' => $this->email()
        ]);
        if ($count >= $this->config['max_users']) {
            return ServiceResponse::error(
                ServiceStatusCodes::USER_LIMIT_EXCEEDED,
                'You have reached the maximum limit of ' . $this->config['max_users'] . ' ' . $this->label() . ' users.'
            );
        }
        return ServiceResponse::success();
    }

    private function assertResourceQuota(array $filter): ServiceResponse
    {
        $count = (int) $this->db()->{$this->config['resources_collection']}->countDocuments($filter);
        if ($count >= $this->config['max_resources']) {
            return ServiceResponse::error(
                ServiceStatusCodes::DATABASE_LIMIT_EXCEEDED,
                'You have reached the limit of ' . $this->config['max_resources'] . ' ' . $this->config['resource_noun'] . ' for this user.'
            );
        }
        return ServiceResponse::success();
    }

    private function internalError(\Throwable $e, string $method): ServiceResponse
    {
        $context = 'services/' . $this->service . '/' . $method;
        return ServiceResponse::error(
            ServiceStatusCodes::OPERATION_FAILED,
            \errors_from_exception($e, $context)['error']
        );
    }

    /**
     * Create service user
     */
    public function createUser(string $username, string $password): ServiceResponse
    {
        $quota = $this->assertUserQuota();
        if ($quota->isError()) {
            return $quota;
        }

        try {
            if (!$this->driver()->createUser($username, $password)) {
                return ServiceResponse::error(
                    ServiceStatusCodes::USER_ALREADY_EXISTS,
                    'Failed to create ' . $this->label() . " user. The username '{$username}' might already be taken globally."
                );
            }

            $dto = new DatabaseUserDTO($this->userId(), $this->email(), $username, $password);
            $this->db()->{$this->config['users_collection']}->insertOne(
                $dto->toMongoRecord($this->config['username_field'])
            );

            return ServiceResponse::success($this->label() . ' user created successfully.');
        } catch (\Throwable $e) {
            return $this->internalError($e, 'user_create');
        }
    }

    /**
     * Delete service user and all associated resources
     */
    public function deleteUser(string $username): ServiceResponse
    {
        try {
            $userRecord = $this->db()->{$this->config['users_collection']}->findOne([
                'user_id' => $this->userId(),
                'email' => $this->email(),
                $this->config['username_field'] => $username
            ]);

            if (!$userRecord) {
                return ServiceResponse::error(
                    ServiceStatusCodes::USER_NOT_FOUND,
                    $this->label() . ' User not found or permission denied.'
                );
            }

            $manager = $this->driver();

            if ($this->config['driver_type'] !== 'user_only') {
                $resources = $this->db()->{$this->config['resources_collection']}->find([
                    $this->config['user_id_field'] => (string) $userRecord['_id'],
                    'user_id' => $this->userId(),
                    'email' => $this->email()
                ]);
                foreach ($resources as $doc) {
                    if ($this->config['driver_type'] === 'vhost') {
                        $manager->deleteVhost($doc[$this->config['resource_field']]);
                    } else {
                        $manager->deleteDatabase($doc[$this->config['resource_field']]);
                    }
                    $this->db()->{$this->config['resources_collection']}->deleteOne(['_id' => $doc['_id']]);
                }
            }

            if (!$manager->deleteUser($username)) {
                error_log("Failed to drop user {$username} in " . $this->label() . ", but continuing with MongoDB cleanup.");
            }

            if ($this->config['soft_delete_user']) {
                $this->db()->{$this->config['users_collection']}->updateOne(['_id' => $userRecord['_id']], [
                    '$set' => [
                        'status' => 'deleted',
                        'deleted_at' => new \MongoDB\BSON\UTCDateTime(),
                        'deleted_by' => $this->email(),
                    ]
                ]);
            } else {
                $this->db()->{$this->config['users_collection']}->deleteOne(['_id' => $userRecord['_id']]);
            }

            return ServiceResponse::success(
                $this->label() . ' user and all associated ' . $this->config['resource_noun'] . ' deleted successfully.'
            );
        } catch (\Throwable $e) {
            return $this->internalError($e, 'user_delete');
        }
    }

    /**
     * Create database
     */
    public function createDatabase(string $serviceUsername, string $databaseName, string $collation = ''): ServiceResponse
    {
        if ($serviceUsername === '' || $databaseName === '') {
            return ServiceResponse::error(
                ServiceStatusCodes::DATABASE_NAME_MISSING,
                $this->label() . ' username and database name are required.'
            );
        }

        if ((strlen($databaseName) + strlen($serviceUsername)) > 64) {
            return ServiceResponse::error(
                ServiceStatusCodes::DATABASE_NAME_INVALID,
                'Combined database name and username exceeds 64 characters'
            );
        }

        $nameValidation = $this->validateName($databaseName, 1, 64);
        if ($nameValidation !== ServiceStatusCodes::SUCCESS) {
            return ServiceResponse::error($nameValidation);
        }

        $collation = $collation !== '' && preg_match('/^[a-zA-Z0-9_]+$/', $collation)
            ? $collation
            : $this->config['default_collation'];

        try {
            $userRecord = $this->db()->{$this->config['users_collection']}->findOne([
                'user_id' => $this->userId(),
                'email' => $this->email(),
                $this->config['username_field'] => $serviceUsername
            ]);

            if (!$userRecord) {
                return ServiceResponse::error(
                    ServiceStatusCodes::USER_NOT_FOUND,
                    $this->label() . ' User not found or permission denied.'
                );
            }

            $quota = $this->assertResourceQuota([
                $this->config['user_id_field'] => (string) $userRecord['_id'],
                'email' => $this->email(),
                'user_id' => $this->userId()
            ]);
            if ($quota->isError()) {
                return $quota;
            }

            $finalDbName = $serviceUsername . '_' . $databaseName;

            if (!$this->driver()->createDatabase($finalDbName, $serviceUsername, $collation)) {
                return ServiceResponse::error(
                    ServiceStatusCodes::DATABASE_ALREADY_EXISTS,
                    "Failed to create database. The name '{$finalDbName}' might already exist."
                );
            }

            $dbRecord = [
                'user_id' => $this->userId(),
                'email' => $this->email(),
                $this->config['user_id_field'] => (string) $userRecord['_id'],
                'db_name' => $finalDbName,
                'collation' => $collation,
                'created_at' => new \MongoDB\BSON\UTCDateTime()
            ];
            $this->db()->{$this->config['resources_collection']}->insertOne($dbRecord);

            return ServiceResponse::success('Database created successfully.', [
                'db_name' => $finalDbName
            ]);
        } catch (\Throwable $e) {
            return $this->internalError($e, 'db_create');
        }
    }

    /**
     * Remove database
     */
    public function removeDatabase(string $databaseName): ServiceResponse
    {
        try {
            $dbRecord = $this->db()->{$this->config['resources_collection']}->findOne([
                'user_id' => $this->userId(),
                'email' => $this->email(),
                'db_name' => $databaseName
            ]);

            if (!$dbRecord) {
                return ServiceResponse::error(
                    ServiceStatusCodes::USER_NOT_FOUND,
                    'Database not found or permission denied.'
                );
            }

            if (!$this->driver()->deleteDatabase($databaseName)) {
                return ServiceResponse::error(
                    ServiceStatusCodes::OPERATION_FAILED,
                    'Failed to drop database.'
                );
            }

            $this->db()->{$this->config['resources_collection']}->deleteOne(['_id' => $dbRecord['_id']]);

            return ServiceResponse::success('Database deleted successfully.');
        } catch (\Throwable $e) {
            return $this->internalError($e, 'db_delete');
        }
    }

    /**
     * Get list of databases for a user
     */
    public function getDatabaseList(string $username): ServiceResponse
    {
        try {
            $serviceUser = $this->db()->{$this->config['users_collection']}->findOne([
                'user_id' => $this->userId(),
                'email' => $this->email(),
                $this->config['username_field'] => $username
            ]);

            if (!$serviceUser) {
                return ServiceResponse::error(
                    ServiceStatusCodes::USER_NOT_FOUND,
                    $this->label() . ' user not found.'
                );
            }

            $databases = $this->db()->{$this->config['resources_collection']}->find([
                $this->config['user_id_field'] => (string) $serviceUser['_id'],
                'user_id' => $this->userId(),
                'email' => $this->email()
            ])->toArray();

            $rows = [];
            foreach ($databases as $dbObj) {
                $row = DatabaseInfoDTO::fromMongo($dbObj)->toArray();
                if ($this->config['resource_field'] !== 'db_name') {
                    $row['db_name'] = $dbObj[$this->config['resource_field']] ?? ($row['db_name'] ?? '');
                }
                $rows[] = $row;
            }

            return ServiceResponse::result($rows);
        } catch (\Throwable $e) {
            return $this->internalError($e, 'check');
        }
    }

    /**
     * Create vhost (RabbitMQ)
     */
    public function createVhost(string $serviceUsername, string $vhostName, string $collation = ''): ServiceResponse
    {
        if ($serviceUsername === '' || $vhostName === '') {
            return ServiceResponse::error(
                ServiceStatusCodes::DATABASE_NAME_MISSING,
                $this->label() . ' username and vhost name are required.'
            );
        }

        $nameValidation = $this->validateName($vhostName, 1, 64);
        if ($nameValidation !== ServiceStatusCodes::SUCCESS) {
            return ServiceResponse::error(
                $nameValidation,
                'Vhost name can only contain lowercase letters, numbers, and underscores.'
            );
        }

        $collation = $collation !== '' && preg_match('/^[a-zA-Z0-9_]+$/', $collation)
            ? $collation
            : $this->config['default_collation'];

        try {
            $userRecord = $this->db()->{$this->config['users_collection']}->findOne([
                'user_id' => $this->userId(),
                'email' => $this->email(),
                $this->config['username_field'] => $serviceUsername
            ]);

            if (!$userRecord) {
                return ServiceResponse::error(
                    ServiceStatusCodes::USER_NOT_FOUND,
                    $this->label() . ' User not found or permission denied.'
                );
            }

            $quota = $this->assertResourceQuota([
                $this->config['user_id_field'] => (string) $userRecord['_id'],
                'email' => $this->email(),
                'user_id' => $this->userId()
            ]);
            if ($quota->isError()) {
                return $quota;
            }

            $finalVhostName = $serviceUsername . '_' . $vhostName;

            if (!$this->driver()->createVhost($finalVhostName, $serviceUsername, $collation)) {
                return ServiceResponse::error(
                    ServiceStatusCodes::DATABASE_ALREADY_EXISTS,
                    "Failed to create vhost. The name '{$finalVhostName}' might already exist."
                );
            }

            $dbRecord = [
                'user_id' => $this->userId(),
                'email' => $this->email(),
                $this->config['user_id_field'] => (string) $userRecord['_id'],
                'vhost_name' => $finalVhostName,
                'collation' => $collation,
                'created_at' => new \MongoDB\BSON\UTCDateTime()
            ];
            $this->db()->{$this->config['resources_collection']}->insertOne($dbRecord);

            return ServiceResponse::success('Vhost created successfully.', [
                'vhost_name' => $finalVhostName
            ]);
        } catch (\Throwable $e) {
            return $this->internalError($e, 'vhost_create');
        }
    }

    /**
     * Remove vhost (RabbitMQ)
     */
    public function removeVhost(string $vhostName): ServiceResponse
    {
        try {
            $dbRecord = $this->db()->{$this->config['resources_collection']}->findOne([
                'user_id' => $this->userId(),
                'email' => $this->email(),
                'vhost_name' => $vhostName
            ]);

            if (!$dbRecord) {
                return ServiceResponse::error(
                    ServiceStatusCodes::USER_NOT_FOUND,
                    'Vhost not found or permission denied.'
                );
            }

            if (!$this->driver()->deleteVhost($vhostName)) {
                return ServiceResponse::error(
                    ServiceStatusCodes::OPERATION_FAILED,
                    'Failed to drop vhost.'
                );
            }

            $this->db()->{$this->config['resources_collection']}->deleteOne(['_id' => $dbRecord['_id']]);

            return ServiceResponse::success('Vhost deleted successfully.');
        } catch (\Throwable $e) {
            return $this->internalError($e, 'vhost_delete');
        }
    }

    /**
     * Get list of vhosts for a user (RabbitMQ)
     */
    public function getVhostList(string $username): ServiceResponse
    {
        return $this->getDatabaseList($username);
    }
}
