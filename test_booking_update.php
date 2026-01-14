<?php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo json_encode([
    'success' => true,
    'message' => 'Test endpoint working',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion()
]);
?>
