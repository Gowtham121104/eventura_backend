<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/database.php';

// ✅ GET USER ID FROM TOKEN
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

try {
    $database = new Database();
    $db = $database->getConnection();

    // Validate token and get user
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

    $user_id = $user['id']; // ✅ Get user ID from token

    if (!isset($_FILES['image'])) {
        echo json_encode(['success' => false, 'message' => 'No image uploaded']);
        exit;
    }

    $file = $_FILES['image'];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB

    // Validate file type
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF allowed']);
        exit;
    }

    // Validate file size
    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'message' => 'File too large. Max 5MB']);
        exit;
    }

    // Create uploads directory if it doesn't exist
    $upload_dir = '../uploads/profile_images/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . $user_id . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Update database
        $query = "UPDATE users SET profile_image = :profile_image WHERE id = :user_id";
        $stmt = $db->prepare($query);
        $image_url = 'uploads/profile_images/' . $filename;
        $stmt->bindParam(':profile_image', $image_url);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute()) {
            // Fetch updated user data
            $fetchQuery = "SELECT id, name, email, phone, profile_image FROM users WHERE id = :user_id";
            $fetchStmt = $db->prepare($fetchQuery);
            $fetchStmt->bindParam(':user_id', $user_id);
            $fetchStmt->execute();
            $userData = $fetchStmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'message' => 'Profile image uploaded successfully',
                'user' => [
                    'id' => (int)$userData['id'],
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'phone' => $userData['phone'],
                    'profile_image' => $userData['profile_image']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update database']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>