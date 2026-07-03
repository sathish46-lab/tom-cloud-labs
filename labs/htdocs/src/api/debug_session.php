<?php
require_once '../load.php';
echo "network_resources: ";
print_r(Session::get('network_resources', []));
