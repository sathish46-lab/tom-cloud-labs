<?php

${basename(__FILE__, '.php')} = function(){
    if($this->get_request_method() == "POST" and $this->isAuthenticated() and !empty($this->_request['public_key']) and !empty($this->_request['email'])){
        try{
            $device = $this->_request['device'] ?? 'wg0';
            $wg = new Wireguard($device);
            
            // Auto-initialize if database is empty
            $wg->initialize();
            
            // 1. Logic Check: If a specific IP is requested, we should still 
            // check if it falls within the protected .1 - .9 range.
            $requestedIp = $this->_request['ip'] ?? null;
            if ($requestedIp) {
                $parts = explode('.', $requestedIp);
                if ((int)end($parts) < 10) {
                    throw new Exception("IP address range .1 to .9 is reserved for system infrastructure.");
                }
            }

            // 2. Add Peer: The IPNetwork::getNextIP method will now 
            // automatically skip .1 through .9 if no specific IP is provided.
            $isReserved = isset($this->_request['reserved']) ? boolval($this->_request['reserved']) : false;
            
            $result = $wg->addPeer(
                $this->_request['public_key'], 
                $this->_request['email'], 
                $isReserved, 
                $requestedIp
            );

            $this->response($this->json(["result" => $result]), 200);

        } catch(Exception $e){
            $this->response($this->json(["error" => $e->getMessage()]), 403);
        }
    } else {
        $this->response($this->json(["error" => "Bad request: Missing public_key or email"]), 400);
    }
};