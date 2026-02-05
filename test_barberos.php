<?php
// Mock GET parameters
$_GET['sucursal_id'] = 1;

// Include the API file
// Note: We need to handle the fact that API might output JSON and exit.
// But get_barberos.php doesn't exit, it just echoes.

ob_start();
require 'api/get_barberos.php';
$output = ob_get_clean();

echo "API Output:\n" . $output . "\n";
?>
