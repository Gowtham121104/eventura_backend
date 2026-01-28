<?php
/**
 * Change Password API for Organizers
 * Endpoint: POST /api/change_password_organizer.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST request.'
    ]);
    exit();
}

try {
    // Get authorization header
    $headers = getallheaders();
    if (empty($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Authorization header missing'
        ]);
        exit();
    }

    $token = str_replace('Bearer ', '', $headers['Authorization']);

    $database = new Database();
    $conn = $database->getConnection();

    // Validate token and get user
    $userQuery = "SELECT u.id, u.role, u.password FROM users u 
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
        exit();
    }

    $userId = $user['id'];
    $userRole = $user['role'];
    $currentHashedPassword = $user['password'];
    
    // Verify user is an organizer or admin
    if ($userRole !== 'organizer' && $userRole !== 'admin') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Access denied. Only organizers can change password.'
        ]);
        exit();
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON input'
        ]);
        exit();
    }
    
    // Validate required fields
    $currentPassword = trim($input['currentPassword'] ?? '');
    $newPassword = trim($input['newPassword'] ?? '');
    
    if (empty($currentPassword)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Current password is required'
        ]);
        exit();
    }
    
    if (empty($newPassword)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'New password is required'
        ]);
        exit();
    }
    
    if (strlen($newPassword) < 6) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'New password must be at least 6 characters long'
        ]);
        exit();
    }
    
    // Verify current password
    if (!password_verify($currentPassword, $currentHashedPassword)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Current password is incorrect'
        ]);
        exit();
    }
    
    // Check if new password is same as current
    if (password_verify($newPassword, $currentHashedPassword)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'New password must be different from current password'
        ]);
        exit();
    }
    
    // Hash the new password
    $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $updateQuery = "UPDATE users SET password = :password WHERE id = :userId";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bindParam(':password', $newHashedPassword);
    $updateStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    
    $updateResult = $updateStmt->execute();
    
    if (!$updateResult) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update password'
        ]);
        exit();
    }
    
    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Password changed successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Error in change_password_organizer.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while changing password',
        'error' => $e->getMessage()
    ]);
}
?>