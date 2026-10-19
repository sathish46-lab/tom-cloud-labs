<?php

require_once __DIR__ . '/DatabaseConnection.class.php';

/**
 * RabbitMQ vhost services store their records in the same MongoDB as the app
 * (legacy services config flag: vhost_connection = true). This thin wrapper
 * keeps that intent explicit without duplicating the connection logic.
 */
class VhostConnection
{
    public static function getClient()
    {
        return DatabaseConnection::getClient();
    }

    public static function selectVhost(string $name)
    {
        return DatabaseConnection::getClient()->selectDatabase($name);
    }
}
