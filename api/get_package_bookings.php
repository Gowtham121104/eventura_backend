<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once '../config/database.php';
require_once '../models/PackageBooking.php';

$database = new Database();
$db = $database->getConnection();

$packageBooking = new PackageBooking($db);

// Get user_id from query parameter
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : 1; // Default to 1 for testing

// Fetch bookings for this user
$stmt = $packageBooking->getBookingsByUser($user_id);
$bookings = array();

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $booking = array(
        'id' => $row['id'],
        'booking_reference' => $row['booking_reference'],
        'package_name' => $row['package_name'],
        'vendor_name' => $row['vendor_name'],
        'event_date' => $row['event_date'],
        'event_time' => $row['event_time'],
        'venue' => $row['venue'],
        'guest_count' => $row['guest_count'],
        'total_amount' => $row['total_amount'],
        'advance_amount' => $row['advance_amount'],
        'status' => $row['status'],
        'created_at' => $row['created_at']
    );
    array_push($bookings, $booking);
}

echo json_encode(array(
    'success' => true,
    'bookings' => $bookings
));
?>