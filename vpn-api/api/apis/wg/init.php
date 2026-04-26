<?php

${basename(__FILE__, '.php')} = function(){
    if($this->isAuthenticated()){
        try {
            $device = $this->_request['device'] ?? 'wg0';
            $wg = new Wireguard($device);
            $result = $wg->initialize();
            
            $this->response($this->json([
                "status" => "success",
                "message" => "VPN Network initialized",
                "details" => $result
            ]), 200);
        } catch (Exception $e) {
            $this->response($this->json([
                "status" => "error",
                "error" => $e->getMessage()
            ]), 500);
        }
    } else {
        $this->response($this->json(["error" => "Unauthorized"]), 401);
    }
};
