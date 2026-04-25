<?php

class System {
    /**
     * Detects the current Operating System.
     * 1 = Windows, 2 = macOS, 3 = Linux/Docker
     */
    public static function getOS() {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return 1;
        } elseif (PHP_OS === 'Darwin') {
            return 2;
        } else {
            return 3;
        }
    }
}