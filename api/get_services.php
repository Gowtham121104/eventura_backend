<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/database.php';

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
        WHERE is_available = 1
        ORDER BY category, vendor_name

    ");

    $stmt->execute();
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => $services
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
