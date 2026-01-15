<?php
error_reporting(0);
ini_set('display_errors', 0);

file_put_contents('debug_package.log', "Request received at: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
file_put_contents('debug_package.log', "Raw input: " . file_get_contents("php://input") . "\n", FILE_APPEND);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once '../config/database.php';
include_once '../models/PackageBooking.php';

$database = new Database();
$db = $database->getConnection();

// ✅ GET USER ID FROM TOKEN
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

// Validate token and get user
$userQuery = "SELECT u.id, u.role FROM users u 
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

$userId = $user['id']; // ✅ Authenticated user ID

$packageBooking = new PackageBooking($db);

$data = json_decode(file_get_contents("php://input"));

if (!$data) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid JSON payload"
    ]);
    exit;
}

$normalizedEventTime = null;

if (!empty($data->event_time)) {
    if (strpos($data->event_time, '.') !== false) {
        $parts = explode('.', $data->event_time);
        $normalizedEventTime = str_pad($parts[0], 2, '0', STR_PAD_LEFT) . ':00:00';
    } elseif (strlen($data->event_time) === 2) {
        $normalizedEventTime = $data->event_time . ':00:00';
    } else {
        $normalizedEventTime = $data->event_time;
    }
}

// ✅ VALIDATION (removed user_id check)
if (
    !empty($data->vendor_id) &&
    !empty($data->package_name) &&
    !empty($data->vendor_name) &&
    !empty($data->customer_name) &&
    !empty($data->customer_phone) &&
    !empty($data->customer_email) &&
    !empty($data->event_date) &&
    !empty($normalizedEventTime) &&
    !empty($data->venue) &&
    isset($data->guest_count)
) {

    // ✅ Use authenticated user ID
    // ✅ Use authenticated user ID as client_id
$packageBooking->client_id = $userId;  // ✅ The logged-in client
$packageBooking->user_id = !empty($data->user_id) ? (int)$data->user_id : $userId;  // ✅ Organizer/vendor
$packageBooking->vendor_id = (int)$data->vendor_id;

$packageBooking->package_name = $data->package_name;
$packageBooking->vendor_name = $data->vendor_name;
$packageBooking->event_type = $data->event_type ?? 'wedding';
$packageBooking->event_name = !empty($data->event_name)
    ? $data->event_name
    : $data->package_name;

$packageBooking->event_date = $data->event_date;
$packageBooking->event_time = $normalizedEventTime;
$packageBooking->duration = $data->duration ?? '4-8 hours';
$packageBooking->venue = $data->venue;
$packageBooking->guest_count = max(1, (int)($data->guest_count ?? 0));

$packageBooking->customer_name = $data->customer_name;
$packageBooking->customer_phone = $data->customer_phone;
$packageBooking->customer_email = $data->customer_email;
$packageBooking->alternate_phone = !empty($data->alternate_phone)
    ? $data->alternate_phone
    : $data->customer_phone;

$method = strtolower($data->preferred_contact_method ?? 'phone');
if (!in_array($method, ['phone', 'email', 'whatsapp'])) {
    $method = 'phone';
}

$packageBooking->preferred_contact_method = $method;
$packageBooking->special_requirements = $data->special_requirements ?? '';
$packageBooking->estimated_price = (float)($data->estimated_price ?? 0);

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