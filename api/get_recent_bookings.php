<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../config/database.php';

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
    $db = $database->getConnection();

    // Validate token and get user
    $userQuery = "SELECT u.id, u.role FROM users u 
                  INNER JOIN user_tokens t ON u.id = t.user_id 
                  WHERE t.token = :token AND t.expires_at > NOW()";
    $userStmt = $db->prepare($userQuery);
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

    $query = "
        SELECT
            b.id,
            b.booking_reference,
            b.package_name,
            b.event_date,
            b.event_time,
            b.venue,
            b.status,
            b.estimated_price AS total_amount
        FROM bookings b
        WHERE b.client_id = :user_id
        ORDER BY
            CASE
                WHEN b.event_date >= CURDATE() THEN 0
                ELSE 1
            END,
            b.event_date DESC
        LIMIT 10
    ";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();

    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'bookings' => $bookings
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>