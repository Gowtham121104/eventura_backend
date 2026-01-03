<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once '../config/database.php';

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
    
    if ($category !== 'ALL') {
        $categoryTypes = getCategoryTypes($category);
        if (!empty($categoryTypes)) {
            $placeholders = implode(',', array_fill(0, count($categoryTypes), '?'));
            $whereClause .= " AND type IN ($placeholders)";
        }
    }
    
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
              LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    
    // Bind category type filters if applicable
    if ($category !== 'ALL' && !empty($categoryTypes)) {
        $paramIndex = 1;
        foreach ($categoryTypes as $type) {
            $stmt->bindValue($paramIndex++, $type, PDO::PARAM_STR);
        }
    }
    
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
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
        'EVENTS' => ['BOOKING_CONFIRMATION', 'BOOKING_UPDATE', 'EVENT_REMINDER', 'EVENT_CANCELLED'],
        'PAYMENTS' => ['PAYMENT_CONFIRMATION', 'PAYMENT_REMINDER', 'REFUND'],
        'MESSAGES' => ['MESSAGE_ORGANIZER', 'MESSAGE_VENDOR', 'VENDOR_ASSIGNMENT'],
        'OFFERS' => ['SPECIAL_OFFER', 'NEW_PACKAGE', 'REVIEW_REQUEST']
    ];
    
    return isset($mapping[$category]) ? $mapping[$category] : [];
}
?>