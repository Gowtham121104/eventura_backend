<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../auth/token_auth.php';

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../api/debug.log');
error_reporting(E_ALL);

// Log the start of the request
error_log("=== Update Booking Status Request Start ===");

try {
    $userPayload = validateAdminOrOrganizerToken();
    $userId = $userPayload['user_id'];
    $userType = $userPayload['user_type'];
    $organizerId = $userPayload['organizer_id'] ?? null;

    error_log("User authenticated: ID=$userId, Type=$userType, OrganizerID=$organizerId");

    $database = new Database();
    $db = $database->getConnection();

    // Get JSON input
    $rawInput = file_get_contents("php://input");
    error_log("Raw input: $rawInput");
    
    $data = json_decode($rawInput, true);

    // Validate input
    if (!isset($data['booking_id']) || !isset($data['action'])) {
        error_log("Missing required fields");
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields: booking_id, action'
        ]);
        exit;
    }

    $bookingId = (int)$data['booking_id'];
    $action = strtoupper($data['action']);
    $remarks = $data['remarks'] ?? null;
    $modifiedPrice = isset($data['modified_price']) ? (float)$data['modified_price'] : null;
    $assignedOrganizerId = isset($data['assigned_organizer_id']) ? (int)$data['assigned_organizer_id'] : null;

    error_log("Booking ID: $bookingId, Action: $action");

    // Validate action
    if (!in_array($action, ['APPROVE', 'REJECT'])) {
        error_log("Invalid action: $action");
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action. Must be APPROVE or REJECT'
        ]);
        exit;
    }

    // Get current booking
    $bookingQuery = "SELECT * FROM bookings WHERE id = :booking_id";
    $bookingStmt = $db->prepare($bookingQuery);
    $bookingStmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
    $bookingStmt->execute();
    $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        error_log("Booking not found: $bookingId");
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Booking not found'
        ]);
        exit;
    }

    error_log("Booking found: Status=" . $booking['status']);

    // Validate current status
    if ($booking['status'] !== 'PENDING') {
        error_log("Invalid booking status: " . $booking['status']);
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Cannot $action booking with status: " . $booking['status']
        ]);
        exit;
    }

    // Organizer safety check
    if ($userType === 'organizer') {
        if (!empty($booking['assigned_organizer_id']) &&
            $booking['assigned_organizer_id'] != $organizerId) {
            error_log("Organizer not authorized");
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'You are not allowed to approve this booking'
            ]);
            exit;
        }

        if (empty($assignedOrganizerId)) {
            $assignedOrganizerId = $organizerId;
        }
    }

    // Set approved_by correctly - always use user_id
    $approvedBy = $userId;
    error_log("Approved by: $approvedBy");

    $db->beginTransaction();
    error_log("Transaction started");

    try {
        $newStatus = ($action === 'APPROVE') ? 'CONFIRMED' : 'REJECTED';
        error_log("New status: $newStatus");

        // Update booking
        if ($action === 'APPROVE') {
            $updateQuery = "UPDATE bookings SET 
                            status = :status,
                            approved_by = :approved_by,
                            admin_remarks = :admin_remarks,
                            modified_price = :modified_price,
                            assigned_organizer_id = :assigned_organizer_id,
                            approved_at = NOW()
                            WHERE id = :booking_id";

            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(':status', $newStatus);
            $updateStmt->bindParam(':approved_by', $approvedBy, PDO::PARAM_INT);
            $updateStmt->bindParam(':admin_remarks', $remarks);
            $updateStmt->bindParam(':modified_price', $modifiedPrice);
            $updateStmt->bindParam(':assigned_organizer_id', $assignedOrganizerId, PDO::PARAM_INT);
            $updateStmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
        } else {
            $rejectionReason = $data['rejection_reason'] ?? 'Booking rejected';
            
            $updateQuery = "UPDATE bookings SET 
                            status = :status,
                            approved_by = :approved_by,
                            admin_remarks = :admin_remarks,
                            modified_price = :modified_price,
                            assigned_organizer_id = :assigned_organizer_id,
                            rejected_at = NOW(),
                            rejection_reason = :rejection_reason
                            WHERE id = :booking_id";

            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(':status', $newStatus);
            $updateStmt->bindParam(':approved_by', $approvedBy, PDO::PARAM_INT);
            $updateStmt->bindParam(':admin_remarks', $remarks);
            $updateStmt->bindParam(':modified_price', $modifiedPrice);
            $updateStmt->bindParam(':assigned_organizer_id', $assignedOrganizerId, PDO::PARAM_INT);
            $updateStmt->bindParam(':rejection_reason', $rejectionReason);
            $updateStmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
        }

        $updateStmt->execute();
        error_log("Booking updated successfully");

        // Log status history
        $historyQuery = "INSERT INTO booking_status_history (
            booking_id, old_status, new_status, changed_by, remarks
        ) VALUES (
            :booking_id, :old_status, :new_status, :changed_by, :remarks
        )";

        $historyStmt = $db->prepare($historyQuery);
        $historyStmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
        $historyStmt->bindParam(':old_status', $booking['status']);
        $historyStmt->bindParam(':new_status', $newStatus);
        $historyStmt->bindParam(':changed_by', $approvedBy, PDO::PARAM_INT);
        $historyStmt->bindParam(':remarks', $remarks);
        $historyStmt->execute();
        error_log("History logged successfully");

        // Create notification
        $notifType = ($action === 'APPROVE') ? 'BOOKING_CONFIRMED' : 'BOOKING_REJECTED';
        $notifTitle = ($action === 'APPROVE') ? '✅ Booking Confirmed!' : '❌ Booking Rejected';
        $approvedByText = ($userType === 'admin') ? 'admin' : 'organizer';

        $notifMessage = ($action === 'APPROVE')
            ? "Your booking has been approved by the $approvedByText."
            : 'Your booking request has been rejected.';

        $notifQuery = "INSERT INTO notifications (
            user_id, related_booking_id, type, title, message
        ) VALUES (
            :user_id, :related_booking_id, :type, :title, :message
        )";

        $notifStmt = $db->prepare($notifQuery);
        $notifStmt->bindParam(':user_id', $booking['client_id'], PDO::PARAM_INT);
        $notifStmt->bindParam(':related_booking_id', $bookingId, PDO::PARAM_INT);
        $notifStmt->bindParam(':type', $notifType);
        $notifStmt->bindParam(':title', $notifTitle);
        $notifStmt->bindParam(':message', $notifMessage);
        $notifStmt->execute();
        error_log("Notification created successfully");

        $db->commit();
        error_log("Transaction committed");

        $response = [
            'success' => true,
            'message' => "Booking $newStatus successfully",
            'data' => [
                'id' => $bookingId,
                'status' => $newStatus
            ]
        ];

        error_log("Response: " . json_encode($response));
        
        http_response_code(200);
        echo json_encode($response);
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        error_log("Transaction error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error in update_booking_status.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
    exit;
}

error_log("=== Update Booking Status Request End ===");
?>