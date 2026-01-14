<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/token_auth.php';  // ✅ ADD THIS

try {
    $database = new Database();
    $db = $database->getConnection();

    // ✅ FIXED: Get user ID from token instead of query parameter
    $headers = getallheaders();
    if (empty($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Authorization header missing'
        ]);
        exit;
    }

    $token = str_replace('Bearer ', '', $headers['Authorization']);
    
    // Get user from token
    $userQuery = "SELECT u.id, u.role FROM users u 
                  INNER JOIN user_tokens t ON u.id = t.user_id 
                  WHERE t.token = :token AND t.expires_at > NOW()";
    $userStmt = $db->prepare($userQuery);
    $userStmt->bindParam(':token', $token);
    $userStmt->execute();
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired token'
        ]);
        exit;
    }

    $userId = $user['id'];  // ✅ Now we have the authenticated user's ID

    // Fetch event types
    $eventTypesQuery = "SELECT DISTINCT event_type as id, event_type as name, 
                        event_type as description, event_type as category 
                        FROM packages 
                        LIMIT 10";
    $eventTypesStmt = $db->prepare($eventTypesQuery);
    $eventTypesStmt->execute();
    $eventTypes = $eventTypesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch services
    $servicesQuery = "SELECT 
                        id,
                        name,
                        description,
                        category,
                        base_price as basePrice,
                        rating,
                        review_count as reviewCount,
                        image_url as imageUrl
                      FROM services 
                      WHERE status = 'active'
                      LIMIT 20";
    $servicesStmt = $db->prepare($servicesQuery);
    $servicesStmt->execute();
    $services = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch packages
    $packagesQuery = "SELECT 
                        p.id,
                        p.package_id as packageId,
                        p.name,
                        p.description,
                        p.event_type as eventType,
                        p.price,
                        p.rating,
                        p.review_count as reviewCount,
                        p.image_url as imageUrl,
                        p.recommended
                      FROM packages p
                      WHERE 1=1
                      ORDER BY p.recommended DESC, p.rating DESC
                      LIMIT 20";
    $packagesStmt = $db->prepare($packagesQuery);
    $packagesStmt->execute();
    $packages = [];
    
    while ($row = $packagesStmt->fetch(PDO::FETCH_ASSOC)) {
        // Get inclusions for each package
        $inclusionsQuery = "SELECT service_name, description 
                           FROM package_services_map 
                           WHERE package_id = :package_id";
        $inclusionsStmt = $db->prepare($inclusionsQuery);
        $inclusionsStmt->bindParam(':package_id', $row['id']);
        $inclusionsStmt->execute();
        
        $inclusions = [];
        while ($inclusion = $inclusionsStmt->fetch(PDO::FETCH_ASSOC)) {
            $inclusions[] = [
                'name' => $inclusion['service_name'],
                'description' => $inclusion['description'],
                'isIncluded' => true
            ];
        }
        
        $row['inclusions'] = $inclusions;
        $packages[] = $row;
    }

    // ✅ FIXED: Fetch recent bookings for the authenticated user only
    $bookingsQuery = "SELECT 
                        b.id,
                        b.event_type as eventType,
                        b.event_date as date,
                        b.event_time as time,
                        b.venue,
                        b.status,
                        b.total_amount as totalPrice,
                        p.name as packageName
                      FROM bookings b
                      LEFT JOIN packages p ON b.package_id = p.id
                      WHERE b.client_id = :user_id
                      ORDER BY b.created_at DESC
                      LIMIT 5";
    $bookingsStmt = $db->prepare($bookingsQuery);
    $bookingsStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $bookingsStmt->execute();
    $recentBookings = $bookingsStmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Dashboard data retrieved successfully',
        'data' => [
            'eventTypes' => $eventTypes,
            'services' => $services,
            'packages' => $packages,
            'recentBookings' => $recentBookings
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'data' => null
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'data' => null
    ]);
}
?>