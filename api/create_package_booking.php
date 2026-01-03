<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
file_put_contents('debug_package.log', "Request received at: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
file_put_contents('debug_package.log', "Raw input: " . file_get_contents("php://input") . "\n", FILE_APPEND);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';
include_once '../models/PackageBooking.php';

$database = new Database();
$db = $database->getConnection();

$packageBooking = new PackageBooking($db);

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
    !empty($data->package_name) &&
    !empty($data->vendor_name) &&
    !empty($data->customer_name) &&
    !empty($data->customer_phone) &&
    !empty($data->customer_email) &&
    !empty($data->event_date) &&
    !empty($data->event_time) &&
    !empty($data->venue) &&
    isset($data->guest_count)
) {

    // Set booking properties
    $packageBooking->user_id = (int)$data->user_id;
    $packageBooking->vendor_id = (int)$data->vendor_id;
    $packageBooking->package_id = $data->package_id ?? null;
    $packageBooking->package_name = $data->package_name;
    $packageBooking->vendor_name = $data->vendor_name;
    $packageBooking->event_type = $data->event_type ?? 'wedding';
    $packageBooking->event_name = $data->event_name ?? null;
    $packageBooking->event_date = $data->event_date;
    $packageBooking->event_time = $data->event_time;
    $packageBooking->duration = $data->duration ?? '4-8 hours';
    $packageBooking->venue = $data->venue;
    $packageBooking->guest_count = $data->guest_count ?? 0;
    $packageBooking->customer_name = $data->customer_name;
    $packageBooking->customer_phone = $data->customer_phone;
    $packageBooking->customer_email = $data->customer_email;
    $packageBooking->alternate_phone = $data->alternate_phone ?? null;
    $packageBooking->preferred_contact_method = $data->preferred_contact_method ?? 'phone';
    $packageBooking->special_requirements = $data->special_requirements ?? null;
    $packageBooking->estimated_price = $data->estimated_price ?? 0;

    // Create booking
    $booking_ref = $packageBooking->create();

    if ($booking_ref) {
        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => "Package booking created successfully",
            "booking_reference" => $booking_ref,
            "data" => [
                "id" => $packageBooking->id,
                "booking_reference" => $booking_ref,
                "status" => "pending",
                "created_at" => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        http_response_code(503);
        echo json_encode([
            "success" => false,
            "message" => "Failed to create package booking. Check server logs."
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