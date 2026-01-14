<?php
/**
 * Organizer Authentication API
 * Handles login, registration, and profile management
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once '../config/database.php';
require_once '../utils/helpers.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$endpoint = Request::get('endpoint', '');

switch ($endpoint) {
    case 'login':
        login($db);
        break;
    case 'register':
        register($db);
        break;
    case 'get_profile':
        getProfile($db);
        break;
    case 'update_profile':
        updateProfile($db);
        break;
    case 'change_password':
        changePassword($db);
        break;
    default:
        ApiResponse::error("Invalid endpoint");
}

/**
 * Organizer Login
 */
function login($db) {
    $data = Request::getJson();
    
    $validator = new Validator();
    $validator->required($data['email'] ?? '', 'email')
              ->email($data['email'] ?? '', 'email')
              ->required($data['password'] ?? '', 'password');
    
    if (!$validator->isValid()) {
        ApiResponse::validationError($validator->getErrors());
    }

    $email = Request::sanitize($data['email']);
    $password = $data['password'];

    try {
        // Check user exists and is organizer
        $query = "SELECT u.*, op.id as organizer_profile_id, op.business_name, 
                         op.is_verified as profile_verified, op.rating
                  FROM users u
                  LEFT JOIN organizer_profiles op ON u.id = op.user_id
                  WHERE u.email = :email AND u.user_type = 'organizer' AND u.is_active = 1";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            ApiResponse::error("Invalid credentials", 401);
        }

        // Update last login
        $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':id', $user['id']);
        $updateStmt->execute();

        // Generate JWT token
        $tokenPayload = [
            'user_id' => $user['id'],
            'email' => $user['email'],
            'user_type' => $user['user_type'],
            'organizer_id' => $user['organizer_profile_id'],
            'iat' => time(),
            'exp' => time() + (86400 * 30) // 30 days
        ];

        $token = JWT::encode($tokenPayload);

        // Remove sensitive data
        unset($user['password_hash']);

        ApiResponse::success([
            'token' => $token,
            'user' => $user
        ], "Login successful");

    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        ApiResponse::error("Login failed");
    }
}

/**
 * Organizer Registration
 */
function register($db) {
    $data = Request::getJson();
    
    $validator = new Validator();
    $validator->required($data['name'] ?? '', 'name')
              ->required($data['email'] ?? '', 'email')
              ->email($data['email'] ?? '', 'email')
              ->required($data['phone'] ?? '', 'phone')
              ->phone($data['phone'] ?? '', 'phone')
              ->required($data['password'] ?? '', 'password')
              ->minLength($data['password'] ?? '', 6, 'password')
              ->required($data['business_name'] ?? '', 'business_name');
    
    if (!$validator->isValid()) {
        ApiResponse::validationError($validator->getErrors());
    }

    $name = Request::sanitize($data['name']);
    $email = Request::sanitize($data['email']);
    $phone = Request::sanitize($data['phone']);
    $password = $data['password'];
    $business_name = Request::sanitize($data['business_name']);
    $business_type = Request::sanitize($data['business_type'] ?? '');

    try {
        // Check if email already exists
        $checkQuery = "SELECT id FROM users WHERE email = :email";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':email', $email);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            ApiResponse::error("Email already registered", 409);
        }

        $db->beginTransaction();

        // Create user account
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        
        $userQuery = "INSERT INTO users (name, email, phone, password_hash, user_type) 
                      VALUES (:name, :email, :phone, :password_hash, 'organizer')";
        
        $userStmt = $db->prepare($userQuery);
        $userStmt->bindParam(':name', $name);
        $userStmt->bindParam(':email', $email);
        $userStmt->bindParam(':phone', $phone);
        $userStmt->bindParam(':password_hash', $passwordHash);
        $userStmt->execute();
        
        $userId = $db->lastInsertId();

        // Create organizer profile
        $profileQuery = "INSERT INTO organizer_profiles (user_id, business_name, business_type) 
                         VALUES (:user_id, :business_name, :business_type)";
        
        $profileStmt = $db->prepare($profileQuery);
        $profileStmt->bindParam(':user_id', $userId);
        $profileStmt->bindParam(':business_name', $business_name);
        $profileStmt->bindParam(':business_type', $business_type);
        $profileStmt->execute();
        
        $organizerId = $db->lastInsertId();

        // Create default working hours
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $hoursQuery = "INSERT INTO organizer_working_hours (organizer_id, day_of_week, is_open, open_time, close_time) 
                       VALUES (:organizer_id, :day, 1, '09:00:00', '18:00:00')";
        
        $hoursStmt = $db->prepare($hoursQuery);
        foreach ($days as $day) {
            if ($day == 'Sunday') continue; // Closed on Sunday by default
            $hoursStmt->bindParam(':organizer_id', $organizerId);
            $hoursStmt->bindParam(':day', $day);
            $hoursStmt->execute();
        }

        $db->commit();

        // Generate token
        $tokenPayload = [
            'user_id' => $userId,
            'email' => $email,
            'user_type' => 'organizer',
            'organizer_id' => $organizerId,
            'iat' => time(),
            'exp' => time() + (86400 * 30)
        ];

        $token = JWT::encode($tokenPayload);

        ApiResponse::success([
            'token' => $token,
            'user' => [
                'id' => $userId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'user_type' => 'organizer',
                'organizer_id' => $organizerId,
                'business_name' => $business_name
            ]
        ], "Registration successful", 201);

    } catch (Exception $e) {
        $db->rollBack();
        error_log("Registration error: " . $e->getMessage());
        ApiResponse::error("Registration failed");
    }
}

/**
 * Get Organizer Profile
 */
function getProfile($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];

    try {
        $query = "SELECT 
                    u.id, u.name, u.email, u.phone, u.profile_image, u.created_at,
                    op.id as organizer_id, op.business_name, op.business_type, 
                    op.description, op.address, op.city, op.state, op.pincode, op.country,
                    op.website, op.established_year, op.gst_number, op.pan_number,
                    op.is_verified, op.rating, op.total_reviews, op.total_events, op.total_revenue
                  FROM users u
                  INNER JOIN organizer_profiles op ON u.id = op.user_id
                  WHERE op.id = :organizer_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':organizer_id', $organizerId);
        $stmt->execute();
        
        $profile = $stmt->fetch();

        if (!$profile) {
            ApiResponse::notFound("Profile not found");
        }

        // Get working hours
        $hoursQuery = "SELECT day_of_week, is_open, open_time, close_time 
                       FROM organizer_working_hours 
                       WHERE organizer_id = :organizer_id 
                       ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')";
        
        $hoursStmt = $db->prepare($hoursQuery);
        $hoursStmt->bindParam(':organizer_id', $organizerId);
        $hoursStmt->execute();
        $profile['working_hours'] = $hoursStmt->fetchAll();

        // Get social media
        $socialQuery = "SELECT platform, url, followers_count FROM organizer_social_media WHERE organizer_id = :organizer_id";
        $socialStmt = $db->prepare($socialQuery);
        $socialStmt->bindParam(':organizer_id', $organizerId);
        $socialStmt->execute();
        $profile['social_media'] = $socialStmt->fetchAll();

        ApiResponse::success($profile);

    } catch (Exception $e) {
        error_log("Get profile error: " . $e->getMessage());
        ApiResponse::error("Failed to fetch profile");
    }
}

/**
 * Update Organizer Profile
 */
function updateProfile($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    $data = Request::getJson();

    try {
        $db->beginTransaction();

        // Update user table
        if (isset($data['name']) || isset($data['phone'])) {
            $updates = [];
            $params = [];
            
            if (isset($data['name'])) {
                $updates[] = "name = :name";
                $params[':name'] = Request::sanitize($data['name']);
            }
            if (isset($data['phone'])) {
                $updates[] = "phone = :phone";
                $params[':phone'] = Request::sanitize($data['phone']);
            }
            
            $userQuery = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :user_id";
            $params[':user_id'] = $user['user_id'];
            
            $stmt = $db->prepare($userQuery);
            $stmt->execute($params);
        }

        // Update organizer profile
        $profileUpdates = [];
        $profileParams = [];
        
        $allowedFields = ['business_name', 'business_type', 'description', 'address', 
                          'city', 'state', 'pincode', 'country', 'website', 'established_year'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $profileUpdates[] = "$field = :$field";
                $profileParams[":$field"] = Request::sanitize($data[$field]);
            }
        }

        if (!empty($profileUpdates)) {
            $profileQuery = "UPDATE organizer_profiles SET " . implode(', ', $profileUpdates) . 
                           " WHERE id = :organizer_id";
            $profileParams[':organizer_id'] = $organizerId;
            
            $stmt = $db->prepare($profileQuery);
            $stmt->execute($profileParams);
        }

        $db->commit();

        ApiResponse::success(null, "Profile updated successfully");

    } catch (Exception $e) {
        $db->rollBack();
        error_log("Update profile error: " . $e->getMessage());
        ApiResponse::error("Failed to update profile");
    }
}

/**
 * Change Password
 */
function changePassword($db) {
    $user = JWT::verifyToken();
    $data = Request::getJson();

    $validator = new Validator();
    $validator->required($data['current_password'] ?? '', 'current_password')
              ->required($data['new_password'] ?? '', 'new_password')
              ->minLength($data['new_password'] ?? '', 6, 'new_password');
    
    if (!$validator->isValid()) {
        ApiResponse::validationError($validator->getErrors());
    }

    try {
        // Verify current password
        $query = "SELECT password_hash FROM users WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $user['user_id']);
        $stmt->execute();
        
        $result = $stmt->fetch();
        
        if (!password_verify($data['current_password'], $result['password_hash'])) {
            ApiResponse::error("Current password is incorrect", 400);
        }

        // Update password
        $newPasswordHash = password_hash($data['new_password'], PASSWORD_BCRYPT);
        
        $updateQuery = "UPDATE users SET password_hash = :password_hash WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':password_hash', $newPasswordHash);
        $updateStmt->bindParam(':id', $user['user_id']);
        $updateStmt->execute();

        ApiResponse::success(null, "Password changed successfully");

    } catch (Exception $e) {
        error_log("Change password error: " . $e->getMessage());
        ApiResponse::error("Failed to change password");
    }
}

?>