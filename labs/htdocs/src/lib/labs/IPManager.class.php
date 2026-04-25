<?php
namespace TomLabs\Labs;

use Exception;
use DatabaseConnection;
use MongoDB\Operation\FindOneAndUpdate;

class IPManager {
    private $db;
    private $collection;
    private $protected_range = 10;
    private $ip_prefix = '172.30.0.';

    public function __construct() {
        $this->db = DatabaseConnection::getClient()->selectDatabase('tom_labs_db');
        $this->collection = $this->db->lab_ips;
    }

/**
 * UPDATED: Checks both Lab IPs and VPN Device IPs to prevent collisions
 */public function getNextIPForUser($email, $instanceHash, $labType = 'essentials') {
    // 1. Check existing assignment
    $reserved = $this->collection->findOne(['reserved_to' => $email, 'allocated_to' => $instanceHash]);
    if ($reserved) return $reserved['ip_addr'];

    // 2. Get all IPs used by physical devices to prevent collisions
    // NOTE: With strict segregation (VPN=1.x, Labs=10.x), this is less critical but good for safety.
    $vpnDb = DatabaseConnection::getClient()->selectDatabase('tom_labs_vpn');
    $userDevices = $vpnDb->networks->find(['email' => $email])->toArray();

    $forbiddenIps = [];
    foreach ($userDevices as $d) {
        if (!empty($d['assigned_ip'])) {
            $forbiddenIps[] = $d['assigned_ip'];
        }
    }

    // 3. Prepare the query
    $query = [
        'allocated' => false,
        'status' => 'available',
        // 'ip_numeric' => ['$gte' => 21, '$lte' => 100] // REMOVED: Allow full range allocation within the subnet
    ];

    if (!empty($forbiddenIps)) {
        $query['ip_addr'] = ['$nin' => $forbiddenIps];
    }

    // 4. Find and Update (FIXED SYNTAX)
    $result = $this->collection->findOneAndUpdate(
        $query,
        [
            '$set' => [
                'status'       => 'allocated',
                'allocated'    => true,
                'allocated_to' => $instanceHash,
                'email'        => $email,
                'reserved_to'  => $email,
                'service_type' => $labType,
                'label'        => ucfirst($labType) . ' Lab',
                'last_deploy'  => time()
            ]
        ],
        [
            // Sort by last_deploy ascending so we pick the "oldest" available IP
            'sort' => ['last_deploy' => 1, 'ip_numeric' => 1], 
            'returnDocument' => \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER
        ]
    );

    if (!$result) {
        throw new Exception("IP Pool Full or Colliding with VPN Devices!");
    }

    return $result['ip_addr'];
}

    /**
     * Initialize or reset IP pool
     * Call this once during setup or to reset the pool
     */
    public function initializePool($start = 11, $end = 254) {
        $this->collection->deleteMany([]); // Clear existing pool
        
        $bulk = [];
        for ($i = $start; $i <= $end; $i++) {
            $bulk[] = [
                'ip_addr' => $this->ip_prefix . $i,
                'ip_numeric' => $i,  // For proper sorting
                'status' => 'available',
                'allocated' => false,
                'reserved_to' => null,
                'allocated_to' => null,
                'email' => null,
                'service_type' => null,
                'label' => null,
                'created_at' => time()
            ];
        }
        
        if (!empty($bulk)) {
            $this->collection->insertMany($bulk);
            error_log("IPManager: Initialized IP pool with " . count($bulk) . " addresses");
        }
    }

    /**
     * Release IP back to pool when lab is deleted (not just stopped)
     */
    public function release($ip, $email) {
        $result = $this->collection->updateOne(
            ['ip_addr' => $ip, 'reserved_to' => $email],
            [
                '$set' => [
                    'status' => 'available', 
                    'allocated' => false,
                    'reserved_to' => null
                ],
                '$unset' => [
                    'allocated_to' => "", 
                    'email' => "",
                    'service_type' => "",
                    'label' => ""
                ] 
            ]
        );
        
        error_log("IPManager: Released IP $ip");
        return $result;
    }

    /**
     * Get allocation statistics
     */
    public function getStats() {
        $total = $this->collection->countDocuments([]);
        $allocated = $this->collection->countDocuments(['allocated' => true]);
        $available = $total - $allocated;
        
        // Get breakdown by service type
        $byType = [];
        $pipeline = [
            ['$match' => ['allocated' => true]],
            ['$group' => [
                '_id' => '$service_type',
                'count' => ['$sum' => 1]
            ]]
        ];
        
        $typeStats = $this->collection->aggregate($pipeline)->toArray();
        foreach ($typeStats as $stat) {
            $byType[$stat['_id'] ?? 'unknown'] = $stat['count'];
        }
        
        return [
            'total' => $total,
            'allocated' => $allocated,
            'available' => $available,
            'utilization' => $total > 0 ? round(($allocated / $total) * 100, 2) : 0,
            'by_type' => $byType
        ];
    }

    /**
     * Force release stale allocations (IPs allocated but container doesn't exist)
     */
    public function cleanupStaleAllocations() {
        // This should be called periodically or on demand
        $allocated = $this->collection->find(['allocated' => true]);
        $cleaned = 0;
        
        foreach ($allocated as $record) {
            $hash = $record['allocated_to'] ?? null;
            if (!$hash) continue;
            
            // Check if container exists
            $containerExists = shell_exec("docker ps -a -q -f name=^{$hash}$");
            
            if (empty(trim($containerExists))) {
                // Container doesn't exist, release IP
                $this->collection->updateOne(
                    ['_id' => $record['_id']],
                    [
                        '$set' => [
                            'status' => 'available',
                            'allocated' => false,
                            'reserved_to' => null
                        ],
                        '$unset' => [
                            'allocated_to' => '',
                            'email' => '',
                            'service_type' => '',
                            'label' => ''
                        ]
                    ]
                );
                $cleaned++;
                error_log("IPManager: Cleaned stale IP {$record['ip_addr']}");
            }
        }
        
        return $cleaned;
    }

    /**
     * Get all IPs allocated to a specific user
     */
    public function getUserAllocations($email) {
        return $this->collection->find([
            'allocated' => true,
            'reserved_to' => $email
        ])->toArray();
    }

    /**
     * Update service type for an existing allocation
     */
    public function updateServiceType($instanceHash, $labType) {
        return $this->collection->updateOne(
            ['allocated_to' => $instanceHash],
            ['$set' => [
                'service_type' => $labType,
                'label' => ucfirst($labType) . ' Lab'
            ]]
        );
    }
}