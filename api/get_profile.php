<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';

// ✅ GET USER ID FROM TOKEN
$headers = getallheaders();
if (empty($headers['Authorization'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authorization header missing'
    ]);
    exit;
}

$token = str_replace('Bearer ', '', $headers['Authorization']);

$database = new Database();
$conn = $database->getConnection();

// Validate token and get user
$userQuery = "SELECT u.id, u.role FROM users u 
              INNER JOIN user_tokens t ON u.id = t.user_id 
              WHERE t.token = :token AND t.expires_at > NOW()";
$userStmt = $conn->prepare($userQuery);
$userStmt->bindParam(':token', $token);
$userStmt->execute();
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid or expired token'
    ]);
    exit;
}

$user_id = $user['id']; // ✅ Authenticated user ID

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