<?php
require_once 'src/load.php';

// Force an exception to trigger the global error handler
throw new Exception("This is a simulated exception to test the new error UI");
