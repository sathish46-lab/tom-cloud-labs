<?php

require_once __DIR__ . '/Database.class.php';
require_once __DIR__ . '/../../vendor/autoload.php';

class IPNetwork {
    private $db;
    private $collection;
    public $cidr;
    public $wgdevice;
    
    public function __construct($cidr, $wgdevice){
        $this->cidr = $cidr;
        $this->wgdevice = $wgdevice;
        $this->db = Database::getConnection();
        // Single source of truth: ip_registry in tom_labs_db
        $this->collection = $this->db->ip_registry;
    }

    public function getNextIP($email=null, $ip = null){
        $opt_config = json_decode(@file_get_contents('/opt/labs-control-panel/config.json'), true) ?: [];
        $tunnel_prefix = $opt_config['tunnel_ip'] ?? '172.31.0.';
        $prefix = $tunnel_prefix;

        // Protected IPs: .0 (network) and .1 (gateway/server)
        $protected_ips = [];
        for($i=0; $i<=1; $i++) {
            $protected_ips[] = $prefix . $i;
        }

        $base_match = [
            "status" => "available",
            "ip_addr" => [
                '$regex' => '^' . str_replace('.', '\.', $prefix),
                '$nin' => $protected_ips
            ]
        ];

        if($ip && $email){
            // Try to atomically claim the specific requested IP if it is still available.
            // findOneAndUpdate is a single server-side operation: find + reserve together,
            // so two concurrent requests for the same IP cannot both succeed.
            $result = $this->collection->findOneAndUpdate(
                array_merge($base_match, ["ip_addr" => $ip]),
                ['$set' => [
                    'status'     => 'reserved',
                    'email'      => $email,
                    'reserved_to'=> $email,
                ]],
                ["sort" => ['ip_numeric' => 1], 'returnDocument' => \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
            );

            if($result){
                return $result['ip_addr'];
            }
            // Specific IP is no longer available (taken by a concurrent request),
            // fall through to assign the next available IP.
        }

        // Atomically find and reserve the next available IP in one operation.
        $result = $this->collection->findOneAndUpdate(
            $base_match,
            ['$set' => [
                'status'     => 'reserved',
                'email'      => $email,
                'reserved_to'=> $email,
            ]],
            ["sort" => ['ip_numeric' => 1], 'returnDocument' => \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
        );

        if (!$result) {
            throw new Exception("No available IPs found in the allowed range");
        }

        return $result['ip_addr'];
    }

    /**
     * Confirm the IP allocation (called after getNextIP + successful wg set).
     * The IP is already atomically reserved by getNextIP(); this re-confirms
     * the fields and is safe to call on an already-reserved document.
     */
    public function allocateIP($ip, $email, $public_key, $reserved){
        try {
            $result = $this->collection->updateOne([
                'ip_addr' => $ip
            ], [
                '$set' => [
                    'status'     => 'reserved',
                    'email'      => $email,
                    'reserved_to'=> $email,
                ]
            ]);
            return $ip;
        } catch (Exception $e) {
            return false;
        }
    }

    public function reserveIP($email, $ip, $reserve=true){
        try {
            if ($reserve) {
                $updateData = ['$set' => [
                    'status' => 'reserved',
                    'reserved_to' => $email,
                    'email' => $email,
                ]];
            } else {
                $updateData = ['$set' => [
                    'status' => 'available',
                    'reserved_to' => null,
                    'email' => null,
                ]];
            }

            $result = $this->collection->updateOne([
                'ip_addr' => $ip
            ], $updateData);
            
            return boolval($result->getModifiedCount());
        } catch (Exception $e) {
            return false;
        }
    }

    public function unallocateIP($public_key, $reserved){
        try {
            // Find the IP by looking up public_key in devices collection
            $devices = $this->db->devices;
            $device = $devices->findOne(['public_key' => $public_key]);
            
            if (!$device) {
                return false;
            }
            
            $ip = $device['assigned_ip'] ?? null;
            if (!$ip) {
                return false;
            }

            if($reserved){
                // Keep reservation but remove allocation
                $result = $this->collection->updateOne([
                    'ip_addr' => $ip
                ], [
                    '$set' => [
                        'status' => 'reserved',
                    ]
                ]);
            } else {
                // FULL RELEASE: Clear everything
                $result = $this->collection->updateOne([
                    'ip_addr' => $ip
                ], [
                    '$set' => [
                        'status' => 'available',
                        'email' => null,
                        'reserved_to' => null,
                    ]
                ]);
            }
            return $result->isAcknowledged();
        } catch (Exception $e) {
            return false;
        }
    }

    public function getAll(){
        return iterator_to_array($this->collection->find([
            'status' => 'reserved',
            'email' => ['$ne' => null, '$exists' => true]
        ]));
    }
}
