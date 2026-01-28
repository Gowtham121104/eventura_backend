<?php
/**
 * Update Organizer Profile API
 * Endpoint: POST /api/update_organizer_profile.php
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
    // ✅ GET USER ID FROM TOKEN
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

    // ✅ FIXED: Use PDO syntax
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
        exit();
    }

    $userId = $user['id'];
    $userRole = $user['role'];
    
    // Verify user is an organizer
    if ($userRole !== 'organizer' && $userRole !== 'admin') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Access denied. Only organizers can update profile.'
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
    $businessName = trim($input['businessName'] ?? '');
    $email = trim($input['email'] ?? '');
    $phone = trim($input['phone'] ?? '');
    
    if (empty($businessName)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Business name is required'
        ]);
        exit();
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Valid email is required'
        ]);
        exit();
    }
    
    if (empty($phone)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Phone number is required'
        ]);
        exit();
    }
    
    // ✅ FIXED: Check if email is already taken using PDO
    $emailCheckQuery = "SELECT id FROM users WHERE email = :email AND id != :userId";
    $emailCheckStmt = $conn->prepare($emailCheckQuery);
    $emailCheckStmt->bindParam(':email', $email);
    $emailCheckStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $emailCheckStmt->execute();
    
    if ($emailCheckStmt->fetch()) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Email is already registered to another account'
        ]);
        exit();
    }
    
    // ✅ FIXED: Update only columns that exist in your database
    $updateQuery = "UPDATE users SET 
            name = :businessName,
            email = :email,
            phone = :phone
        WHERE id = :userId AND (role = 'organizer' OR role = 'admin')";
    
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bindParam(':businessName', $businessName);
    $updateStmt->bindParam(':email', $email);
    $updateStmt->bindParam(':phone', $phone);
    $updateStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    
    $updateResult = $updateStmt->execute();
    
    if (!$updateResult) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update profile'
        ]);
        exit();
    }
    
    if ($updateStmt->rowCount() === 0) {
        // Profile exists but no changes were made
        // This is still considered success
    }
    
    // ✅ FIXED: Fetch updated profile using PDO
    $fetchQuery = "SELECT 
            id,
            name as businessName,
            email,
            phone,
            profile_image as logoUrl,
            created_at as createdAt
        FROM users 
        WHERE id = :userId";
    
    $fetchStmt = $conn->prepare($fetchQuery);
    $fetchStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $fetchStmt->execute();
    $updatedUser = $fetchStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get ratings
    $ratingQuery = "SELECT 
            COALESCE(AVG(rating), 0) as averageRating,
            COUNT(*) as totalReviews
        FROM reviews 
        WHERE organizer_id = :userId";
    $ratingStmt = $conn->prepare($ratingQuery);
    $ratingStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $ratingStmt->execute();
    $ratingData = $ratingStmt->fetch(PDO::FETCH_ASSOC);
    
    // Format response
    $profileData = [
        'id' => (int)$updatedUser['id'],
        'businessName' => $updatedUser['businessName'] ?? '',
        'logoUrl' => $updatedUser['logoUrl'] ?? '',
        'description' => '', // Not in database
        'email' => $updatedUser['email'] ?? '',
        'phone' => $updatedUser['phone'] ?? '',
        'website' => '', // Not in database
        'address' => '', // Not in database
        'city' => '', // Not in database
        'state' => '', // Not in database
        'country' => '', // Not in database
        'zipCode' => '', // Not in database
        'averageRating' => round((float)($ratingData['averageRating'] ?? 0), 2),
        'totalReviews' => (int)($ratingData['totalReviews'] ?? 0),
        'establishedYear' => null, // Not in database
        'teamSize' => null, // Not in database
        'coverImageUrl' => '', // Not in database
        'createdAt' => $updatedUser['createdAt'] ?? '',
        'updatedAt' => '' // Not in database
    ];
    
    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully',
        'data' => $profileData
    ]);
    
} catch (Exception $e) {
    error_log("Error in update_organizer_profile.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating profile',
        'error' => $e->getMessage()
    ]);
}
?>