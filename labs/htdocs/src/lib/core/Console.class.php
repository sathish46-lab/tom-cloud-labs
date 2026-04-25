<?php

class Console {
    public static function log($data) {
        $bt = debug_backtrace();
        $caller = array_shift($bt);
        
        $log = [
            'log' => $data,
            'caller' => [
                'file' => $caller['file'] ?? 'unknown',
                'line' => $caller['line'] ?? 0
            ]
        ];

        // Ensure session is started before accessing
        if (session_status() === PHP_SESSION_NONE) { session_start(); }

        if (!isset($_SESSION['console_logs'])) {
            $_SESSION['console_logs'] = [];
        }
        $_SESSION['console_logs'][] = $log;
    }

    public static function flush() {
        if (isset($_SESSION['console_logs']) && is_array($_SESSION['console_logs'])) {
            echo "<script>\n";
            foreach ($_SESSION['console_logs'] as $entry) {
                // Encode the entire entry as a JS Object
                $jsonLog = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                echo "console.log(" . $jsonLog . ");\n";
            }
            echo "</script>\n";
            
            // Clear the session logs so they don't print on the NEXT refresh
            unset($_SESSION['console_logs']);
        }
    }
}