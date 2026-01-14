<?php
require_once __DIR__ . '/../config/database.php';

function validateAdminOrOrganizerToken() {

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
    $db = $database->getConnection();

    // Updated query - check which column name exists in your users table
    // Try 'type' instead of 'user_type' or 'role' instead of 'user_type'
    $query = "
    SELECT 
        u.id AS user_id,
        u.role AS user_type,
        op.id AS organizer_id
        FROM user_tokens t
        INNER JOIN users u ON u.id = t.user_id
        LEFT JOIN organizer_profiles op ON op.user_id = u.id
        WHERE t.token = :token
        AND t.expires_at > NOW()
    ";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':token', $token);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !in_array($user['user_type'], ['admin', 'organizer'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired token'
        ]);
        exit;
    }

    return $user;
}
?>