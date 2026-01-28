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
require_once '../auth/token_auth.php';

$user = verifyToken();
if (!$user || $user['role'] !== 'organizer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// ✅ FIXED: Changed from 'id' to 'service_id'
$serviceId = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;

if ($serviceId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid service ID']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT * FROM services WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $serviceId);
    $stmt->execute();

    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$service) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Service not found']);
        exit();
    }

    $features = json_decode($service['features'] ?? '[]', true);

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => (int)$service['id'],
            'name' => $service['name'],
            'description' => $service['description'],
            'category' => $service['category'],
            'basePrice' => (float)$service['base_price'],
            'imageUrl' => $service['image_url'],
            'isActive' => (bool)$service['is_active'],
            'features' => $features,
            'totalBookings' => 0,
            'avgRating' => 0.0,
            'reviewCount' => 0,
            'createdAt' => $service['created_at'],
            'updatedAt' => $service['updated_at']
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>