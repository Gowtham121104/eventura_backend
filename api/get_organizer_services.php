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

// Verify token and get user
$user = verifyToken();
if (!$user) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit();
}

$userId = $user['user_id'];

// Only allow organizers
if ($user['role'] !== 'organizer') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Only organizers can access this endpoint'
    ]);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get organizer_id from organizer_profiles
    $orgQuery = "SELECT id FROM organizer_profiles WHERE user_id = :user_id";
    $orgStmt = $db->prepare($orgQuery);
    $orgStmt->bindParam(':user_id', $userId);
    $orgStmt->execute();
    $organizer = $orgStmt->fetch(PDO::FETCH_ASSOC);

    if (!$organizer) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Organizer profile not found'
        ]);
        exit();
    }

    $organizerId = $organizer['id'];

    // Get all services for this organizer
    $query = "SELECT 
        s.id,
        s.name,
        s.description,
        s.category,
        s.base_price,
        s.image_url,
        s.is_active,
        s.features,
        s.created_at,
        s.updated_at,
        COUNT(DISTINCT b.id) as total_bookings,
        COALESCE(AVG(r.rating), 0) as avg_rating,
        COUNT(DISTINCT r.id) as review_count
    FROM services s
    LEFT JOIN bookings b ON s.id = b.service_id AND b.status IN ('CONFIRMED', 'COMPLETED')
    LEFT JOIN reviews r ON b.id = r.booking_id
    WHERE s.organizer_id = :organizer_id
    GROUP BY s.id
    ORDER BY s.created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':organizer_id', $organizerId);
    $stmt->execute();

    $services = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $features = [];
        if (!empty($row['features'])) {
            $decodedFeatures = json_decode($row['features'], true);
            $features = is_array($decodedFeatures) ? $decodedFeatures : [];
        }

        $services[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'category' => $row['category'],
            'basePrice' => (float)$row['base_price'],
            'imageUrl' => $row['image_url'],
            'isActive' => (bool)$row['is_active'],
            'features' => $features,
            'totalBookings' => (int)$row['total_bookings'],
            'avgRating' => round((float)$row['avg_rating'], 1),
            'reviewCount' => (int)$row['review_count'],
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at']
        ];
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $services,
        'total' => count($services)
    ]);

} catch (PDOException $e) {
    error_log("Database error in get_organizer_services.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>