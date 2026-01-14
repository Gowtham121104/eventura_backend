<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../../config/database.php';
include_once '../../models/User.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

// Get posted data
$data = json_decode(file_get_contents("php://input"));

if(
    !empty($data->email) &&
    !empty($data->password)
) {
    $user->email = $data->email;
    
    $stmt = $user->login();
    $num = $stmt->rowCount();

    if($num > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify password
        if(password_verify($data->password, $row['password'])) {
            
            // Check if user is active
            if(isset($row['status']) && $row['status'] !== 'active') {
                http_response_code(403);
                echo json_encode([
                    "success" => false,
                    "message" => "Account is not active",
                    "data" => null
                ]);
                exit;
            }
            
            // Generate token
            $token = bin2hex(random_bytes(32));
            $userId = (int)$row['id'];
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            try {
                // Delete old tokens for this user (optional - keeps only latest token)
                $deleteQuery = "DELETE FROM user_tokens WHERE user_id = :user_id";
                $deleteStmt = $db->prepare($deleteQuery);
                $deleteStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $deleteStmt->execute();

                // Insert new token into user_tokens table
                $insertQuery = "INSERT INTO user_tokens (user_id, token, expires_at) 
                               VALUES (:user_id, :token, :expires_at)";
                $insertStmt = $db->prepare($insertQuery);
                $insertStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $insertStmt->bindParam(':token', $token);
                $insertStmt->bindParam(':expires_at', $expiresAt);
                $insertStmt->execute();
                
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode([
                    "success" => false,
                    "message" => "Error creating token: " . $e->getMessage(),
                    "data" => null
                ]);
                exit;
            }
            
            // Get organizer_id if user is an organizer
            $organizerId = null;
            if($row['role'] === 'organizer') {
                try {
                    $orgQuery = "SELECT id FROM organizer_profiles WHERE user_id = :user_id LIMIT 1";
                    $orgStmt = $db->prepare($orgQuery);
                    $orgStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                    $orgStmt->execute();
                    $organizer = $orgStmt->fetch(PDO::FETCH_ASSOC);
                    $organizerId = $organizer ? (int)$organizer['id'] : null;
                } catch (Exception $e) {
                    // If organizer_profiles doesn't exist or error occurs, just set to null
                    $organizerId = null;
                }
            }
            
            http_response_code(200);
            echo json_encode([
                "success" => true,
                "message" => "Login successful",
                "data" => [
                    "token" => $token,
                    "expires_at" => $expiresAt,
                    "user" => [
                        "id" => $userId,
                        "name" => $row['name'],
                        "email" => $row['email'],
                        "phone" => $row['phone'] ?? null,
                        "user_type" => $row['role'],
                        "role" => $row['role'],  // Including both for compatibility
                        "organizer_id" => $organizerId
                    ]
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode([
                "success" => false,
                "message" => "Invalid password",
                "data" => null
            ]);
        }
    } else {
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "User not found",
            "data" => null
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Incomplete data. Email and password required.",
        "data" => null
    ]);
}
?>