<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/../config/database.php';

$data = json_decode(file_get_contents("php://input"), true);

if (
    empty($data['name']) ||
    empty($data['event_type']) ||
    !isset($data['price'])
) {
    echo json_encode([
        "success" => false,
        "message" => "Required fields missing"
    ]);
    exit;
}

$database = new Database();
$db = $database->getConnection();

/**
 * IMPORTANT:
 * Requires UNIQUE(name, event_type) in DB
 */
$query = "
INSERT INTO packages (
    name,
    description,
    price,
    event_type,
    status,
    image,
    created_at,
    updated_at
) VALUES (
    :name,
    :description,
    :price,
    :event_type,
    :status,
    :image,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    price = VALUES(price),
    status = VALUES(status),
    image = VALUES(image),
    updated_at = NOW()
";

$stmt = $db->prepare($query);
$stmt->execute([
    ':name' => $data['name'],
    ':description' => $data['description'] ?? '',
    ':price' => $data['price'],
    ':event_type' => $data['event_type'],
    ':status' => $data['status'] ?? 'active',
    ':image' => $data['image'] ?? null
]);

echo json_encode([
    "success" => true,
    "message" => "Package saved successfully"
]);
