<?php

namespace TomLabs\Services;

require_once __DIR__ . '/ServiceResponse.class.php';
require_once __DIR__ . '/ServiceValidator.class.php';
require_once __DIR__ . '/ServiceConfig.class.php';
require_once __DIR__ . '/ServiceFlags.class.php';
require_once __DIR__ . '/DatabaseInfoDTO.class.php';
require_once __DIR__ . '/DatabaseUserDTO.class.php';

/**
 * Service permission and operations manager (base; config-driven)
 *
 * Bookkeeping (users/resources collections) lives in MongoDB; every engine
 * operation runs a raw `labsctl service <slug> --email=… <action> …` command
 * implemented in opt/labs-control-panel/src/commands/service.py, which emits a
 * JSON envelope {"status","code","message","data"}.
 *
 * Concurrency/transport rules:
 * - labsctl refusing an operation (exit 1 + valid envelope) maps to the
 *   $errorMessage passed by the concrete service class.
 * - A transport failure (labsctl missing, bad/absent output, abnormal exit)
 *   throws RuntimeException; the public methods' catch(\Throwable) converts it
 *   via internalError(), like the old driver connection failures.
 *
 * Concrete classes: TomLabs\Services\{Mysql,Mariadb,Mongodb,Postgresql,Redis,Rabbitmq}\Permission
 */
abstract class BaseServicePermission
{
    private string $service;
    protected array $config;
    private $user;
    protected string $email;

    public function __construct($user, string $service)
    {
        $this->user = $user;
        $this->service = $service;
        $this->config = ServiceConfig::get($service);

        // Email is interpolated into labsctl command strings — sanitize hard:
        // only a plain address shape is allowed, anything else is treated as
        // absent (labsctl ignores an empty --email=).
        $email = trim((string) $user->getEmail());
        $this->email = preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/', $email) ? $email : '';

        if (!ServiceFlags::isEnabled($service)) {
            throw new \ServiceDisabledException();
        }
    }

    /**
     * Run a raw `labsctl service …` command and decode its JSON envelope.
     *
     * @param string $command     full command, e.g. "labsctl service mariadb --email=x createdb --username=u --database=d --charset=utf8mb4 --collation=utf8mb4_general_ci"
     * @param string $errorMessage message returned when the engine refuses the operation
     * @return ServiceResponse
     * @throws \RuntimeException on transport failure (invalid/absent envelope)
     */
    protected function executeLabsctl(string $command, string $errorMessage): ServiceResponse
    {
        $lines = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $lines, $exitCode);

        $raw = trim(implode("\n", $lines));
        $decoded = $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($decoded) || !isset($decoded['status'], $decoded['code'], $decoded['message'])) {
            throw new \RuntimeException(
                'labsctl service transport failure (exit ' . $exitCode . '): ' . substr($raw, 0, 300)
            );
        }
        if ($exitCode !== 0 && $exitCode !== 1) {
            throw new \RuntimeException(
                'labsctl service failed (exit ' . $exitCode . '): ' . $decoded['message']
            );
        }

        if ($decoded['status'] !== 'success') {
            return ServiceResponse::error((int) $decoded['code'], $errorMessage);
        }

        $data = $decoded['data'] ?? [];
        return ServiceResponse::success((string) $decoded['message'], is_array($data) ? $data : []);
    }

    /**
     * Maximum service users for the current account (overridable per service).
     */
    protected function getMaxUsers(): int
    {
        return (int) $this->config['max_users'];
    }

    /**
     * Maximum databases/vhosts per service user (overridable per service).
     */
    protected function getMaxResourcesPerUser(): int
    {
        return (int) $this->config['max_resources'];
    }

    protected function label(): string
    {
        return $this->config['label'];
    }

    protected function userId()
    {
        return $this->user->getUserId();
    }

    protected function db()
    {
        $client = $this->config['vhost_connection']
            ? \VhostConnection::getClient()
            : \DatabaseConnection::getClient();
        return $client->selectDatabase('tom_labs_db');
    }

    protected function validateName(string $name, int $min = 1, int $max = 64): int
    {
        if ($name === '') {
            return ServiceStatusCodes::DATABASE_NAME_MISSING;
        }
        if (!preg_match('/^[a-z0-9_]+$/', $name) || strlen($name) < $min || strlen($name) > $max) {
            return ServiceStatusCodes::DATABASE_NAME_INVALID;
        }
        return ServiceStatusCodes::SUCCESS;
    }

    /**
     * Resolve the owning service username for a resource record (records store
     * the owner's ObjectId as a string). Falls back to the caller-provided
     * username when the owner lookup is impossible.
     */
    protected function resolveOwnerUsername($resourceRecord, string $fallback = ''): string
    {
        $ownerId = (string) ($resourceRecord[$this->config['user_id_field']] ?? '');
        if ($ownerId !== '' && preg_match('/^[a-f0-9]{24}$/', $ownerId)) {
            $owner = $this->db()->{$this->config['users_collection']}->findOne([
                '_id' => new \MongoDB\BSON\ObjectId($ownerId)
            ]);
            if ($owner && isset($owner[$this->config['username_field']])) {
                return (string) $owner[$this->config['username_field']];
            }
        }
        return $fallback;
    }

    protected function assertUserQuota(): ServiceResponse
    {
        $maxUsers = $this->getMaxUsers();
        $count = (int) $this->db()->{$this->config['users_collection']}->countDocuments([
            'user_id' => $this->userId(),
            'email' => $this->email
        ]);
        if ($count >= $maxUsers) {
            return ServiceResponse::error(
                ServiceStatusCodes::USER_LIMIT_EXCEEDED,
                'You have reached the maximum limit of ' . $maxUsers . ' ' . $this->label() . ' users.'
            );
        }
        return ServiceResponse::success();
    }

    protected function assertResourceQuota(array $filter): ServiceResponse
    {
        $maxResources = $this->getMaxResourcesPerUser();
        $count = (int) $this->db()->{$this->config['resources_collection']}->countDocuments($filter);
        if ($count >= $maxResources) {
            return ServiceResponse::error(
                ServiceStatusCodes::DATABASE_LIMIT_EXCEEDED,
                'You have reached the limit of ' . $maxResources . ' ' . $this->config['resource_noun'] . ' for this user.'
            );
        }
        return ServiceResponse::success();
    }

    protected function internalError(\Throwable $e, string $method): ServiceResponse
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
        $nameValidation = $this->validateName($username);
        if ($nameValidation !== ServiceStatusCodes::SUCCESS) {
            return ServiceResponse::error(
                ServiceStatusCodes::USERNAME_INVALID,
                'Username can only contain lowercase letters, numbers, and underscores.'
            );
        }

        $quota = $this->assertUserQuota();
        if ($quota->isError()) {
            return $quota;
        }

        try {
            $command = "labsctl service {$this->service} --email={$this->email} createuser"
                . " --username={$username} --password=" . escapeshellarg($password);
            $response = $this->executeLabsctl($command, "Cannot create {$this->label()} user");

            if ($response->isSuccess()) {
                $dto = new DatabaseUserDTO($this->userId(), $this->email, $username, $password);
                $this->db()->{$this->config['users_collection']}->insertOne(
                    $dto->toMongoRecord($this->config['username_field'])
                );
                \Cache::clear("{$this->service}_user_list_{$this->email}");

                return ServiceResponse::success($this->label() . ' user created successfully.');
            }

            return $response;
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
                'email' => $this->email,
                $this->config['username_field'] => $username
            ]);

            if (!$userRecord) {
                return ServiceResponse::error(
                    ServiceStatusCodes::USER_NOT_FOUND,
                    $this->label() . ' User not found or permission denied.'
                );
            }

            if ($this->config['driver_type'] !== 'user_only') {
                $resources = $this->db()->{$this->config['resources_collection']}->find([
                    $this->config['user_id_field'] => (string) $userRecord['_id'],
                    'user_id' => $this->userId(),
                    'email' => $this->email
                ]);
                foreach ($resources as $doc) {
                    if ($this->config['driver_type'] === 'vhost') {
                        $this->executeLabsctl(
                            "labsctl service {$this->service} --email={$this->email} deletevhost"
                            . " --username={$username} --vhost={$doc[$this->config['resource_field']]}",
                            "Cannot delete vhost {$doc[$this->config['resource_field']]}"
                        );
                    } else {
                        $this->executeLabsctl(
                            "labsctl service {$this->service} --email={$this->email} deletedb"
                            . " --username={$username} --database={$doc[$this->config['resource_field']]}",
                            "Cannot delete database {$doc[$this->config['resource_field']]}"
                        );
                    }
                    $this->db()->{$this->config['resources_collection']}->deleteOne(['_id' => $doc['_id']]);
                }
            }

            $userResult = $this->executeLabsctl(
                "labsctl service {$this->service} --email={$this->email} deleteuser --username={$username}",
                "Cannot delete {$this->label()} user"
            );
            if ($userResult->isError()) {
                error_log("Failed to drop user {$username} in " . $this->label() . ", but continuing with MongoDB cleanup.");
            }

            if ($this->config['soft_delete_user']) {
                $this->db()->{$this->config['users_collection']}->updateOne(['_id' => $userRecord['_id']], [
                    '$set' => [
                        'status' => 'deleted',
                        'deleted_at' => \TimeHelper::now(),
                        'deleted_by' => $this->email,
                    ]
                ]);
            } else {
                $this->db()->{$this->config['users_collection']}->deleteOne(['_id' => $userRecord['_id']]);
            }

            \Cache::clear("{$this->service}_db_list_{$this->email}_{$username}");
            \Cache::clear("{$this->service}_user_list_{$this->email}");

            return ServiceResponse::success(
                $this->label() . ' user and all associated ' . $this->config['resource_noun'] . ' deleted successfully.'
            );
        } catch (\Throwable $e) {
            return $this->internalError($e, 'user_delete');
        }
    }
}
