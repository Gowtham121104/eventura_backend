<?php
ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

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

// ✅ GET USER ID FROM TOKEN
$headers = getallheaders();
if (empty($headers['Authorization'])) {
    http_response_code(401);
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Authorization header missing'
    ]);
    exit;
}

$token = str_replace('Bearer ', '', $headers['Authorization']);

try {
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
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired token'
        ]);
        exit;
    }

    $userId = $user['id']; // ✅ Get user ID from token

    // Get query parameters (optional filters)
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $perPage = isset($_GET['per_page']) ? min(100, max(1, intval($_GET['per_page']))) : 20;
    $offset = ($page - 1) * $perPage;

    // Build query
    $query = "SELECT 
        b.id,
        b.booking_reference,
        b.booking_type,
        b.client_id,
        b.vendor_id,
        b.service_id,
        b.service_name,
        b.vendor_name,
        b.package_id,
        b.event_type,
        b.event_name,
        b.event_date,
        b.event_time,
        b.duration,
        b.venue,
        b.guest_count,
        b.customer_name,
        b.customer_phone,
        b.customer_email,
        b.special_requirements,
        b.estimated_price,
        b.total_amount,
        b.modified_price,
        b.status,
        b.admin_remarks,
        b.rejection_reason,
        b.approved_by,
        b.approved_at,
        b.rejected_at,
        b.created_at,
        u.name as client_name,
        u.email as client_email,
        u.phone as client_phone,
        p.name as package_name
    FROM bookings b
    LEFT JOIN users u ON b.client_id = u.id
    LEFT JOIN packages p ON b.package_id = p.id
    WHERE b.client_id = :user_id";
    
    // Add status filter if provided
    if ($status !== null && in_array($status, ['PENDING', 'CONFIRMED', 'REJECTED', 'CANCELLED', 'COMPLETED'])) {
        $query .= " AND b.status = :status";
    }
    
    $query .= " ORDER BY b.created_at DESC LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    
    if ($status !== null && in_array($status, ['PENDING', 'CONFIRMED', 'REJECTED', 'CANCELLED', 'COMPLETED'])) {
        $stmt->bindParam(':status', $status, PDO::PARAM_STR);
    }
    
    $stmt->bindParam(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    
    $bookings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $bookings[] = [
            'id' => (int)$row['id'],
            'booking_reference' => $row['booking_reference'],
            'booking_type' => $row['booking_type'],
            'client_id' => (int)$row['client_id'],
            'client_name' => $row['client_name'],
            'client_email' => $row['client_email'],
            'client_phone' => $row['client_phone'],
            'vendor_id' => $row['vendor_id'] ? (int)$row['vendor_id'] : null,
            'vendor_name' => $row['vendor_name'],
            'service_id' => $row['service_id'],
            'service_name' => $row['service_name'],
            'package_id' => $row['package_id'] ? (int)$row['package_id'] : null,
            'package_name' => $row['package_name'],
            'event_type' => $row['event_type'],
            'event_name' => $row['event_name'],
            'event_date' => $row['event_date'],
            'event_time' => $row['event_time'],
            'duration' => $row['duration'],
            'venue' => $row['venue'],
            'guest_count' => (int)$row['guest_count'],
            'customer_name' => $row['customer_name'],
            'customer_phone' => $row['customer_phone'],
            'customer_email' => $row['customer_email'],
            'special_requirements' => $row['special_requirements'],
            'estimated_price' => (float)$row['estimated_price'],
            'total_amount' => (float)$row['total_amount'],
            'modified_price' => $row['modified_price'] ? (float)$row['modified_price'] : null,
            'status' => $row['status'],
            'admin_remarks' => $row['admin_remarks'],
            'rejection_reason' => $row['rejection_reason'],
            'approved_by' => $row['approved_by'] ? (int)$row['approved_by'] : null,
            'approved_at' => $row['approved_at'],
            'rejected_at' => $row['rejected_at'],
            'created_at' => $row['created_at']
        ];
    }
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM bookings WHERE client_id = :user_id";
    if ($status !== null && in_array($status, ['PENDING', 'CONFIRMED', 'REJECTED', 'CANCELLED', 'COMPLETED'])) {
        $countQuery .= " AND status = :status";
    }
    
    $countStmt = $db->prepare($countQuery);
    $countStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    
    if ($status !== null && in_array($status, ['PENDING', 'CONFIRMED', 'REJECTED', 'CANCELLED', 'COMPLETED'])) {
        $countStmt->bindParam(':status', $status, PDO::PARAM_STR);
    }
    
    $countStmt->execute();
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($total / $perPage);
    
    http_response_code(200);
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Bookings retrieved successfully',
        'data' => $bookings,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => (int)$total,
            'total_pages' => (int)$totalPages
        ]
    ]);
    exit;
    
} catch (PDOException $e) {
    error_log("Database error in get_bookings.php: " . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit;
} catch (Exception $e) {
    error_log("Error in get_bookings.php: " . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
    exit;
}
?>
