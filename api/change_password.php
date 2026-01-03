<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get POST data
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $current_password = isset($_POST['current_password']) ? trim($_POST['current_password']) : '';
    $new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';

    // Validate input
    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid user ID"
        ]);
        exit();
    }

    if (empty($current_password) || empty($new_password)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Current password and new password are required"
        ]);
        exit();
    }

    if (strlen($new_password) < 6) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "New password must be at least 6 characters long"
        ]);
        exit();
    }

    try {
        $database = new Database();
        $conn = $database->getConnection();

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
            exit();
        }

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $stored_password = $user['password'];

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
            exit();
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
} else {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed. Use POST request."
    ]);
}
?>