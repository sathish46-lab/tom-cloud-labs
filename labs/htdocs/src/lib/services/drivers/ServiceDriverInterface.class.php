<?php

interface ServiceDriverInterface
{
    public function createUser(string $userName, string $password): bool;

    public function deleteUser(string $userName): bool;
}

interface DatabaseServiceDriverInterface extends ServiceDriverInterface
{
    public function createDatabase(string $dbName, string $ownerName, string $collation = ''): bool;

    public function deleteDatabase(string $dbName): bool;
}

interface VhostServiceDriverInterface extends ServiceDriverInterface
{
    public function createVhost(string $vhostName, string $ownerName, string $collation = ''): bool;

    public function deleteVhost(string $vhostName): bool;
}
