<?php

/**
 * Orchestrates background worker tasks.
 */
class Worker {
    private $path;
    private $worker = null;
    private $name = null;
    private $arg1; // User identifier (Base64)
    private $arg2; // Task arguments (Base64)

    public function __construct($worker, $args = array()) {
        // Define the path where worker files are stored
        $this->path = __DIR__ . "/../../../worker/";

        if (!is_file($this->path . $worker . ".worker.php")) {
            throw new Exception("WorkerNotFoundException: " . $worker);
        } else {
            $this->name = $worker;
            $this->worker = $this->path . $worker . ".worker.php";
            
            // Encode session and arguments for command-line safety
            if (Session::getAuthStatus() == Constants::STATUS_LOGGEDIN) {
                // FIXED: Changed getEmail() to getUsername(). 
                // The Python script matches SSH keys by username, not email.
                $this->arg1 = base64_encode(Session::getUser()->getUsername());
                $this->arg2 = base64_encode(json_encode($args));
            }
        }
    }

    public function invoke() {
        if (Session::getAuthStatus() == Constants::STATUS_LOGGEDIN) {
            // FIXED: Use absolute path for PHP to avoid environment PATH issues
            $phpPath = '/usr/bin/php'; 
            
            // Ensure the worker path is explicitly quoted
            $cmd = $phpPath . ' "' . $this->worker . '" ' . $this->arg1 . ' ' . $this->arg2;
            
            $tempFile = $this->name . "_" . time() . ".log";
            return new Process($cmd, true, $tempFile);
        }
        return false;
    }
}