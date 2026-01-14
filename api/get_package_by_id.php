<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/../config/database.php';

if (!isset($_GET['package_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'package_id is required'
    ]);
    exit;
}

$packageId = (int) $_GET['package_id'];

try {
    $database = new Database();
    $db = $database->getConnection();

    // ✅ Fetch single package
    $stmt = $db->prepare("
        SELECT *
        FROM packages
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->bindValue(':id', $packageId, PDO::PARAM_INT);
    $stmt->execute();

    $package = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$package) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Package not found'
        ]);
        exit;
    }

    // ✅ Fetch inclusions
    $incStmt = $db->prepare("
    SELECT s.service_name
    FROM package_services_map psm
    INNER JOIN services s ON s.id = psm.service_id
    WHERE psm.package_id = :package_id
    ");
    $incStmt->bindValue(':package_id', $packageId, PDO::PARAM_INT);
    $incStmt->execute();

    $package['inclusions'] = array_map(
        fn($row) => $row['service_name'],
        $incStmt->fetchAll(PDO::FETCH_ASSOC)
    );


    echo json_encode([
        'success' => true,
        'data' => $package
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
