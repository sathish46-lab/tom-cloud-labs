<?php

namespace TomLabs\Services;

require_once __DIR__ . '/ServiceConfig.class.php';
require_once __DIR__ . '/drivers/ServiceDriverInterface.class.php';
require_once __DIR__ . '/drivers/MySqlDriver.class.php';
require_once __DIR__ . '/drivers/MariaDbDriver.class.php';
require_once __DIR__ . '/drivers/MongoDbDriver.class.php';
require_once __DIR__ . '/drivers/PostgreSqlDriver.class.php';
require_once __DIR__ . '/drivers/RedisDriver.class.php';
require_once __DIR__ . '/drivers/RabbitMqDriver.class.php';

class ServiceManager
{
    public static function driver(string $service): object
    {
        $config = ServiceConfig::get($service);
        $class = $config['driver'];
        return new $class();
    }
}
