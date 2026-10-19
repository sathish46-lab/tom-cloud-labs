<?php

namespace TomLabs\Services;

class ServiceConfig
{
    private static ?array $services = null;

    public static function get(string $service): array
    {
        $all = self::all();
        if (!isset($all[$service])) {
            throw new \InvalidArgumentException("Unknown service: {$service}");
        }
        return $all[$service];
    }

    public static function all(): array
    {
        if (self::$services === null) {
            self::$services = [
                'mysql' => [
                    'label' => 'MySQL',
                    'driver_type' => 'database',
                    'users_collection' => 'mysql_users',
                    'resources_collection' => 'mysql_databases',
                    'username_field' => 'mysql_username',
                    'resource_field' => 'db_name',
                    'user_id_field' => 'mysql_user_id',
                    'max_users' => 5,
                    'max_resources' => 10,
                    'resource_noun' => 'databases',
                    'default_collation' => 'utf8mb4_0900_ai_ci',
                    'soft_delete_user' => true,
                    'vhost_connection' => false,
                ],
                'mariadb' => [
                    'label' => 'MariaDB',
                    'driver_type' => 'database',
                    'users_collection' => 'mariadb_users',
                    'resources_collection' => 'mariadb_databases',
                    'username_field' => 'mariadb_username',
                    'resource_field' => 'db_name',
                    'user_id_field' => 'mariadb_user_id',
                    'max_users' => 5,
                    'max_resources' => 10,
                    'resource_noun' => 'databases',
                    'default_collation' => 'utf8mb4_general_ci',
                    'soft_delete_user' => false,
                    'vhost_connection' => false,
                ],
                'mongodb' => [
                    'label' => 'MongoDB',
                    'driver_type' => 'database',
                    'users_collection' => 'mongodb_users',
                    'resources_collection' => 'mongodb_databases',
                    'username_field' => 'mongodb_username',
                    'resource_field' => 'db_name',
                    'user_id_field' => 'mongodb_user_id',
                    'max_users' => 5,
                    'max_resources' => 10,
                    'resource_noun' => 'databases',
                    'default_collation' => 'utf8mb4_0900_ai_ci',
                    'soft_delete_user' => false,
                    'vhost_connection' => false,
                ],
                'postgresql' => [
                    'label' => 'PostgreSQL',
                    'driver_type' => 'database',
                    'users_collection' => 'postgresql_users',
                    'resources_collection' => 'postgresql_databases',
                    'username_field' => 'postgresql_username',
                    'resource_field' => 'db_name',
                    'user_id_field' => 'postgresql_user_id',
                    'max_users' => 5,
                    'max_resources' => 10,
                    'resource_noun' => 'databases',
                    'default_collation' => 'UTF8',
                    'soft_delete_user' => false,
                    'vhost_connection' => false,
                ],
                'redis' => [
                    'label' => 'Redis',
                    'driver_type' => 'user_only',
                    'users_collection' => 'redis_users',
                    'resources_collection' => 'redis_databases',
                    'username_field' => 'redis_username',
                    'resource_field' => 'db_name',
                    'user_id_field' => 'redis_user_id',
                    'max_users' => 5,
                    'max_resources' => 0,
                    'resource_noun' => 'databases',
                    'default_collation' => 'utf8mb4_0900_ai_ci',
                    'soft_delete_user' => false,
                    'vhost_connection' => false,
                ],
                'rabbitmq' => [
                    'label' => 'RabbitMQ',
                    'driver_type' => 'vhost',
                    'users_collection' => 'rabbitmq_users',
                    'resources_collection' => 'rabbitmq_vhosts',
                    'username_field' => 'rabbitmq_username',
                    'resource_field' => 'vhost_name',
                    'user_id_field' => 'rabbitmq_user_id',
                    'max_users' => 5,
                    'max_resources' => 10,
                    'resource_noun' => 'vhosts',
                    'default_collation' => 'utf8mb4_0900_ai_ci',
                    'soft_delete_user' => false,
                    'vhost_connection' => true,
                ],
            ];
        }
        return self::$services;
    }
}
