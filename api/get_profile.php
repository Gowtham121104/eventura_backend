<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

// Get user_id from query parameter
if (!isset($_GET['user_id'])) {
    http_response_code(400);
    echo json_encode(array(
        "success" => false,
        "message" => "user_id parameter is required"
    ));
    exit();
}

$user_id = $_GET['user_id'];

// Fetch user profile
$query = "SELECT id, name, email, phone, profile_image, created_at FROM users WHERE id = :user_id LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(array(
        "success" => true,
        "message" => "Profile retrieved successfully",
        "user" => array(
            "id" => (int)$row['id'],
            "name" => $row['name'],
            "email" => $row['email'],
            "phone" => $row['phone'] ?? "",
            "profile_image" => $row['profile_image'] ?? "default_avatar"
        )
    ));
} else {
    http_response_code(404);
    echo json_encode(array(
        "success" => false,
        "message" => "User not found"
    ));
}
?>