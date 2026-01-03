<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$organizer_id = isset($_GET['organizer_id']) ? $_GET['organizer_id'] : null;

if(!$organizer_id) {
    http_response_code(400);
    echo json_encode(array("message" => "Organizer ID required"));
    exit();
}

try {
    // Total events
    $query = "SELECT COUNT(*) as total_events FROM events WHERE organizer_id = :organizer_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':organizer_id', $organizer_id);
    $stmt->execute();
    $total_events = $stmt->fetch(PDO::FETCH_ASSOC)['total_events'];
    
    // Total bookings
    $query = "SELECT COUNT(DISTINCT b.id) as total_bookings 
              FROM bookings b
              INNER JOIN events e ON b.event_id = e.id
              WHERE e.organizer_id = :organizer_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':organizer_id', $organizer_id);
    $stmt->execute();
    $total_bookings = $stmt->fetch(PDO::FETCH_ASSOC)['total_bookings'];
    
    // Total revenue
    $query = "SELECT COALESCE(SUM(b.total_amount), 0) as total_revenue 
              FROM bookings b
              INNER JOIN events e ON b.event_id = e.id
              WHERE e.organizer_id = :organizer_id AND b.status = 'confirmed'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':organizer_id', $organizer_id);
    $stmt->execute();
    $total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'];
    
    // Upcoming events
    $query = "SELECT COUNT(*) as upcoming_events 
              FROM events 
              WHERE organizer_id = :organizer_id 
              AND event_date >= CURDATE()
              AND status = 'upcoming'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':organizer_id', $organizer_id);
    $stmt->execute();
    $upcoming_events = $stmt->fetch(PDO::FETCH_ASSOC)['upcoming_events'];
    
    // Monthly revenue trend (last 6 months)
    $query = "SELECT 
                DATE_FORMAT(b.booking_date, '%Y-%m') as month,
                COALESCE(SUM(b.total_amount), 0) as revenue
              FROM bookings b
              INNER JOIN events e ON b.event_id = e.id
              WHERE e.organizer_id = :organizer_id 
              AND b.status = 'confirmed'
              AND b.booking_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
              GROUP BY month
              ORDER BY month";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':organizer_id', $organizer_id);
    $stmt->execute();
    $monthly_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    http_response_code(200);
    echo json_encode(array(
        "success" => true,
        "data" => array(
            "total_events" => (int)$total_events,
            "total_bookings" => (int)$total_bookings,
            "total_revenue" => (float)$total_revenue,
            "upcoming_events" => (int)$upcoming_events,
            "monthly_revenue" => $monthly_revenue
        )
    ));
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(array(
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ));
}
?>