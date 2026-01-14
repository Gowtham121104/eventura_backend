<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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

try {
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

    $user_id = $user['id']; // ✅ Get user ID from token

    // Get POST data
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';

    // Validate input
    if (empty($name) || empty($email)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Name and email are required"
        ]);
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid email format"
        ]);
        exit;
    }

    // Check if email already exists for another user
    $checkQuery = "SELECT id FROM users WHERE email = :email AND id != :user_id LIMIT 1";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bindParam(":email", $email);
    $checkStmt->bindParam(":user_id", $user_id);
    $checkStmt->execute();

    if ($checkStmt->rowCount() > 0) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Email already exists for another user"
        ]);
        exit;
    }

    // Update user profile
    $query = "UPDATE users 
              SET name = :name, email = :email, phone = :phone 
              WHERE id = :user_id";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(":name", $name);
    $stmt->bindParam(":email", $email);
    $stmt->bindParam(":phone", $phone);
    $stmt->bindParam(":user_id", $user_id);

    if ($stmt->execute()) {
        // Fetch updated profile
        $fetchQuery = "SELECT id, name, email, phone, profile_image 
                      FROM users WHERE id = :user_id LIMIT 1";
        $fetchStmt = $conn->prepare($fetchQuery);
        $fetchStmt->bindParam(":user_id", $user_id);
        $fetchStmt->execute();
        
        $userData = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Profile updated successfully",
            "data" => [
                "id" => intval($userData['id']),
                "name" => $userData['name'],
                "email" => $userData['email'],
                "phone" => $userData['phone'] ?? "",
                "profile_image" => $userData['profile_image'] ?? "default_avatar"
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Failed to update profile"
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>