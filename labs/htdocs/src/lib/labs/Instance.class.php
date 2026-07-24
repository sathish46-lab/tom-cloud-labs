<?php
namespace TomLabs\Labs;

use DatabaseConnection;

class Instance {
    private $db;
    private $hash;
    private $metadata;

    public function __construct($instanceHash) {
        $this->hash = $instanceHash;
        $this->db = DatabaseConnection::getClient()->selectDatabase('tom_labs_instances_db');
        $this->metadata = $this->db->instances->findOne(['instance_hash' => $this->hash]);
    }

    public function getStatus() {
        return $this->metadata['deploy']['status'] ?? $this->metadata['status'] ?? 'offline';
    }

    public function getUrl() {
        return $this->metadata['deploy']['credentials']['code_server_url'] ?? null;
    }

    public function getAssignedIP() {
        return $this->metadata['deploy']['internal_ip'] ?? '0.0.0.0';
    }

    public function getDeploy() {
        return $this->metadata['deploy'] ?? [];
    }
}
