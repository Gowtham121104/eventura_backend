<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get active offers with package details
    $query = "SELECT 
        o.id,
        o.title,
        o.description,
        o.discount_percentage,
        o.valid_from,
        o.valid_until,
        o.image_url,
        p.id as package_id,
        p.name as package_name,
        p.price as package_price,
        p.image_url as package_image
    FROM special_offers o
    LEFT JOIN packages p ON o.package_id = p.id
    WHERE o.is_active = TRUE 
    AND o.valid_until >= CURDATE()
    ORDER BY o.created_at DESC
    LIMIT 5";

    $stmt = $db->prepare($query);
    $stmt->execute();

    $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $offers
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>