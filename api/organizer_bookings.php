<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$organizer_id = isset($_GET['organizer_id']) ? $_GET['organizer_id'] : die(json_encode(["message" => "Organizer ID required"]));

$query = "SELECT b.*, e.title as eventTitle, u.name as userName, u.email as userEmail, u.phone as userPhone
          FROM bookings b
          INNER JOIN events e ON b.event_id = e.id
          INNER JOIN users u ON b.user_id = u.id
          WHERE e.organizer_id = :organizer_id
          ORDER BY b.booking_date DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(":organizer_id", $organizer_id);
$stmt->execute();

$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($bookings);
?>