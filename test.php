<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

echo json_encode(array(
    "status" => "success",
    "message" => "Eventura API is running!",
    "timestamp" => date("Y-m-d H:i:s")
));
?>