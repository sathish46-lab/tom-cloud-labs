<?php
namespace TomLabs\Labs;

use DatabaseConnection;

class Instance {
    private $db;
    private $hash;
    private $metadata;

    public function __construct($instanceHash) {
        $this->hash = $instanceHash;
        $this->db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
        $this->metadata = $this->db->deployed_labs->findOne(['instance_hash' => $this->hash]);
    }

    public function getStatus() {
        return $this->metadata['status'] ?? 'offline';
    }

    public function getUrl() {
        return $this->metadata['credentials']['code_server_url'] ?? null;
    }

    public function getAssignedIP() {
        return $this->metadata['internal_ip'] ?? '0.0.0.0';
    }
}