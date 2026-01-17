<?php
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

file_put_contents(
    __DIR__ . '/debug.log',
    "=== NEW REQUEST ===" . PHP_EOL .
    "Request received at: " . date('Y-m-d H:i:s') . PHP_EOL,
    FILE_APPEND
);

$rawInput = file_get_contents("php://input");
file_put_contents(
    __DIR__ . '/debug.log',
    "Raw input: " . $rawInput . PHP_EOL,
    FILE_APPEND
);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

// ✅ IMPROVED TOKEN EXTRACTION - handles different server configurations
function getBearerToken() {
    $headers = null;
    
    // Try different methods to get headers
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    } elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
    } else {
        // Fallback for nginx/other servers
        $headers = array();
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
    }
    
    // Log all headers for debugging
    file_put_contents(
        __DIR__ . '/debug.log',
        "Headers: " . json_encode($headers) . PHP_EOL,
        FILE_APPEND
    );
    
    // Check Authorization header (case-insensitive)
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    } elseif (isset($headers['authorization'])) {
        $authHeader = $headers['authorization'];
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    } else {
        return null;
    }
    
    // Extract token
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        return $matches[1];
    }
    
    return str_replace('Bearer ', '', $authHeader);
}

$token = getBearerToken();

file_put_contents(
    __DIR__ . '/debug.log',
    "Extracted token: " . ($token ? "EXISTS (length: " . strlen($token) . ")" : "NULL") . PHP_EOL,
    FILE_APPEND
);

if (empty($token)) {
    file_put_contents(
        __DIR__ . '/debug.log',
        "❌ Authorization header missing" . PHP_EOL,
        FILE_APPEND
    );
    http_response_code(401);
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Authorization header missing'
    ]);
    exit;
}

// Validate token and get user
$userQuery = "SELECT u.id, u.role FROM users u 
              INNER JOIN user_tokens t ON u.id = t.user_id 
              WHERE t.token = :token AND t.expires_at > NOW()";
$userStmt = $db->prepare($userQuery);
$userStmt->bindParam(':token', $token);
$userStmt->execute();
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

file_put_contents(
    __DIR__ . '/debug.log',
    "User found: " . ($user ? "YES (ID: {$user['id']}, Role: {$user['role']})" : "NO") . PHP_EOL,
    FILE_APPEND
);

if (!$user) {
    file_put_contents(
        __DIR__ . '/debug.log',
        "❌ Invalid or expired token" . PHP_EOL,
        FILE_APPEND
    );
    http_response_code(401);
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Invalid or expired token'
    ]);
    exit;
}

$userId = $user['id']; // ✅ Authenticated user ID

file_put_contents(
    __DIR__ . '/debug.log',
    "✅ Authenticated user ID: " . $userId . PHP_EOL,
    FILE_APPEND
);

$data = json_decode($rawInput);

if (!$data) {
    http_response_code(400);
    ob_clean();
    echo json_encode([
        "success" => false,
        "message" => "Invalid JSON payload"
    ]);
    exit;
}

// ✅ VALIDATION (removed user_id check since we get it from token)
if (
    !empty($data->customer_name) &&
    !empty($data->customer_phone) &&
    !empty($data->customer_email) &&
    !empty($data->event_date) &&
    !empty($data->event_time) &&
    !empty($data->venue)
) {

    try {
        $bookingType = $data->booking_type ?? 'service';
        $validTypes = ['event', 'service', 'package'];
        if (!in_array($bookingType, $validTypes)) {
            throw new Exception('Invalid booking_type. Must be: event, service, or package');
        }

        file_put_contents(
            __DIR__ . '/debug.log',
            "Creating booking for user ID: " . $userId . PHP_EOL,
            FILE_APPEND
        );

        // Check for duplicate pending bookings
        $duplicateCheck = "SELECT id FROM bookings 
                           WHERE client_id = :user_id 
                           AND booking_type = :booking_type
                           AND status = 'PENDING'
                           AND event_date = :event_date";
        
        $dupStmt = $db->prepare($duplicateCheck);
        $dupStmt->bindParam(':user_id', $userId); // ✅ Use token user ID
        $dupStmt->bindParam(':booking_type', $bookingType);
        $dupStmt->bindParam(':event_date', $data->event_date);
        $dupStmt->execute();

        if ($dupStmt->rowCount() > 0) {
            http_response_code(409);
            ob_clean();
            echo json_encode([
                'success' => false,
                'message' => 'You already have a pending booking for this date. Please wait for approval.'
            ]);
            exit;
        }

        $insertQuery = "INSERT INTO bookings (
            user_id,
            client_id,
            booking_type,
            vendor_id,
            service_id,
            service_name,
            vendor_name,
            package_id,
            package_name,
            event_type,
            event_name,
            event_date,
            event_time,
            duration,
            venue,
            guest_count,
            customer_name,
            customer_phone,
            customer_email,
            alternate_phone,
            preferred_contact_method,
            special_requirements,
            estimated_price,
            total_amount,
            status,
            booking_reference,
            created_at
        ) VALUES (
            :user_id,
            :client_id,
            :booking_type,
            :vendor_id,
            :service_id,
            :service_name,
            :vendor_name,
            :package_id,
            :package_name,
            :event_type,
            :event_name,
            :event_date,
            :event_time,
            :duration,
            :venue,
            :guest_count,
            :customer_name,
            :customer_phone,
            :customer_email,
            :alternate_phone,
            :preferred_contact_method,
            :special_requirements,
            :estimated_price,
            :total_amount,
            'PENDING',
            :booking_reference,
            NOW()
        )";

        $stmt = $db->prepare($insertQuery);
        $bookingReference = 'BKG-' . strtoupper(substr(md5(uniqid()), 0, 10));

        // ✅ Bind authenticated user ID
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':client_id', $userId);
        $stmt->bindParam(':booking_type', $bookingType);
        
        $vendorId = $data->vendor_id ?? null;
        $serviceId = $data->service_id ?? null;
        $serviceName = $data->service_name ?? null;
        $vendorName = $data->vendor_name ?? null;
        
        $stmt->bindParam(':vendor_id', $vendorId);
        $stmt->bindParam(':service_id', $serviceId);
        $stmt->bindParam(':service_name', $serviceName);
        $stmt->bindParam(':vendor_name', $vendorName);
        
        $packageId = $data->package_id ?? null;
        $packageName = $data->package_name ?? null;
        
        $stmt->bindParam(':package_id', $packageId);
        $stmt->bindParam(':package_name', $packageName);
        
        $eventType = $data->event_type ?? 'wedding';
        $eventName = $data->event_name ?? null;
        
        $stmt->bindParam(':event_type', $eventType);
        $stmt->bindParam(':event_name', $eventName);
        $stmt->bindParam(':event_date', $data->event_date);
        $stmt->bindParam(':event_time', $data->event_time);
        
        $duration = $data->duration ?? '4-8 hours';
        $stmt->bindParam(':duration', $duration);
        $stmt->bindParam(':venue', $data->venue);
        
        $guestCount = $data->guest_count ?? 0;
        $stmt->bindParam(':guest_count', $guestCount);
        
        $stmt->bindParam(':customer_name', $data->customer_name);
        $stmt->bindParam(':customer_phone', $data->customer_phone);
        $stmt->bindParam(':customer_email', $data->customer_email);
        
        $alternatePhone = $data->alternate_phone ?? null;
        $stmt->bindParam(':alternate_phone', $alternatePhone);
        
        $preferredContact = $data->preferred_contact_method ?? 'phone';
        $stmt->bindParam(':preferred_contact_method', $preferredContact);
        
        $specialRequirements = $data->special_requirements ?? null;
        $stmt->bindParam(':special_requirements', $specialRequirements);
        
        $estimatedPrice = $data->estimated_price ?? $data->total_amount ?? 0;
        $totalAmount = $data->total_amount ?? $estimatedPrice;
        
        $stmt->bindParam(':estimated_price', $estimatedPrice);
        $stmt->bindParam(':total_amount', $totalAmount);
        $stmt->bindParam(':booking_reference', $bookingReference);

        if ($stmt->execute()) {
            $bookingId = $db->lastInsertId();

            file_put_contents(
                __DIR__ . '/debug.log',
                "✅ Booking created with ID: " . $bookingId . PHP_EOL,
                FILE_APPEND
            );

            $historyQuery = "INSERT INTO booking_status_history (
                booking_id, old_status, new_status, changed_by, remarks
            ) VALUES (
                :booking_id, NULL, 'PENDING', :changed_by, 'Booking created by client'
            )";
            
            $historyStmt = $db->prepare($historyQuery);
            $historyStmt->bindParam(':booking_id', $bookingId);
            $historyStmt->bindParam(':changed_by', $userId); // ✅ Use token user ID
            $historyStmt->execute();

            try {
                $notificationQuery = "INSERT INTO notifications (
                    user_id, 
                    type, 
                    title, 
                    message, 
                    related_booking_id
                ) VALUES (
                    1, 
                    'booking_request', 
                    'New Booking Request', 
                    :message, 
                    :booking_id
                )";
                
                $notificationStmt = $db->prepare($notificationQuery);
                $notificationMessage = "New booking request from {$data->customer_name} for {$eventName} on {$data->event_date}";
                $notificationStmt->bindParam(':message', $notificationMessage);
                $notificationStmt->bindParam(':booking_id', $bookingId);
                $notificationStmt->execute();
            } catch (Exception $e) {
                error_log("Failed to create notification: " . $e->getMessage());
            }

            http_response_code(201);
            ob_clean();
            echo json_encode([
                "success" => true,
                "message" => "Booking request submitted successfully. Waiting for admin approval.",
                "booking_reference" => $bookingReference,
                "data" => [
                    "id" => $bookingId,
                    "booking_reference" => $bookingReference,
                    "status" => "PENDING",
                    "created_at" => date('Y-m-d H:i:s')
                ]
            ]);
            exit;
        } else {
            throw new Exception('Failed to create booking');
        }

    } catch (PDOException $e) {
        error_log("Database error in create_booking.php: " . $e->getMessage());
        file_put_contents(
            __DIR__ . '/debug.log',
            "❌ Database error: " . $e->getMessage() . PHP_EOL,
            FILE_APPEND
        );
        http_response_code(500);
        ob_clean();
        echo json_encode([
            "success" => false,
            "message" => "Database error: " . $e->getMessage()
        ]);
        exit;
    } catch (Exception $e) {
        file_put_contents(
            __DIR__ . '/debug.log',
            "❌ Exception: " . $e->getMessage() . PHP_EOL,
            FILE_APPEND
        );
        http_response_code(400);
        ob_clean();
        echo json_encode([
            "success" => false,
            "message" => $e->getMessage()
        ]);
        exit;
    }
}

http_response_code(400);
ob_clean();
echo json_encode([
    "success" => false,
    "message" => "Incomplete data. Required fields missing."
]);
exit;