<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/token_auth.php';

// Verify authentication
$user = verifyToken();
if (!$user || $user['role'] !== 'organizer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Add this after verifyToken() to see your user_id
error_log("User ID: " . $user['user_id']);

$data = json_decode(file_get_contents("php://input"), true);

// Check for service_id
if (!isset($data['service_id'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Service ID required"]);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // ✅ First, check if the service exists and belongs to this organizer
    $checkStmt = $db->prepare("SELECT id, organizer_id FROM services WHERE id = :id");
    $checkStmt->execute([":id" => $data['service_id']]);
    $existingService = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingService) {
        echo json_encode([
            "success" => false,
            "message" => "Service not found with ID: " . $data['service_id']
        ]);
        exit;
    }

    // ✅ Check if organizer_id matches (if column exists)
    if (isset($existingService['organizer_id']) && $existingService['organizer_id'] != $user['user_id']) {
        echo json_encode([
            "success" => false,
            "message" => "You don't have permission to edit this service"
        ]);
        exit;
    }

    // ✅ Now update the service
    $features = isset($data['features']) ? json_encode($data['features']) : json_encode([]);

    // Check if organizer_id column exists in the UPDATE
    $updateQuery = "
        UPDATE services SET
            name = :name,
            description = :description,
            category = :category,
            base_price = :base_price,
            features = :features,
            updated_at = NOW()
        WHERE id = :id
    ";

    $stmt = $db->prepare($updateQuery);

    $executed = $stmt->execute([
        ":id" => $data['service_id'],
        ":name" => $data['name'],
        ":description" => $data['description'],
        ":category" => $data['category'],
        ":base_price" => $data['base_price'],
        ":features" => $features
    ]);

    if ($executed) {
        echo json_encode([
            "success" => true,
            "message" => "Service updated successfully"
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Failed to update service",
            "debug" => $stmt->errorInfo()
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>