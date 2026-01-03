<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

// Get POST data
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->user_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing user_id'
    ]);
    exit;
}

$user_id = intval($data->user_id);

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "UPDATE notifications 
              SET is_read = 1 
              WHERE user_id = :user_id AND is_read = 0";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        $rowCount = $stmt->rowCount();
        echo json_encode([
            'success' => true,
            'message' => 'All notifications marked as read',
            'updated_count' => $rowCount
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to mark all notifications as read'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
