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
    $current_password = isset($_POST['current_password']) ? trim($_POST['current_password']) : '';
    $new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';

    // Validate input
    if (empty($current_password) || empty($new_password)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Current password and new password are required"
        ]);
        exit;
    }

    if (strlen($new_password) < 6) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "New password must be at least 6 characters long"
        ]);
        exit;
    }

    // Get current password from database
    $query = "SELECT password FROM users WHERE id = :user_id LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();

    if ($stmt->rowCount() == 0) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "User not found"
        ]);
        exit;
    }

    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    $stored_password = $userData['password'];

    // Verify current password (check both plain text and hashed)
    $password_verified = false;
    
    if (password_verify($current_password, $stored_password)) {
        // Password is hashed and matches
        $password_verified = true;
    } elseif ($current_password === $stored_password) {
        // Password is stored in plain text and matches
        $password_verified = true;
    }

    if (!$password_verified) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Current password is incorrect"
        ]);
        exit;
    }

    // Hash new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // Update password
    $updateQuery = "UPDATE users SET password = :password WHERE id = :user_id";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bindParam(":password", $hashed_password);
    $updateStmt->bindParam(":user_id", $user_id);

    if ($updateStmt->execute()) {
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Password changed successfully"
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Failed to change password"
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