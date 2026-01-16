<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);  // Don't display errors in response
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

// Get booking ID from URL
$bookingId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($bookingId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid booking ID'
    ]);
    exit;
}

try {
    $query = "SELECT 
        b.*,
        u.name as client_name,
        u.email as client_email,
        u.phone as client_phone,
        p.name as package_name
    FROM bookings b
    LEFT JOIN users u ON b.client_id = u.id
    LEFT JOIN packages p ON b.package_id = p.id
    WHERE b.id = :booking_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
    $stmt->execute();
    
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($booking) {
        // Format the booking data to match Android model
        $formattedBooking = [
            'id' => (int)$booking['id'],
            'booking_reference' => $booking['booking_reference'],
            'booking_type' => $booking['booking_type'] ?? 'Package',
            'client_id' => (int)$booking['client_id'],
            'client_name' => $booking['client_name'],
            'client_email' => $booking['client_email'],
            'client_phone' => $booking['client_phone'],
            'vendor_id' => $booking['vendor_id'] ? (int)$booking['vendor_id'] : null,
            'vendor_name' => $booking['vendor_name'],
            'service_id' => $booking['service_id'],
            'service_name' => $booking['service_name'],
            'package_id' => $booking['package_id'] ? (int)$booking['package_id'] : null,
            'package_name' => $booking['package_name'],
            'event_type' => $booking['event_type'],
            'event_name' => $booking['event_name'],
            'event_date' => $booking['event_date'],
            'event_time' => $booking['event_time'],
            'duration' => $booking['duration'],
            'venue' => $booking['venue'],
            'guest_count' => (int)$booking['guest_count'],
            'customer_name' => $booking['customer_name'],
            'customer_phone' => $booking['customer_phone'],
            'customer_email' => $booking['customer_email'],
            'total_amount' => (float)($booking['total_amount'] ?? $booking['estimated_price'] ?? 0),
            'modified_price' => $booking['modified_price'] ? (float)$booking['modified_price'] : null,
            'status' => $booking['status'],
            'admin_remarks' => $booking['admin_remarks'],
            'rejection_reason' => $booking['rejection_reason'],
            'special_requirements' => $booking['special_requirements'],
            'approved_by' => $booking['approved_by'] ? (int)$booking['approved_by'] : null,
            'approved_at' => $booking['approved_at'],
            'rejected_at' => $booking['rejected_at'],
            'assigned_organizer_id' => $booking['assigned_organizer_id'] ? (int)$booking['assigned_organizer_id'] : null,
            'created_at' => $booking['created_at']
        ];

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $formattedBooking
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Booking not found'
        ]);
    }
    exit;  // ✅ CRITICAL: Stop execution after sending response
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
    exit;
}
?>