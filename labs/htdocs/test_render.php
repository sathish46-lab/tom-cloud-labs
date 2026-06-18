<?php
require_once 'src/load.php';

Session::set('error_exception', new Exception("Test exception"));
ob_start();
Session::loadTemplate('_error');
$out = ob_get_clean();

echo "OUTPUT LENGTH: " . strlen($out) . "\n";
echo "OUTPUT: \n" . $out;
