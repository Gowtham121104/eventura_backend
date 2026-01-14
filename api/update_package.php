<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/../config/database.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id'])) {
    echo json_encode([
        "success" => false,
        "message" => "Package ID is required"
    ]);
    exit;
}


try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "
    UPDATE packages
    SET
        name = :name,
        description = :description,
        price = :price,
        event_type = :event_type,
        status = :status,
        image = :image,
        updated_at = NOW()
    WHERE id = :id;

    ";


    $stmt = $db->prepare($query);

   $stmt->execute([
    ':id' => $data['id'],
    ':name' => $data['name'],
    ':description' => $data['description'],
    ':price' => $data['price'],
    ':event_type' => $data['event_type'],
    ':status' => $data['status'],
    ':image' => $data['image']
]);

if ($stmt->rowCount() === 0) {
    echo json_encode([
        "success" => false,
        "message" => "No rows updated. Invalid package ID or no changes detected."
    ]);
    exit;
}

echo json_encode([
    "success" => true,
    "message" => "Package updated successfully"
]);



} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
