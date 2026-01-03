<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
file_put_contents('debug.log', "Request received at: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
file_put_contents('debug.log', "Raw input: " . file_get_contents("php://input") . "\n", FILE_APPEND);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';   // ✅ CORRECT
include_once '../models/Booking.php';    // ✅ CORRECT
           

$database = new Database();
$db = $database->getConnection();

$booking = new Booking($db);

// Read JSON input
$data = json_decode(file_get_contents("php://input"));

// Check if JSON is valid
if (!$data) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid JSON payload"
    ]);
    exit;
}

// Validate required fields
if (
    !empty($data->user_id) &&
    !empty($data->vendor_id) &&
    !empty($data->service_name) &&
    !empty($data->vendor_name) &&
    !empty($data->customer_name) &&
    !empty($data->customer_phone) &&
    !empty($data->customer_email) &&
    !empty($data->event_date) &&
    !empty($data->event_time) &&
    !empty($data->venue) &&
    isset($data->guest_count)   // ✅ FIXED (was !empty)
) {

    // Set booking properties
    $booking->user_id = (int)$data->user_id;
    $booking->vendor_id = (int)$data->vendor_id;
    $booking->service_id = $data->service_id ?? null;
    $booking->service_name = $data->service_name;
    $booking->vendor_name = $data->vendor_name;
    $booking->event_type = $data->event_type ?? 'wedding';
    $booking->event_name = $data->event_name ?? null;
    $booking->event_date = $data->event_date;
    $booking->event_time = $data->event_time;
    $booking->duration = $data->duration ?? '4-8 hours';
    $booking->venue = $data->venue;

    // ✅ SAFE DEFAULT
    $booking->guest_count = $data->guest_count ?? 0;

    $booking->customer_name = $data->customer_name;
    $booking->customer_phone = $data->customer_phone;
    $booking->customer_email = $data->customer_email;
    $booking->alternate_phone = $data->alternate_phone ?? null;
    $booking->preferred_contact_method = $data->preferred_contact_method ?? 'phone';
    $booking->special_requirements = $data->special_requirements ?? null;
    $booking->estimated_price = $data->estimated_price ?? 0;

    // Create booking
    $booking_ref = $booking->create();

    if ($booking_ref) {
        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => "Booking created successfully",
            "booking_reference" => $booking_ref,
            "data" => [
                "id" => $booking->id,
                "booking_reference" => $booking_ref,
                "status" => "pending",
                "created_at" => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        http_response_code(503);
        echo json_encode([
            "success" => false,
            "message" => "Failed to create booking. Check server logs."
        ]);
    }

} else {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Incomplete data. Required fields missing."
    ]);
}
?>
