<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/database.php';

if (!isset($_GET['service_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'service_id is required'
    ]);
    exit;
}

$serviceId = (int) $_GET['service_id'];

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("
        SELECT
            id,
            service_name,
            category,
            vendor_name,
            description,
            base_price,
            price_unit,
            rating,
            review_count,
            service_image,
            is_available,
            created_at,
            updated_at
        FROM services
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->bindValue(':id', $serviceId, PDO::PARAM_INT);
    $stmt->execute();

    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$service) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Service not found'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => $service
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
