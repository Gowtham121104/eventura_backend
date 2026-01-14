<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $eventType = isset($_GET['event_type']) ? trim($_GET['event_type']) : null;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $perPage = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 10;
    $offset = ($page - 1) * $perPage;

    // ✅ SINGLE, CLEAN QUERY
    $query = "
    SELECT *
    FROM packages
WHERE (
    :event_type IS NULL
    OR LOWER(event_type) = LOWER(:event_type)
    OR LOWER(event_type) = LOWER(REPLACE(:event_type, ' ', '_'))
    OR LOWER(event_type) = LOWER(REPLACE(:event_type, '_', ' '))
)
    ORDER BY updated_at DESC
    LIMIT :limit OFFSET :offset
";


    $stmt = $db->prepare($query);

    // ✅ ALWAYS bind event_type
    if ($eventType === null || $eventType === '') {
        $stmt->bindValue(':event_type', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':event_type', $eventType, PDO::PARAM_STR);
    }

    $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

    $stmt->execute();

    $packages = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

        // Fetch inclusions
        $incStmt = $db->prepare("
            SELECT s.service_name
            FROM package_services_map psm
            INNER JOIN services s ON s.id = psm.service_id
            WHERE psm.package_id = :package_id
        ");

        $incStmt->bindValue(':package_id', $row['id'], PDO::PARAM_INT);
        $incStmt->execute();

        $inclusions = $incStmt->fetchAll(PDO::FETCH_ASSOC);

        $packages[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'price' => (float)$row['price'],
            'event_type' => $row['event_type'],
            'rating' => (float)$row['rating'],
            'review_count' => (int)$row['review_count'],
            'status' => $row['status'],
            'image' => $row['image'],
            'inclusions' => $inclusions,
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $packages
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
