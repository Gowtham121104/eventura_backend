<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../config/database.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id'])) {
    echo json_encode(["success" => false, "message" => "Service ID required"]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("
        UPDATE services SET
            service_name = :service_name,
            vendor_name = :vendor_name,
            description = :description,
            base_price = :base_price,
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        ":id" => $data['id'],
        ":service_name" => $data['service_name'],
        ":vendor_name" => $data['vendor_name'],
        ":description" => $data['description'],
        ":base_price" => $data['base_price']
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Service updated successfully"
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
