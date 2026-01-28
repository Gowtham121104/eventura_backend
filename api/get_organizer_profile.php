<?php
/**
 * Get Organizer Profile API
 * Endpoint: GET /api/get_organizer_profile.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use GET request.'
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
        exit();
    }

    $userId = $user['id'];
    $userRole = $user['role'];
    
    // Verify user is an organizer
    if ($userRole !== 'organizer' && $userRole !== 'admin') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Access denied. Only organizers can access this endpoint.'
        ]);
        exit();
    }
    
    // ✅ FIXED: Query only columns that exist in your database
    $query = "SELECT 
            id,
            name as businessName,
            email,
            phone,
            profile_image as logoUrl,
            created_at as createdAt
        FROM users 
        WHERE id = :userId AND (role = 'organizer' OR role = 'admin')";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $userProfile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userProfile) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Organizer profile not found.'
        ]);
        exit();
    }
    
    // Get average rating and total reviews
    $ratingQuery = "SELECT 
            COALESCE(AVG(rating), 0) as averageRating,
            COUNT(*) as totalReviews
        FROM reviews 
        WHERE organizer_id = :userId";
    $ratingStmt = $conn->prepare($ratingQuery);
    $ratingStmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $ratingStmt->execute();
    $ratingData = $ratingStmt->fetch(PDO::FETCH_ASSOC);
    
    // ✅ Format response with only available fields + empty defaults for missing ones
    $profileData = [
        'id' => (int)$userProfile['id'],
        'businessName' => $userProfile['businessName'] ?? '',
        'logoUrl' => $userProfile['logoUrl'] ?? '',
        'description' => '', // Not in database
        'email' => $userProfile['email'] ?? '',
        'phone' => $userProfile['phone'] ?? '',
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
        'createdAt' => $userProfile['createdAt'] ?? '',
        'updatedAt' => '' // Not in database
    ];
    
    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Profile fetched successfully',
        'data' => $profileData
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_organizer_profile.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching profile',
        'error' => $e->getMessage()
    ]);
}
?>