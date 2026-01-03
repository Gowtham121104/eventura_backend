<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

// Get POST data
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->notification_id) || !isset($data->user_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit;
}

$notification_id = intval($data->notification_id);
$user_id = intval($data->user_id);

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "UPDATE notifications 
              SET is_read = 1 
              WHERE id = :notification_id AND user_id = :user_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':notification_id', $notification_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to mark notification as read'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>