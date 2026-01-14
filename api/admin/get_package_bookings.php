<?php
/*************************************************
 * OUTPUT BUFFERING — CRITICAL FIX
 *************************************************/
ob_start();

/*************************************************
 * HEADERS
 *************************************************/
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

/*************************************************
 * INCLUDES
 *************************************************/
require_once __DIR__ . '/../config/database.php';

/*************************************************
 * HANDLE OPTIONS REQUEST
 *************************************************/
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/*************************************************
 * DB INIT
 *************************************************/
$database = new Database();
$db = $database->getConnection();

/*************************************************
 * GET PARAMETERS
 *************************************************/
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

/*************************************************
 * VALIDATION
 *************************************************/
if ($userId <= 0) {
    http_response_code(400);
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Invalid user_id'
    ]);
    exit;
}

try {
    /*************************************************
     * QUERY FOR PACKAGE BOOKINGS
     *************************************************/
    $query = "
        SELECT 
            b.id,
            b.booking_reference,
            b.booking_type,
            b.package_name,
            b.vendor_name,
            b.event_date,
            b.event_time,
            b.venue,
            b.guest_count,
            b.total_amount,
            b.status,
            b.created_at,
            p.name as package_full_name
        FROM bookings b
        LEFT JOIN packages p ON b.package_id = p.id
        WHERE b.client_id = :user_id
          AND (b.booking_type = 'package' OR b.booking_reference LIKE 'PKG-%')
        ORDER BY b.created_at DESC
    ";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Package bookings retrieved successfully',
        'bookings' => $bookings
    ]);
    exit;

} catch (PDOException $e) {
    error_log("Database error in get_package_bookings.php: " . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit;
} catch (Exception $e) {
    error_log("Error in get_package_bookings.php: " . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
    exit;
}
?>
