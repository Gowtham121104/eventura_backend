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

    // Get statistics
    $stats = [
        'total_events' => 0,
        'active_bookings' => 0,
        'pending_bookings' => 0,
        'total_revenue' => 0,
        'total_services' => 0,
        'month_revenue' => 0,
        'month_bookings' => 0,
        'completed_bookings' => 0
    ];

    // Count packages
    $stmt = $db->query("SELECT COUNT(*) as total FROM packages");
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['total_events'] = (int)$row['total'];
    }

    // Count active bookings
    $stmt = $db->query("SELECT COUNT(*) as total FROM bookings WHERE status IN ('PENDING', 'CONFIRMED')");
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['active_bookings'] = (int)$row['total'];
    }

    // Count pending bookings
    $stmt = $db->query("SELECT COUNT(*) as total FROM bookings WHERE status = 'PENDING'");
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['pending_bookings'] = (int)$row['total'];
    }

    // Calculate revenue
    $stmt = $db->query("SELECT SUM(COALESCE(modified_price, total_amount)) as total FROM bookings WHERE status IN ('CONFIRMED', 'COMPLETED')");
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['total_revenue'] = (float)($row['total'] ?? 0);
    }

    // Count services
    $stmt = $db->query("SELECT COUNT(*) as total FROM services");
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['total_services'] = (int)$row['total'];
    }

    // This month's revenue
    $stmt = $db->query("SELECT SUM(COALESCE(modified_price, total_amount)) as total FROM bookings WHERE status IN ('CONFIRMED', 'COMPLETED') AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['month_revenue'] = (float)($row['total'] ?? 0);
    }

    // This month's bookings
    $stmt = $db->query("SELECT COUNT(*) as total FROM bookings WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['month_bookings'] = (int)$row['total'];
    }

    // Completed bookings
    $stmt = $db->query("SELECT COUNT(*) as total FROM bookings WHERE status = 'COMPLETED'");
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats['completed_bookings'] = (int)$row['total'];
    }

    echo json_encode([
        'success' => true,
        'message' => 'Statistics retrieved successfully',
        'data' => $stats
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>