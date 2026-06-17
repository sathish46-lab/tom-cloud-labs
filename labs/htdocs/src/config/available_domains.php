<?php
/**
 * Available System Domains Configuration
 * 
 * Add new domains to this array to make them appear in the domains dropdown.
 * Also configure the server IP for A records here.
 * 
 * No database interaction needed - just edit this file!
 */

return [
    // Server IP for A Records (Loaded dynamically)
    'server_ip' => \TomLabs\Core\Env::get('SERVER_IP', '106.51.76.75'),
    
    // Available domain patterns
    'domains' => [
        '*.tomweb.shop',
        '*.tomweb.fun',
        '*.awshosting.in',
        // Add more domains here as needed
    ],
];
