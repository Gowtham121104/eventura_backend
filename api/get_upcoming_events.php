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

// Get user ID from token
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

    // Get upcoming events (PENDING + CONFIRMED, event_date >= today)
    $query = "SELECT 
        b.id,
        b.booking_reference,
        b.event_name,
        b.event_date,
        b.event_time,
        b.venue,
        b.guest_count,
        b.status,
        b.package_name,
        b.total_amount,
        b.modified_price
    FROM bookings b
    WHERE b.client_id = :user_id 
    AND b.status IN ('PENDING', 'CONFIRMED') 
    AND b.event_date >= CURDATE()
    ORDER BY b.event_date ASC, b.event_time ASC
    LIMIT 10";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $events = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $events[] = [
            'id' => (int)$row['id'],
            'booking_reference' => $row['booking_reference'],
            'event_name' => $row['event_name'],
            'date' => $row['event_date'],
            'time' => $row['event_time'],
            'venue' => $row['venue'],
            'guest_count' => (int)$row['guest_count'],
            'status' => $row['status'],
            'package_name' => $row['package_name'],
            'total_amount' => (float)($row['total_amount'] ?? 0),
            'final_price' => $row['modified_price'] ? (float)$row['modified_price'] : (float)($row['total_amount'] ?? 0)
        ];
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $events
    ]);
    exit;

} catch (PDOException $e) {
    error_log("Database error in get_upcoming_events.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
    exit;
}
?>