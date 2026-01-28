<?php
require_once __DIR__ . '/../config/database.php';

function verifyToken() {
    // Get authorization header
    $headers = getallheaders();
    
    if (empty($headers['Authorization']) && empty($headers['authorization'])) {
        return false;
    }
    
    // Extract token
    $authHeader = $headers['Authorization'] ?? $headers['authorization'];
    $token = str_replace('Bearer ', '', $authHeader);
    
    if (empty($token)) {
        return false;
    }
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Verify token and get user
        $query = "SELECT u.id as user_id, u.role, u.name, u.email 
                  FROM users u 
                  INNER JOIN user_tokens t ON u.id = t.user_id 
                  WHERE t.token = :token 
                  AND t.expires_at > NOW()";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return false;
        }
        
        return $user;
        
    } catch (PDOException $e) {
        error_log("Token verification error: " . $e->getMessage());
        return false;
    }
}

function validateAdminOrOrganizerToken() {
    $user = verifyToken();
    
    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Authentication required'
        ]);
        exit();
    }
    
    if (!in_array($user['role'], ['admin', 'organizer'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Insufficient permissions'
        ]);
        exit();
    }
    
    // Get organizer_id if user is organizer
    if ($user['role'] === 'organizer') {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "SELECT id FROM organizer_profiles WHERE user_id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user['user_id']);
            $stmt->execute();
            
            $organizer = $stmt->fetch(PDO::FETCH_ASSOC);
            $user['organizer_id'] = $organizer ? $organizer['id'] : null;
            
        } catch (PDOException $e) {
            error_log("Error fetching organizer_id: " . $e->getMessage());
            $user['organizer_id'] = null;
        }
    }
    
    return $user;
}
?>