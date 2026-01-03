<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

$data = json_decode(file_get_contents("php://input"));

if (
    !empty($data->name) &&
    !empty($data->email) &&
    !empty($data->password) &&
    !empty($data->role)
) {
    $user->name = $data->name;
    $user->email = $data->email;
    $user->phone = $data->phone ?? null;
    $user->password = $data->password;
    $user->role = $data->role;

    if ($user->emailExists()) {
        http_response_code(400);
        echo json_encode(["message" => "Email already exists"]);
        exit;
    }

    if ($user->register()) {
        http_response_code(201);
        echo json_encode(["message" => "User registered successfully"]);
    } else {
        http_response_code(503);
        echo json_encode(["message" => "Unable to register user"]);
    }
} else {
    http_response_code(400);
    echo json_encode(["message" => "Incomplete data"]);
}
