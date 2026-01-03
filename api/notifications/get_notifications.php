<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once '../../config/database.php';

// Get user_id from query parameter
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($user_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid user ID'
    ]);
    exit;
}

// Get optional filters
$category = isset($_GET['category']) ? $_GET['category'] : 'ALL';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Build query based on category filter
    $whereClause = "WHERE user_id = :user_id";
    $params = [':user_id' => $user_id];
    
    if ($category !== 'ALL') {
        $categoryTypes = getCategoryTypes($category);
        if (!empty($categoryTypes)) {
            // Build named parameters for each type
            $typeParams = [];
            foreach ($categoryTypes as $index => $type) {
                $paramName = ":type_$index";
                $typeParams[] = $paramName;
                $params[$paramName] = $type;
            }
            $whereClause .= " AND type IN (" . implode(',', $typeParams) . ")";
        }
    }
    
    // Use integers directly for LIMIT and OFFSET (already validated)
    $query = "SELECT 
                id,
                type,
                priority,
                title,
                message,
                timestamp,
                is_read,
                action_url,
                action_label,
                image_url,
                related_event_id,
                related_booking_id
              FROM notifications 
              $whereClause
              ORDER BY timestamp DESC
              LIMIT $limit OFFSET $offset";
    
    $stmt = $db->prepare($query);
    
    // Bind all named parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unread count
    $countQuery = "SELECT COUNT(*) as unread_count 
                   FROM notifications 
                   WHERE user_id = :user_id AND is_read = 0";
    $countStmt = $db->prepare($countQuery);
    $countStmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $countStmt->execute();
    $unreadCount = $countStmt->fetch(PDO::FETCH_ASSOC)['unread_count'];
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => intval($unreadCount),
        'total_count' => count($notifications)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

function getCategoryTypes($category) {
    $mapping = [
        'EVENTS' => ['BOOKING_CONFIRMATION', 'BOOKING_UPDATE', 'EVENT_REMINDER', 'EVENT_CANCELLED', 'VENDOR_ASSIGNMENT'],
        'PAYMENTS' => ['PAYMENT_CONFIRMATION', 'PAYMENT_REMINDER', 'REFUND'],
        'MESSAGES' => ['MESSAGE_ORGANIZER', 'MESSAGE_VENDOR', 'REVIEW_REQUEST'],
        'OFFERS' => ['SPECIAL_OFFER', 'NEW_PACKAGE']
    ];
    
    return isset($mapping[$category]) ? $mapping[$category] : [];
}
?>
