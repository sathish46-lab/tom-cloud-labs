<?php

/**
 * Manages the execution of system processes in the background.
 */
class Process {
    private $pid;
    private $command;
    private $tmp;
    private $nohup = true;

    public function __construct($cmd = false, $nohup = true, $tempfile) {
        $this->nohup = $nohup;
        if ($cmd !== false) {
            $this->command = $cmd;
            $this->tmp = $tempfile;
            $this->runCom();
        }
    }

    private function runCom() {
    $logFile = '/var/log/labs_deploy.log';
    
    // Ensure absolute path for the 'nohup' and 'echo' commands
    $command = $this->command . ' >> ' . $logFile . ' 2>&1 & /usr/bin/echo $!';
    
    if ($this->nohup) {
        $command = '/usr/bin/nohup ' . $command;
    }
    
    exec($command, $op);
    
    foreach ($op as $line) {
        $line = trim($line);
        if (is_numeric($line)) {
            $this->pid = (int)$line;
            return;
        }
    }
    $this->pid = 0;
}
    
    public function getPid() {
        return $this->pid;
    }

    public function isAlive() {
        if (!$this->pid) return false;
        // Checks if the process is still running in the system
        $command = 'ps -p ' . $this->pid;
        exec($command, $op);
        return isset($op[1]);
    }
}