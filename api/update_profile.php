<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get POST data
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';

    // Validate input
    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid user ID"
        ]);
        exit();
    }

    if (empty($name) || empty($email)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Name and email are required"
        ]);
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Invalid email format"
        ]);
        exit();
    }

    try {
        $database = new Database();
        $conn = $database->getConnection();

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
            exit();
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
            
            $user = $fetchStmt->fetch(PDO::FETCH_ASSOC);

            http_response_code(200);
            echo json_encode([
                "success" => true,
                "message" => "Profile updated successfully",
                "data" => [
                    "id" => intval($user['id']),
                    "name" => $user['name'],
                    "email" => $user['email'],
                    "phone" => $user['phone'] ?? "",
                    "profile_image" => $user['profile_image'] ?? "default_avatar"
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
} else {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed. Use POST request."
    ]);
}
?>