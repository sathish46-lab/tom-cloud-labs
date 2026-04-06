<?php

require_once __DIR__ . '/Database.class.php';
require_once __DIR__ . '/../../vendor/autoload.php';

class IPNetwork {
    private $db;
    private $collection;
    private $network = NULL;
    public $cidr;
    public $wgdevice;
    
    public function __construct($cidr, $wgdevice){
        $this->cidr = $cidr;
        $this->wgdevice = $wgdevice;
        $this->db = Database::getConnection();
        $this->collection = $this->db->networks;
        $this->network = $this->getNetwork();
    }

    public function getNetwork(){
        if(!$this->network){
            $val = $this->collection->findOne([
                'cidr' => $this->cidr
            ]);
            return Database::getArray($val);
        } else {
            return $this->network;
        } 
    }

    public function constructNetworkFile(){
        $ip_file = $this->getNetworkFilePath($this->wgdevice);
        $cmd = 'sudo nmap -sL -n '.$this->cidr.' | awk \'/Nmap scan report/{print $NF}\'';
        $result = shell_exec($cmd);
        if ($result === null) {
            $result = ""; // Handle empty result to prevent null file write
        }
        $file = fopen($ip_file, "w"); 
        fwrite($file, $result);
        fclose($file);
    }

    public function getNetworkFilePath(){
        $file_name = str_replace('.', '_', $this->cidr);
        $file_name = str_replace('/', '_', $file_name."_".$this->wgdevice);
        //print($_SERVER['DOCUMENT_ROOT'] . '/api/networks/' . $file_name);
        return '/tmp/' . $file_name;

    }

    public function getNextInsertID(){
        $last_ip = $this->collection->findOne([], [
            'limit' => 1,
            'sort' => ['_id' => -1],
        ]);
        return $last_ip['_id'] + 1;
    }

    /**
     * Used to generate list of IP addresses when generating a new network node on Wireguard. 
     * Known Issue: If you call this multiple times, multiple documents gets inserted. Avoid this to keep the network consistant.
     */
    public function syncNetworkFile(){
        if(file_exists($this->getNetworkFilePath($this->wgdevice))){
            $data = file_get_contents($this->getNetworkFilePath($this->wgdevice));
            $data = explode(PHP_EOL, $data);
            $data = array_slice($data, 2, count($data) - 4);
            $documents = array();
            $id = $this->getNextInsertID();
            foreach($data as $datum){
                if(empty($datum)){
                    continue;
                }
                $val = [
                    '_id' => $id++,
                    'network_cidr' => $this->cidr,
                    'ip_addr' => $datum,
                    'wgdevice' => $this->wgdevice,
                    'allocated' => False,
                    'creationTime' => time(),
                    'allocationTime' => '',
                    'public_key' => '',
                    'private_key' => '',
                    'reserved' => false
                ];
                array_push($documents, $val);
            }
            $this->collection->deleteMany([
                "wgdevice" => $this->wgdevice
            ]);
            return $this->collection->insertMany($documents);

        } else {
            throw new Exception('Network file not present.');
        }
    }

    public function getNextIP($email=null, $ip = null){
        // STRICT SUBNET SEGREGATION: VPN Clients always go to 172.30.1.x
        // Ignoring the interface CIDR prefix for client allocation to preventing collisions with Labs (172.30.10.x)
        $prefix = "172.30.1.";

        $protected_ips = [];
        // Protected Gateway/Router IPs in the client subnet just in case
        for($i=1; $i<=9; $i++) {
            $protected_ips[] = $prefix . $i;
        }

        $base_query = [
            "allocated" => false,
            "wgdevice" => $this->wgdevice,
            // SECURITY: Regex enforce the subnet so we never allocate outside 172.30.1.x
            // even if the DB contains other IPs.
            "ip_addr" => [
                '$regex' => '^' . str_replace('.', '\.', $prefix),
                '$nin' => $protected_ips
            ]
        ];

        if($ip and $email){
            // Try to find if this specific user has this specific IP reserved
            $result = $this->collection->findOne(array_merge($base_query, [
                "reserved" => true,
                "ip_addr" => $ip,
                'email' => $email,  
            ]), ["sort" => ['id' => 1]]);

            if(!$result){
                // If not found, find the first available unreserved IP above .9
                $result = $this->collection->findOne(array_merge($base_query, [
                    "reserved" => false,
                ]), ["sort" => ['id' => 1]]);
            }
        } else {
            // Find the first available unreserved IP above .9
            $result = $this->collection->findOne(array_merge($base_query, [
                "reserved" => false,
            ]), ["sort" => ['id' => 1]]);
        }

        if (!$result) {
            throw new Exception("No available IPs found in the allowed range (.10 - .254)");
        }

        return $result['ip_addr'];
    }

    public function allocateIP($ip, $email, $public_key, $reserved){
        try {
            $result = $this->collection->updateOne([
                'ip_addr' => $ip,
                'wgdevice' => $this->wgdevice
            ], [
                '$set' => [
                    'allocated' => true,
                    'email' => $email,
                    'public_key' => $public_key,
                    'reserved' => $reserved
                ]
            ]);
            return $ip;
        } catch (Exception $e) {
            return false;
        }
    }

    public function reserveIP($email, $ip, $reserve=true){
    try {
        $updateData = ['$set' => ['reserved' => $reserve]];
        
        // Professional Logic: If unreserving, wipe the email ownership
        if (!$reserve) {
            $updateData['$set']['email'] = ""; 
        }

        $result = $this->collection->updateOne([
            'ip_addr' => $ip,
            'wgdevice' => $this->wgdevice,
            'email' => $email
        ], $updateData);
        
        return boolval($result->getModifiedCount());
    } catch (Exception $e) {
        return false;
    }
}

    public function unallocateIP($public_key, $reserved){
        try {
            if($reserved){
                // If just deleting device but KEEPING reservation
                $result = $this->collection->updateOne([
                    'public_key' => $public_key,
                    'wgdevice' => $this->wgdevice
                ], [
                    '$set' => [
                        'allocated' => false,
                        'reserved' => true,
                        'public_key' => ""
                        // Email remains so user still "owns" the reservation
                    ]
                ]);
            } else {
                // FULL RELEASE: Clear everything so others can use it
                $result = $this->collection->updateOne([
                    'public_key' => $public_key,
                    'wgdevice' => $this->wgdevice
                ], [
                    '$set' => [
                        'allocated' => false,
                        'email' => "", // CRITICAL: Clear email
                        'reserved' => false,
                        'public_key' => ""
                    ]
                ]);
            }
            return $result->isAcknowledged();
        } catch (Exception $e) {
            return false;
        }
    }

    public function getAll(){
        return iterator_to_array($this->collection->find(
            [
                '$and' => [
                    [
                    'email'=>[
                        '$ne' => ""
                    ],],[
                    'email'=>[
                        '$exists' => true
                    ],]
                ],
                'wgdevice' => $this->wgdevice
            ]
        ));
    }
}