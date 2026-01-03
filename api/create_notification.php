<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

// Get POST data
$data = json_decode(file_get_contents("php://input"));

// Validate required fields
$required = ['user_id', 'type', 'title', 'message'];
foreach ($required as $field) {
    if (!isset($data->$field)) {
        echo json_encode([
            'success' => false,
            'message' => "Missing required field: $field"
        ]);
        exit;
    }
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "INSERT INTO notifications 
              (user_id, type, priority, title, message, action_url, action_label, 
               image_url, related_event_id, related_booking_id) 
              VALUES 
              (:user_id, :type, :priority, :title, :message, :action_url, :action_label,
               :image_url, :related_event_id, :related_booking_id)";
    
    $stmt = $db->prepare($query);
    
    // Bind parameters
    $stmt->bindParam(':user_id', $data->user_id, PDO::PARAM_INT);
    $stmt->bindParam(':type', $data->type, PDO::PARAM_STR);
    $stmt->bindParam(':priority', $data->priority ?? 'NORMAL', PDO::PARAM_STR);
    $stmt->bindParam(':title', $data->title, PDO::PARAM_STR);
    $stmt->bindParam(':message', $data->message, PDO::PARAM_STR);
    $stmt->bindParam(':action_url', $data->action_url ?? null, PDO::PARAM_STR);
    $stmt->bindParam(':action_label', $data->action_label ?? null, PDO::PARAM_STR);
    $stmt->bindParam(':image_url', $data->image_url ?? null, PDO::PARAM_STR);
    $stmt->bindParam(':related_event_id', $data->related_event_id ?? null, PDO::PARAM_INT);
    $stmt->bindParam(':related_booking_id', $data->related_booking_id ?? null, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Notification created successfully',
            'notification_id' => $db->lastInsertId()
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create notification'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>