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

// Read JSON input
$data = json_decode(file_get_contents("php://input"));

// Validate required fields
if (
    !empty($data->name) &&
    !empty($data->email) &&
    !empty($data->password) &&
    !empty($data->role)
) {
    // Assign values
    $user->name = $data->name;
    $user->email = $data->email;
    $user->phone = $data->phone ?? null;
    $user->password = $data->password;
    $user->role = $data->role;

    // Check if email already exists
    if ($user->emailExists()) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Email already exists",
            "data" => null
        ]);
        exit;
    }

    // Register user
    if ($user->register()) {

        // Ensure ID is available
        $user->id = $db->lastInsertId();

        // Generate auth token (replace with JWT later)
        $token = bin2hex(random_bytes(32));

        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => "User registered successfully",
            "data" => [
                "token" => $token,
                "user" => [
                    "id" => (int)$user->id,
                    "name" => $user->name,
                    "email" => $user->email,
                    "phone" => $user->phone,
                    "user_type" => $user->role
                ]
            ]
        ]);
    } else {
        http_response_code(503);
        echo json_encode([
            "success" => false,
            "message" => "Unable to register user",
            "data" => null
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Incomplete data",
        "data" => null
    ]);
}
