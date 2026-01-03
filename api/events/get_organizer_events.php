<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get organizer_id from query parameter
$organizer_id = isset($_GET['organizer_id']) ? $_GET['organizer_id'] : null;

if(!$organizer_id) {
    http_response_code(400);
    echo json_encode(array("message" => "Organizer ID required"));
    exit();
}

try {
    $query = "SELECT 
                e.id,
                e.title,
                e.description,
                e.event_date,
                e.start_time,
                e.end_time,
                e.location,
                e.venue,
                e.category,
                e.price,
                e.max_attendees,
                e.image_url,
                e.status,
                e.created_at,
                COUNT(DISTINCT b.id) as total_bookings,
                COALESCE(SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END), 0) as confirmed_bookings,
                COALESCE(SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END), 0) as pending_bookings
              FROM events e
              LEFT JOIN bookings b ON e.id = b.event_id
              WHERE e.organizer_id = :organizer_id
              GROUP BY e.id
              ORDER BY e.event_date DESC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':organizer_id', $organizer_id);
    $stmt->execute();
    
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    http_response_code(200);
    echo json_encode(array(
        "success" => true,
        "data" => $events
    ));
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ));
}
?>