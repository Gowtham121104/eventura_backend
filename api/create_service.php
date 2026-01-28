<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'db_connection.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit();
}

try {
    // Get authorization header
    $headers = getallheaders();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    
    if (empty($authHeader)) {
        throw new Exception('Authorization header missing');
    }
    
    // Extract token
    $token = str_replace('Bearer ', '', $authHeader);
    
    // Verify token and get organizer
    $stmt = $conn->prepare("
        SELECT id, role 
        FROM users 
        WHERE token = ? 
        AND token_expiry > NOW() 
        AND role = 'organizer'
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Invalid token or unauthorized access');
    }
    
    $user = $result->fetch_assoc();
    $organizerId = $user['id'];
    
    // Get request body
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($data['name']) || empty($data['description']) || 
        empty($data['category']) || !isset($data['base_price'])) {
        throw new Exception('Missing required fields: name, description, category, base_price');
    }
    
    $name = trim($data['name']);
    $description = trim($data['description']);
    $category = strtoupper(trim($data['category']));
    $basePrice = (float)$data['base_price'];
    $features = isset($data['features']) && is_array($data['features']) ? $data['features'] : [];
    $imageUrl = isset($data['image_url']) ? trim($data['image_url']) : null;
    
    // Validate category
    $validCategories = ['CATERING', 'DECORATION', 'DJ', 'PHOTOGRAPHY', 'VIDEOGRAPHY', 'VENUE', 'ENTERTAINMENT'];
    if (!in_array($category, $validCategories)) {
        throw new Exception('Invalid category. Must be one of: ' . implode(', ', $validCategories));
    }
    
    // Validate price
    if ($basePrice < 0) {
        throw new Exception('Price cannot be negative');
    }
    
    // Convert features to JSON
    $featuresJson = json_encode($features);
    
    // Insert service
    $stmt = $conn->prepare("
        INSERT INTO services (
            organizer_id, 
            name, 
            description, 
            category, 
            base_price, 
            features,
            image_url,
            is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 1)
    ");
    
    $stmt->bind_param(
        "ssssdss",
        $organizerId,
        $name,
        $description,
        $category,
        $basePrice,
        $featuresJson,
        $imageUrl
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create service: ' . $stmt->error);
    }
    
    $serviceId = $conn->insert_id;
    
    // Fetch the created service
    $stmt = $conn->prepare("
        SELECT 
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
            0 as total_bookings,
            0 as avg_rating,
            0 as review_count
        FROM services s
        WHERE s.id = ?
    ");
    
    $stmt->bind_param("i", $serviceId);
    $stmt->execute();
    $result = $stmt->get_result();
    $service = $result->fetch_assoc();
    
    // Parse features
    $serviceFeaturesArray = [];
    if (!empty($service['features'])) {
        $decodedFeatures = json_decode($service['features'], true);
        $serviceFeaturesArray = is_array($decodedFeatures) ? $decodedFeatures : [];
    }
    
    // Format response
    $serviceData = [
        'id' => (int)$service['id'],
        'name' => $service['name'],
        'description' => $service['description'],
        'category' => $service['category'],
        'basePrice' => (float)$service['base_price'],
        'imageUrl' => $service['image_url'],
        'isActive' => (bool)$service['is_active'],
        'features' => $serviceFeaturesArray,
        'totalBookings' => 0,
        'avgRating' => 0.0,
        'reviewCount' => 0,
        'createdAt' => $service['created_at'],
        'updatedAt' => $service['updated_at']
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Service created successfully',
        'data' => $serviceData
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => null
    ]);
}

$conn->close();
?>