<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . "/db_connection.php";

// TEMP organizer id (we'll add auth later)
$organizer_id = 1;

$query = "
    SELECT 
        id,
        name,
        description,
        price,
        event_type,
        rating,
        review_count,
        status,
        image,
        created_at
    FROM packages
";

$stmt = $conn->prepare($query);
$stmt->execute();

$result = $stmt->get_result();

$packages = [];

while ($row = $result->fetch_assoc()) {
    $packages[] = $row;
}

echo json_encode([
    "success" => true,
    "packages" => $packages
]);
