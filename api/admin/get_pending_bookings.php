<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $status = $_GET['status'] ?? 'PENDING';
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? 20);
    $offset = ($page - 1) * $perPage;

    // Get bookings
    $query = "SELECT 
                b.id,
                b.booking_reference,
                b.booking_type,
                b.client_id,
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
                b.total_amount,
                b.modified_price,
                b.status,
                b.admin_remarks,
                b.rejection_reason,
                b.created_at,
                u.name as client_name,
                u.email as client_email,
                p.name as package_name,
                b.vendor_name
              FROM bookings b
              LEFT JOIN users u ON b.client_id = u.id
              LEFT JOIN packages p ON b.package_id = p.id
              WHERE b.status = :status
              ORDER BY b.created_at DESC 
              LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':status', $status, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE status = :status");
    $countStmt->bindValue(':status', $status, PDO::PARAM_STR);
    $countStmt->execute();
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    echo json_encode([
        'success' => true,
        'message' => 'Bookings retrieved successfully',
        'data' => $bookings,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => (int)$total,
            'total_pages' => ceil($total / $perPage)
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
