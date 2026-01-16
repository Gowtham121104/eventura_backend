<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

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
    // Validate token and get user
    $userQuery = "SELECT u.id FROM users u 
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

    $userId = $user['id'];

    // Get total bookings count
    $totalQuery = "SELECT COUNT(*) as total FROM bookings WHERE client_id = :user_id";
    $totalStmt = $db->prepare($totalQuery);
    $totalStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $totalStmt->execute();
    $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get upcoming bookings count (PENDING + CONFIRMED, event_date >= today)
    $upcomingQuery = "SELECT COUNT(*) as upcoming 
                      FROM bookings 
                      WHERE client_id = :user_id 
                      AND status IN ('PENDING', 'CONFIRMED') 
                      AND event_date >= CURDATE()";
    $upcomingStmt = $db->prepare($upcomingQuery);
    $upcomingStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $upcomingStmt->execute();
    $upcoming = $upcomingStmt->fetch(PDO::FETCH_ASSOC)['upcoming'];

    // Get completed bookings count
    $completedQuery = "SELECT COUNT(*) as completed 
                       FROM bookings 
                       WHERE client_id = :user_id 
                       AND status = 'COMPLETED'";
    $completedStmt = $db->prepare($completedQuery);
    $completedStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $completedStmt->execute();
    $completed = $completedStmt->fetch(PDO::FETCH_ASSOC)['completed'];

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'total_events' => (int)$total,
            'upcoming' => (int)$upcoming,
            'completed' => (int)$completed
        ]
    ]);
    exit;

} catch (PDOException $e) {
    error_log("Database error in get_booking_stats.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
    exit;
}
?>