<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$organizer_id = isset($_GET['organizer_id']) ? $_GET['organizer_id'] : die(json_encode(["message" => "Organizer ID required"]));

$query = "SELECT 
          COUNT(DISTINCT e.id) as totalEvents,
          COUNT(DISTINCT CASE WHEN e.status = 'upcoming' THEN e.id END) as upcomingEvents,
          COUNT(DISTINCT b.id) as totalBookings,
          COUNT(DISTINCT CASE WHEN b.status = 'confirmed' THEN b.id END) as activeBookings,
          COALESCE(SUM(CASE WHEN b.status = 'confirmed' THEN b.total_amount ELSE 0 END), 0) as totalRevenue,
          COALESCE(SUM(CASE WHEN b.status = 'pending' THEN b.total_amount ELSE 0 END), 0) as pendingPayments
          FROM events e
          LEFT JOIN bookings b ON e.id = b.event_id
          WHERE e.organizer_id = :organizer_id";

$stmt = $db->prepare($query);
$stmt->bindParam(":organizer_id", $organizer_id);
$stmt->execute();

$analytics = $stmt->fetch(PDO::FETCH_ASSOC);
echo json_encode($analytics);
?>