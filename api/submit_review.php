<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';
require_once '../models/Review.php';

// 🔒 Disable PHP warnings leaking into JSON
error_reporting(0);
ini_set('display_errors', 0);

// Read raw input
$rawInput = file_get_contents("php://input");

if (!$rawInput) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Empty request body"
    ]);
    exit;
}

// Decode JSON
$data = json_decode($rawInput);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid JSON input",
        "error" => json_last_error_msg()
    ]);
    exit;
}

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed"
    ]);
    exit;
}

$review = new Review($db);

// Validate required fields
if (
    empty($data->booking_type) ||
    empty($data->booking_id) ||
    empty($data->user_id) ||
    !isset($data->rating)
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Missing required fields"
    ]);
    exit;
}

// Assign values
$review->booking_type = $data->booking_type;
$review->booking_id   = (int)$data->booking_id;
$review->user_id      = (int)$data->user_id;
$review->vendor_id    = $data->vendor_id ?? null;
$review->rating       = (int)$data->rating;
$review->comment      = $data->comment ?? null;

// Try insert
try {
    if ($review->create()) {
        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => "Review submitted successfully"
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Failed to insert review"
        ]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Server exception",
        "error" => $e->getMessage()
    ]);
}
