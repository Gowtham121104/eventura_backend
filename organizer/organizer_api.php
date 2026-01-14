<?php
/**
 * EVENTURA - COMPLETE ORGANIZER API
 * All-in-one API for Organizer Admin Panel
 * 
 * Endpoints:
 * - Dashboard & Analytics
 * - Events Management
 * - Bookings & Leads
 * - Payments & Invoices
 * - Team Management
 * - Services
 * - Reviews & Communication
 * - Marketing
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/database.php';
require_once '../utils/helpers.php';

$database = new Database();
$db = $database->getConnection();

$endpoint = Request::get('endpoint', '');

// Route to appropriate handler
switch ($endpoint) {
    // ===== DASHBOARD =====
    case 'get_dashboard_stats':
        getDashboardStats($db);
        break;
    case 'get_recent_activity':
        getRecentActivity($db);
        break;
    
    // ===== EVENTS =====
    case 'get_events':
        getEvents($db);
        break;
    case 'get_event_detail':
        getEventDetail($db);
        break;
    case 'create_event':
        createEvent($db);
        break;
    case 'update_event':
        updateEvent($db);
        break;
    case 'delete_event':
        deleteEvent($db);
        break;
    
    // ===== BOOKING REQUESTS =====
    case 'get_booking_requests':
        getBookingRequests($db);
        break;
    case 'get_booking_request_detail':
        getBookingRequestDetail($db);
        break;
    case 'update_booking_status':
        updateBookingStatus($db);
        break;
    case 'create_quotation':
        createQuotation($db);
        break;
    case 'convert_to_event':
        convertBookingToEvent($db);
        break;
    
    // ===== TEAM MANAGEMENT =====
    case 'get_team_members':
        getTeamMembers($db);
        break;
    case 'add_team_member':
        addTeamMember($db);
        break;
    case 'update_team_member':
        updateTeamMember($db);
        break;
    case 'delete_team_member':
        deleteTeamMember($db);
        break;
    
    // ===== SERVICES =====
    case 'get_services':
        getServices($db);
        break;
    case 'create_service':
        createService($db);
        break;
    case 'update_service':
        updateService($db);
        break;
    case 'delete_service':
        deleteService($db);
        break;
    
    // ===== PAYMENTS =====
    case 'get_payments':
        getPayments($db);
        break;
    case 'record_payment':
        recordPayment($db);
        break;
    case 'get_revenue_stats':
        getRevenueStats($db);
        break;
    
    // ===== INVOICES =====
    case 'get_invoices':
        getInvoices($db);
        break;
    case 'create_invoice':
        createInvoice($db);
        break;
    case 'update_invoice':
        updateInvoice($db);
        break;
    
    // ===== REVIEWS =====
    case 'get_reviews':
        getReviews($db);
        break;
    case 'respond_to_review':
        respondToReview($db);
        break;
    
    // ===== ANALYTICS =====
    case 'get_analytics':
        getAnalytics($db);
        break;
    case 'get_event_trends':
        getEventTrends($db);
        break;
    case 'get_revenue_trends':
        getRevenueTrends($db);
        break;
    
    // ===== NOTIFICATIONS =====
    case 'get_notifications':
        getNotifications($db);
        break;
    case 'mark_notification_read':
        markNotificationRead($db);
        break;
    
    default:
        ApiResponse::error("Invalid endpoint");
}

// ==================== DASHBOARD ====================

function getDashboardStats($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];

    try {
        // Get overall stats
        $statsQuery = "SELECT 
            COUNT(DISTINCT e.id) as total_events,
            COUNT(DISTINCT CASE WHEN e.status = 'upcoming' THEN e.id END) as upcoming_events,
            COUNT(DISTINCT CASE WHEN e.status = 'completed' THEN e.id END) as completed_events,
            COUNT(DISTINCT br.id) as total_booking_requests,
            COUNT(DISTINCT CASE WHEN br.status IN ('new', 'contacted') THEN br.id END) as pending_requests,
            COALESCE(SUM(CASE WHEN p.payment_status = 'completed' THEN p.amount ELSE 0 END), 0) as total_revenue,
            COALESCE(SUM(CASE WHEN p.payment_status = 'pending' THEN p.amount ELSE 0 END), 0) as pending_payments,
            COUNT(DISTINCT tm.id) as total_team_members,
            AVG(r.rating) as average_rating,
            COUNT(DISTINCT r.id) as total_reviews
        FROM organizer_profiles op
        LEFT JOIN events e ON op.id = e.organizer_id
        LEFT JOIN booking_requests br ON op.id = br.organizer_id
        LEFT JOIN payments p ON op.id = p.organizer_id
        LEFT JOIN team_members tm ON op.id = tm.organizer_id AND tm.is_active = 1
        LEFT JOIN reviews r ON op.id = r.organizer_id AND r.is_published = 1
        WHERE op.id = :organizer_id";

        $stmt = $db->prepare($statsQuery);
        $stmt->bindParam(':organizer_id', $organizerId);
        $stmt->execute();
        $stats = $stmt->fetch();

        // Get upcoming events (next 7 days)
        $upcomingQuery = "SELECT id, title, category, event_date, expected_guests, venue_name, cover_image
                         FROM events 
                         WHERE organizer_id = :organizer_id 
                         AND status = 'upcoming' 
                         AND event_date >= CURDATE()
                         AND event_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                         ORDER BY event_date ASC LIMIT 5";
        
        $upcomingStmt = $db->prepare($upcomingQuery);
        $upcomingStmt->bindParam(':organizer_id', $organizerId);
        $upcomingStmt->execute();
        $upcomingEvents = $upcomingStmt->fetchAll();

        // Get recent booking requests
        $recentBookingsQuery = "SELECT id, client_name, event_type, event_date, status, priority, created_at
                               FROM booking_requests 
                               WHERE organizer_id = :organizer_id 
                               ORDER BY created_at DESC LIMIT 5";
        
        $recentBookingsStmt = $db->prepare($recentBookingsQuery);
        $recentBookingsStmt->bindParam(':organizer_id', $organizerId);
        $recentBookingsStmt->execute();
        $recentBookings = $recentBookingsStmt->fetchAll();

        // Revenue by month (last 6 months)
        $revenueQuery = "SELECT 
                DATE_FORMAT(payment_date, '%Y-%m') as month,
                SUM(amount) as revenue
            FROM payments
            WHERE organizer_id = :organizer_id
            AND payment_status = 'completed'
            AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
            ORDER BY month ASC";
        
        $revenueStmt = $db->prepare($revenueQuery);
        $revenueStmt->bindParam(':organizer_id', $organizerId);
        $revenueStmt->execute();
        $revenueByMonth = $revenueStmt->fetchAll();

        ApiResponse::success([
            'stats' => $stats,
            'upcoming_events' => $upcomingEvents,
            'recent_bookings' => $recentBookings,
            'revenue_by_month' => $revenueByMonth
        ]);

    } catch (Exception $e) {
        error_log("Dashboard stats error: " . $e->getMessage());
        ApiResponse::error("Failed to fetch dashboard stats");
    }
}

function getRecentActivity($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    $limit = Request::get('limit', 20);

    try {
        $query = "SELECT action, entity_type, entity_id, description, created_at
                 FROM activity_logs
                 WHERE user_id = :user_id
                 ORDER BY created_at DESC
                 LIMIT :limit";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user['user_id']);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $activities = $stmt->fetchAll();

        ApiResponse::success($activities);

    } catch (Exception $e) {
        error_log("Recent activity error: " . $e->getMessage());
        ApiResponse::error("Failed to fetch recent activity");
    }
}

// ==================== EVENTS ====================

function getEvents($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    
    $status = Request::get('status', '');
    $category = Request::get('category', '');
    $search = Request::get('search', '');
    $page = Request::get('page', 1);
    $perPage = Request::get('per_page', 10);

    try {
        $query = "SELECT id, title, description, category, status, event_date, start_time, end_time,
                        venue_name, city, expected_guests, budget_min, budget_max, 
                        total_revenue, client_name, cover_image, created_at
                 FROM events
                 WHERE organizer_id = :organizer_id";
        
        $params = [':organizer_id' => $organizerId];

        if ($status) {
            $query .= " AND status = :status";
            $params[':status'] = $status;
        }

        if ($category) {
            $query .= " AND category = :category";
            $params[':category'] = $category;
        }

        if ($search) {
            $query .= " AND (title LIKE :search OR client_name LIKE :search OR city LIKE :search)";
            $params[':search'] = "%$search%";
        }

        $query .= " ORDER BY event_date DESC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        // Manual pagination
        $allResults = $stmt->fetchAll();
        $total = count($allResults);
        $totalPages = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $paginatedResults = array_slice($allResults, $offset, $perPage);

        ApiResponse::success([
            'data' => $paginatedResults,
            'pagination' => [
                'current_page' => (int)$page,
                'per_page' => (int)$perPage,
                'total' => $total,
                'total_pages' => $totalPages
            ]
        ]);

    } catch (Exception $e) {
        error_log("Get events error: " . $e->getMessage());
        ApiResponse::error("Failed to fetch events");
    }
}

function getEventDetail($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    $eventId = Request::get('event_id');

    if (!$eventId) {
        ApiResponse::error("Event ID is required");
    }

    try {
        $query = "SELECT * FROM events WHERE id = :event_id AND organizer_id = :organizer_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':event_id', $eventId);
        $stmt->bindParam(':organizer_id', $organizerId);
        $stmt->execute();
        
        $event = $stmt->fetch();

        if (!$event) {
            ApiResponse::notFound("Event not found");
        }

        // Get assigned team members
        $teamQuery = "SELECT eta.*, tm.name, tm.role, tm.specialization
                     FROM event_team_assignments eta
                     INNER JOIN team_members tm ON eta.team_member_id = tm.id
                     WHERE eta.event_id = :event_id";
        
        $teamStmt = $db->prepare($teamQuery);
        $teamStmt->bindParam(':event_id', $eventId);
        $teamStmt->execute();
        $event['team'] = $teamStmt->fetchAll();

        // Get tasks
        $tasksQuery = "SELECT * FROM event_tasks WHERE event_id = :event_id ORDER BY due_date ASC";
        $tasksStmt = $db->prepare($tasksQuery);
        $tasksStmt->bindParam(':event_id', $eventId);
        $tasksStmt->execute();
        $event['tasks'] = $tasksStmt->fetchAll();

        // Parse JSON fields
        if ($event['gallery_images']) {
            $event['gallery_images'] = json_decode($event['gallery_images'], true);
        }

        ApiResponse::success($event);

    } catch (Exception $e) {
        error_log("Get event detail error: " . $e->getMessage());
        ApiResponse::error("Failed to fetch event details");
    }
}

function createEvent($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    $data = Request::getJson();

    $validator = new Validator();
    $validator->required($data['title'] ?? '', 'title')
              ->required($data['category'] ?? '', 'category')
              ->required($data['event_date'] ?? '', 'event_date')
              ->date($data['event_date'] ?? '', 'event_date');

    if (!$validator->isValid()) {
        ApiResponse::validationError($validator->getErrors());
    }

    try {
        $query = "INSERT INTO events (
                    organizer_id, title, description, category, event_type, status,
                    venue_name, venue_address, city, state, event_date, start_time, end_time,
                    expected_guests, budget_min, budget_max, client_name, client_email, client_phone,
                    cover_image, notes
                ) VALUES (
                    :organizer_id, :title, :description, :category, :event_type, :status,
                    :venue_name, :venue_address, :city, :state, :event_date, :start_time, :end_time,
                    :expected_guests, :budget_min, :budget_max, :client_name, :client_email, :client_phone,
                    :cover_image, :notes
                )";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':organizer_id', $organizerId);
        $stmt->bindParam(':title', $data['title']);
        $stmt->bindParam(':description', $data['description'] ?? null);
        $stmt->bindParam(':category', $data['category']);
        $stmt->bindParam(':event_type', $data['event_type'] ?? null);
        $stmt->bindParam(':status', $data['status'] ?? 'draft');
        $stmt->bindParam(':venue_name', $data['venue_name'] ?? null);
        $stmt->bindParam(':venue_address', $data['venue_address'] ?? null);
        $stmt->bindParam(':city', $data['city'] ?? null);
        $stmt->bindParam(':state', $data['state'] ?? null);
        $stmt->bindParam(':event_date', $data['event_date']);
        $stmt->bindParam(':start_time', $data['start_time'] ?? null);
        $stmt->bindParam(':end_time', $data['end_time'] ?? null);
        $stmt->bindParam(':expected_guests', $data['expected_guests'] ?? null);
        $stmt->bindParam(':budget_min', $data['budget_min'] ?? null);
        $stmt->bindParam(':budget_max', $data['budget_max'] ?? null);
        $stmt->bindParam(':client_name', $data['client_name'] ?? null);
        $stmt->bindParam(':client_email', $data['client_email'] ?? null);
        $stmt->bindParam(':client_phone', $data['client_phone'] ?? null);
        $stmt->bindParam(':cover_image', $data['cover_image'] ?? null);
        $stmt->bindParam(':notes', $data['notes'] ?? null);
        
        $stmt->execute();
        $eventId = $db->lastInsertId();

        // Log activity
        logActivity($db, $user['user_id'], "Created event", "event", $eventId, "Created event: " . $data['title']);

        ApiResponse::success(['event_id' => $eventId], "Event created successfully", 201);

    } catch (Exception $e) {
        error_log("Create event error: " . $e->getMessage());
        ApiResponse::error("Failed to create event");
    }
}

function updateEvent($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    $data = Request::getJson();
    $eventId = $data['event_id'] ?? null;

    if (!$eventId) {
        ApiResponse::error("Event ID is required");
    }

    try {
        // Verify ownership
        $checkQuery = "SELECT id FROM events WHERE id = :event_id AND organizer_id = :organizer_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':event_id', $eventId);
        $checkStmt->bindParam(':organizer_id', $organizerId);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() == 0) {
            ApiResponse::forbidden("You don't have permission to update this event");
        }

        $updates = [];
        $params = [':event_id' => $eventId];
        
        $allowedFields = ['title', 'description', 'category', 'event_type', 'status', 
                         'venue_name', 'venue_address', 'city', 'state', 'event_date', 
                         'start_time', 'end_time', 'expected_guests', 'actual_guests',
                         'budget_min', 'budget_max', 'total_revenue', 'total_cost',
                         'client_name', 'client_email', 'client_phone', 'cover_image', 'notes'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($updates)) {
            ApiResponse::error("No fields to update");
        }

        $query = "UPDATE events SET " . implode(', ', $updates) . " WHERE id = :event_id";
        $stmt = $db->prepare($query);
        $stmt->execute($params);

        logActivity($db, $user['user_id'], "Updated event", "event", $eventId, "Updated event ID: $eventId");

        ApiResponse::success(null, "Event updated successfully");

    } catch (Exception $e) {
        error_log("Update event error: " . $e->getMessage());
        ApiResponse::error("Failed to update event");
    }
}

function deleteEvent($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    $eventId = Request::get('event_id');

    if (!$eventId) {
        ApiResponse::error("Event ID is required");
    }

    try {
        // Verify ownership
        $checkQuery = "SELECT title FROM events WHERE id = :event_id AND organizer_id = :organizer_id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':event_id', $eventId);
        $checkStmt->bindParam(':organizer_id', $organizerId);
        $checkStmt->execute();
        
        $event = $checkStmt->fetch();
        if (!$event) {
            ApiResponse::forbidden("You don't have permission to delete this event");
        }

        $query = "DELETE FROM events WHERE id = :event_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':event_id', $eventId);
        $stmt->execute();

        logActivity($db, $user['user_id'], "Deleted event", "event", $eventId, "Deleted event: " . $event['title']);

        ApiResponse::success(null, "Event deleted successfully");

    } catch (Exception $e) {
        error_log("Delete event error: " . $e->getMessage());
        ApiResponse::error("Failed to delete event");
    }
}

// ==================== BOOKING REQUESTS ====================

function getBookingRequests($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    
    $status = Request::get('status', '');
    $page = Request::get('page', 1);
    $perPage = Request::get('per_page', 10);

    try {
        $query = "SELECT * FROM booking_requests WHERE organizer_id = :organizer_id";
        $params = [':organizer_id' => $organizerId];

        if ($status) {
            $query .= " AND status = :status";
            $params[':status'] = $status;
        }

        $query .= " ORDER BY created_at DESC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        $allResults = $stmt->fetchAll();
        $total = count($allResults);
        $totalPages = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $paginatedResults = array_slice($allResults, $offset, $perPage);

        ApiResponse::success([
            'data' => $paginatedResults,
            'pagination' => [
                'current_page' => (int)$page,
                'per_page' => (int)$perPage,
                'total' => $total,
                'total_pages' => $totalPages
            ]
        ]);

    } catch (Exception $e) {
        error_log("Get booking requests error: " . $e->getMessage());
        ApiResponse::error("Failed to fetch booking requests");
    }
}

function getBookingRequestDetail($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    $bookingId = Request::get('booking_id');

    if (!$bookingId) {
        ApiResponse::error("Booking ID is required");
    }

    try {
        $query = "SELECT * FROM booking_requests WHERE id = :booking_id AND organizer_id = :organizer_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':booking_id', $bookingId);
        $stmt->bindParam(':organizer_id', $organizerId);
        $stmt->execute();
        
        $booking = $stmt->fetch();

        if (!$booking) {
            ApiResponse::notFound("Booking request not found");
        }

        // Get quotations
        $quotationsQuery = "SELECT * FROM quotations WHERE booking_request_id = :booking_id ORDER BY created_at DESC";
        $quotationsStmt = $db->prepare($quotationsQuery);
        $quotationsStmt->bindParam(':booking_id', $bookingId);
        $quotationsStmt->execute();
        $booking['quotations'] = $quotationsStmt->fetchAll();

        ApiResponse::success($booking);

    } catch (Exception $e) {
        error_log("Get booking detail error: " . $e->getMessage());
        ApiResponse::error("Failed to fetch booking details");
    }
}

function updateBookingStatus($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    $data = Request::getJson();

    $validator = new Validator();
    $validator->required($data['booking_id'] ?? '', 'booking_id')
              ->required($data['status'] ?? '', 'status')
              ->in($data['status'] ?? '', ['new', 'contacted', 'quoted', 'negotiating', 'approved', 'rejected', 'converted'], 'status');

    if (!$validator->isValid()) {
        ApiResponse::validationError($validator->getErrors());
    }

    try {
        $query = "UPDATE booking_requests SET status = :status, notes = :notes 
                 WHERE id = :booking_id AND organizer_id = :organizer_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':status', $data['status']);
        $stmt->bindParam(':notes', $data['notes'] ?? null);
        $stmt->bindParam(':booking_id', $data['booking_id']);
        $stmt->bindParam(':organizer_id', $organizerId);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            ApiResponse::error("Booking request not found or not authorized");
        }

        logActivity($db, $user['user_id'], "Updated booking status", "booking", $data['booking_id'], 
                   "Changed status to: " . $data['status']);

        ApiResponse::success(null, "Booking status updated successfully");

    } catch (Exception $e) {
        error_log("Update booking status error: " . $e->getMessage());
        ApiResponse::error("Failed to update booking status");
    }
}

function createQuotation($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    $data = Request::getJson();

    $validator = new Validator();
    $validator->required($data['booking_request_id'] ?? '', 'booking_request_id')
              ->required($data['total_amount'] ?? '', 'total_amount')
              ->required($data['items'] ?? '', 'items');

    if (!$validator->isValid()) {
        ApiResponse::validationError($validator->getErrors());
    }

    try {
        // Generate quotation number
        $quotationNumber = 'QUO-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));

        $query = "INSERT INTO quotations (
                    booking_request_id, organizer_id, quotation_number, 
                    total_amount, discount_amount, tax_amount, final_amount,
                    items, terms_conditions, validity_date, status
                ) VALUES (
                    :booking_request_id, :organizer_id, :quotation_number,
                    :total_amount, :discount_amount, :tax_amount, :final_amount,
                    :items, :terms_conditions, :validity_date, :status
                )";

        $finalAmount = $data['total_amount'] - ($data['discount_amount'] ?? 0) + ($data['tax_amount'] ?? 0);

        $stmt = $db->prepare($query);
        $stmt->bindParam(':booking_request_id', $data['booking_request_id']);
        $stmt->bindParam(':organizer_id', $organizerId);
        $stmt->bindParam(':quotation_number', $quotationNumber);
        $stmt->bindParam(':total_amount', $data['total_amount']);
        $stmt->bindParam(':discount_amount', $data['discount_amount'] ?? 0);
        $stmt->bindParam(':tax_amount', $data['tax_amount'] ?? 0);
        $stmt->bindParam(':final_amount', $finalAmount);
        $stmt->bindParam(':items', json_encode($data['items']));
        $stmt->bindParam(':terms_conditions', $data['terms_conditions'] ?? null);
        $stmt->bindParam(':validity_date', $data['validity_date'] ?? null);
        $stmt->bindParam(':status', $data['status'] ?? 'draft');
        
        $stmt->execute();
        $quotationId = $db->lastInsertId();

        // Update booking request status
        $updateQuery = "UPDATE booking_requests SET status = 'quoted' WHERE id = :booking_id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':booking_id', $data['booking_request_id']);
        $updateStmt->execute();

        ApiResponse::success([
            'quotation_id' => $quotationId,
            'quotation_number' => $quotationNumber
        ], "Quotation created successfully", 201);

    } catch (Exception $e) {
        error_log("Create quotation error: " . $e->getMessage());
        ApiResponse::error("Failed to create quotation");
    }
}

function convertBookingToEvent($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    $data = Request::getJson();
    $bookingId = $data['booking_id'] ?? null;

    if (!$bookingId) {
        ApiResponse::error("Booking ID is required");
    }

    try {
        $db->beginTransaction();

        // Get booking details
        $bookingQuery = "SELECT * FROM booking_requests WHERE id = :booking_id AND organizer_id = :organizer_id";
        $bookingStmt = $db->prepare($bookingQuery);
        $bookingStmt->bindParam(':booking_id', $bookingId);
        $bookingStmt->bindParam(':organizer_id', $organizerId);
        $bookingStmt->execute();
        
        $booking = $bookingStmt->fetch();
        if (!$booking) {
            ApiResponse::notFound("Booking request not found");
        }

        // Create event from booking
        $eventQuery = "INSERT INTO events (
                        organizer_id, title, category, event_type, status,
                        event_date, expected_guests, city,
                        client_name, client_email, client_phone
                    ) VALUES (
                        :organizer_id, :title, :category, :event_type, 'upcoming',
                        :event_date, :expected_guests, :city,
                        :client_name, :client_email, :client_phone
                    )";

        $title = $booking['event_type'] . " - " . $booking['client_name'];
        
        $eventStmt = $db->prepare($eventQuery);
        $eventStmt->bindParam(':organizer_id', $organizerId);
        $eventStmt->bindParam(':title', $title);
        $eventStmt->bindParam(':category', $booking['event_type']);
        $eventStmt->bindParam(':event_type', $booking['event_type']);
        $eventStmt->bindParam(':event_date', $booking['event_date']);
        $eventStmt->bindParam(':expected_guests', $booking['expected_guests']);
        $eventStmt->bindParam(':city', $booking['city']);
        $eventStmt->bindParam(':client_name', $booking['client_name']);
        $eventStmt->bindParam(':client_email', $booking['client_email']);
        $eventStmt->bindParam(':client_phone', $booking['client_phone']);
        
        $eventStmt->execute();
        $eventId = $db->lastInsertId();

        // Update booking request
        $updateQuery = "UPDATE booking_requests SET status = 'converted', converted_event_id = :event_id 
                       WHERE id = :booking_id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':event_id', $eventId);
        $updateStmt->bindParam(':booking_id', $bookingId);
        $updateStmt->execute();

        $db->commit();

        logActivity($db, $user['user_id'], "Converted booking to event", "event", $eventId, 
                   "Converted booking #$bookingId to event #$eventId");

        ApiResponse::success(['event_id' => $eventId], "Booking converted to event successfully");

    } catch (Exception $e) {
        $db->rollBack();
        error_log("Convert booking error: " . $e->getMessage());
        ApiResponse::error("Failed to convert booking to event");
    }
}

// ==================== TEAM MANAGEMENT ====================

function getTeamMembers($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    
    $role = Request::get('role', '');
    $isActive = Request::get('is_active', '');

    try {
        $query = "SELECT * FROM team_members WHERE organizer_id = :organizer_id";
        $params = [':organizer_id' => $organizerId];

        if ($role) {
            $query .= " AND role = :role";
            $params[':role'] = $role;
        }

        if ($isActive !== '') {
            $query .= " AND is_active = :is_active";
            $params[':is_active'] = $isActive;
        }

        $query .= " ORDER BY name ASC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        $members = $stmt->fetchAll();

        // Parse JSON fields
        foreach ($members as &$member) {
            if ($member['portfolio_images']) {
                $member['portfolio_images'] = json_decode($member['portfolio_images'], true);
            }
        }

        ApiResponse::success($members);

    } catch (Exception $e) {
        error_log("Get team members error: " . $e->getMessage());
        ApiResponse::error("Failed to fetch team members");
    }
}

function addTeamMember($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    $data = Request::getJson();

    $validator = new Validator();
    $validator->required($data['name'] ?? '', 'name')
              ->required($data['role'] ?? '', 'role');

    if (!$validator->isValid()) {
        ApiResponse::validationError($validator->getErrors());
    }

    try {
        $query = "INSERT INTO team_members (
                    organizer_id, name, role, email, phone, specialization,
                    experience_years, hourly_rate, daily_rate
                ) VALUES (
                    :organizer_id, :name, :role, :email, :phone, :specialization,
                    :experience_years, :hourly_rate, :daily_rate
                )";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':organizer_id', $organizerId);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':role', $data['role']);
        $stmt->bindParam(':email', $data['email'] ?? null);
        $stmt->bindParam(':phone', $data['phone'] ?? null);
        $stmt->bindParam(':specialization', $data['specialization'] ?? null);
        $stmt->bindParam(':experience_years', $data['experience_years'] ?? null);
        $stmt->bindParam(':hourly_rate', $data['hourly_rate'] ?? null);
        $stmt->bindParam(':daily_rate', $data['daily_rate'] ?? null);
        
        $stmt->execute();
        $memberId = $db->lastInsertId();

        ApiResponse::success(['member_id' => $memberId], "Team member added successfully", 201);

    } catch (Exception $e) {
        error_log("Add team member error: " . $e->getMessage());
        ApiResponse::error("Failed to add team member");
    }
}

function updateTeamMember($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    $data = Request::getJson();
    $memberId = $data['member_id'] ?? null;

    if (!$memberId) {
        ApiResponse::error("Member ID is required");
    }

    try {
        $updates = [];
        $params = [':member_id' => $memberId, ':organizer_id' => $organizerId];
        
        $allowedFields = ['name', 'role', 'email', 'phone', 'specialization', 
                         'experience_years', 'hourly_rate', 'daily_rate', 'is_active', 'availability_status'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($updates)) {
            ApiResponse::error("No fields to update");
        }

        $query = "UPDATE team_members SET " . implode(', ', $updates) . 
                " WHERE id = :member_id AND organizer_id = :organizer_id";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);

        if ($stmt->rowCount() == 0) {
            ApiResponse::error("Team member not found or not authorized");
        }

        ApiResponse::success(null, "Team member updated successfully");

    } catch (Exception $e) {
        error_log("Update team member error: " . $e->getMessage());
        ApiResponse::error("Failed to update team member");
    }
}

function deleteTeamMember($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    $memberId = Request::get('member_id');

    if (!$memberId) {
        ApiResponse::error("Member ID is required");
    }

    try {
        $query = "DELETE FROM team_members WHERE id = :member_id AND organizer_id = :organizer_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':member_id', $memberId);
        $stmt->bindParam(':organizer_id', $organizerId);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            ApiResponse::error("Team member not found or not authorized");
        }

        ApiResponse::success(null, "Team member deleted successfully");

    } catch (Exception $e) {
        error_log("Delete team member error: " . $e->getMessage());
        ApiResponse::error("Failed to delete team member");
    }
}

// ==================== SERVICES ====================

function getServices($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    $category = Request::get('category', '');

    try {
        $query = "SELECT * FROM services WHERE organizer_id = :organizer_id";
        $params = [':organizer_id' => $organizerId];

        if ($category) {
            $query .= " AND category = :category";
            $params[':category'] = $category;
        }

        $query .= " ORDER BY service_name ASC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        ApiResponse::success($stmt->fetchAll());

    } catch (Exception $e) {
        error_log("Get services error: " . $e->getMessage());
        ApiResponse::error("Failed to fetch services");
    }
}

function createService($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    $data = Request::getJson();

    $validator = new Validator();
    $validator->required($data['service_name'] ?? '', 'service_name')
              ->required($data['category'] ?? '', 'category')
              ->required($data['base_price'] ?? '', 'base_price');

    if (!$validator->isValid()) {
        ApiResponse::validationError($validator->getErrors());
    }

    try {
        $query = "INSERT INTO services (
                    organizer_id, service_name, category, description,
                    base_price, price_unit, service_image
                ) VALUES (
                    :organizer_id, :service_name, :category, :description,
                    :base_price, :price_unit, :service_image
                )";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':organizer_id', $organizerId);
        $stmt->bindParam(':service_name', $data['service_name']);
        $stmt->bindParam(':category', $data['category']);
        $stmt->bindParam(':description', $data['description'] ?? null);
        $stmt->bindParam(':base_price', $data['base_price']);
        $stmt->bindParam(':price_unit', $data['price_unit'] ?? 'fixed');
        $stmt->bindParam(':service_image', $data['service_image'] ?? null);
        
        $stmt->execute();
        $serviceId = $db->lastInsertId();

        ApiResponse::success(['service_id' => $serviceId], "Service created successfully", 201);

    } catch (Exception $e) {
        error_log("Create service error: " . $e->getMessage());
        ApiResponse::error("Failed to create service");
    }
}

function updateService($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    $data = Request::getJson();
    $serviceId = $data['service_id'] ?? null;

    if (!$serviceId) {
        ApiResponse::error("Service ID is required");
    }

    try {
        $updates = [];
        $params = [':service_id' => $serviceId, ':organizer_id' => $organizerId];
        
        $allowedFields = ['service_name', 'category', 'description', 'base_price', 'price_unit', 'is_available', 'service_image'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($updates)) {
            ApiResponse::error("No fields to update");
        }

        $query = "UPDATE services SET " . implode(', ', $updates) . 
                " WHERE id = :service_id AND organizer_id = :organizer_id";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);

        if ($stmt->rowCount() == 0) {
            ApiResponse::error("Service not found or not authorized");
        }

        ApiResponse::success(null, "Service updated successfully");

    } catch (Exception $e) {
        error_log("Update service error: " . $e->getMessage());
        ApiResponse::error("Failed to update service");
    }
}

function deleteService($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    $serviceId = Request::get('service_id');

    if (!$serviceId) {
        ApiResponse::error("Service ID is required");
    }

    try {
        $query = "DELETE FROM services WHERE id = :service_id AND organizer_id = :organizer_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':service_id', $serviceId);
        $stmt->bindParam(':organizer_id', $organizerId);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            ApiResponse::error("Service not found or not authorized");
        }

        ApiResponse::success(null, "Service deleted successfully");

    } catch (Exception $e) {
        error_log("Delete service error: " . $e->getMessage());
        ApiResponse::error("Failed to delete service");
    }
}

// ==================== PAYMENTS ====================

function getPayments($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    
    $status = Request::get('status', '');
    $startDate = Request::get('start_date', '');
    $endDate = Request::get('end_date', '');

    try {
        $query = "SELECT * FROM payments WHERE organizer_id = :organizer_id";
        $params = [':organizer_id' => $organizerId];

        if ($status) {
            $query .= " AND payment_status = :status";
            $params[':status'] = $status;
        }

        if ($startDate) {
            $query .= " AND payment_date >= :start_date";
            $params[':start_date'] = $startDate;
        }

        if ($endDate) {
            $query .= " AND payment_date <= :end_date";
            $params[':end_date'] = $endDate;
        }

        $query .= " ORDER BY payment_date DESC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        ApiResponse::success($stmt->fetchAll());

    } catch (Exception $e) {
        error_log("Get payments error: " . $e->getMessage());
        ApiResponse::error("Failed to fetch payments");
    }
}

function recordPayment($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    $data = Request::getJson();

    $validator = new Validator();
    $validator->required($data['amount'] ?? '', 'amount')
              ->required($data['payment_method'] ?? '', 'payment_method');

    if (!$validator->isValid()) {
        ApiResponse::validationError($validator->getErrors());
    }

    try {
        $transactionId = 'TXN-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 8));

        $query = "INSERT INTO payments (
                    organizer_id, event_id, transaction_id, payment_type, amount,
                    payment_method, payment_status, payment_date, client_name, 
                    client_email, reference_number, notes
                ) VALUES (
                    :organizer_id, :event_id, :transaction_id, :payment_type, :amount,
                    :payment_method, :payment_status, :payment_date, :client_name,
                    :client_email, :reference_number, :notes
                )";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':organizer_id', $organizerId);
        $stmt->bindParam(':event_id', $data['event_id'] ?? null);
        $stmt->bindParam(':transaction_id', $transactionId);
        $stmt->bindParam(':payment_type', $data['payment_type'] ?? 'partial');
        $stmt->bindParam(':amount', $data['amount']);
        $stmt->bindParam(':payment_method', $data['payment_method']);
        $stmt->bindParam(':payment_status', $data['payment_status'] ?? 'completed');
        $stmt->bindParam(':payment_date', $data['payment_date'] ?? date('Y-m-d H:i:s'));
        $stmt->bindParam(':client_name', $data['client_name'] ?? null);
        $stmt->bindParam(':client_email', $data['client_email'] ?? null);
        $stmt->bindParam(':reference_number', $data['reference_number'] ?? null);
        $stmt->bindParam(':notes', $data['notes'] ?? null);
        
        $stmt->execute();
        $paymentId = $db->lastInsertId();

        ApiResponse::success([
            'payment_id' => $paymentId,
            'transaction_id' => $transactionId
        ], "Payment recorded successfully", 201);

    } catch (Exception $e) {
        error_log("Record payment error: " . $e->getMessage());
        ApiResponse::error("Failed to record payment");
    }
}

function getRevenueStats($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    $period = Request::get('period', 'month'); // month, quarter, year

    try {
        $dateFilter = match($period) {
            'month' => "DATE_SUB(CURDATE(), INTERVAL 1 MONTH)",
            'quarter' => "DATE_SUB(CURDATE(), INTERVAL 3 MONTH)",
            'year' => "DATE_SUB(CURDATE(), INTERVAL 1 YEAR)",
            default => "DATE_SUB(CURDATE(), INTERVAL 1 MONTH)"
        };

        $query = "SELECT 
                    SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END) as total_received,
                    SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END) as total_pending,
                    COUNT(*) as total_transactions,
                    AVG(CASE WHEN payment_status = 'completed' THEN amount END) as avg_transaction
                 FROM payments
                 WHERE organizer_id = :organizer_id
                 AND payment_date >= $dateFilter";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':organizer_id', $organizerId);
        $stmt->execute();
        
        $stats = $stmt->fetch();

        // Get revenue breakdown by payment method
        $methodQuery = "SELECT payment_method, SUM(amount) as total
                       FROM payments
                       WHERE organizer_id = :organizer_id 
                       AND payment_status = 'completed'
                       AND payment_date >= $dateFilter
                       GROUP BY payment_method";
        
        $methodStmt = $db->prepare($methodQuery);
        $methodStmt->bindParam(':organizer_id', $organizerId);
        $methodStmt->execute();
        $byMethod = $methodStmt->fetchAll();

        ApiResponse::success([
            'stats' => $stats,
            'by_method' => $byMethod
        ]);

    } catch (Exception $e) {
        error_log("Get revenue stats error: " . $e->getMessage());
        ApiResponse::error("Failed to fetch revenue stats");
    }
}

// ==================== INVOICES ====================

function getInvoices($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    $status = Request::get('status', '');

    try {
        $query = "SELECT * FROM invoices WHERE organizer_id = :organizer_id";
        $params = [':organizer_id' => $organizerId];

        if ($status) {
            $query .= " AND status = :status";
            $params[':status'] = $status;
        }

        $query .= " ORDER BY invoice_date DESC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        $invoices = $stmt->fetchAll();

        foreach ($invoices as &$invoice) {
            if ($invoice['items']) {
                $invoice['items'] = json_decode($invoice['items'], true);
            }
        }

        ApiResponse::success($invoices);

    } catch (Exception $e) {
        error_log("Get invoices error: " . $e->getMessage());
        ApiResponse::error("Failed to fetch invoices");
    }
}

function createInvoice($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    $data = Request::getJson();

    $validator = new Validator();
    $validator->required($data['client_name'] ?? '', 'client_name')
              ->required($data['total_amount'] ?? '', 'total_amount')
              ->required($data['items'] ?? '', 'items');

    if (!$validator->isValid()) {
        ApiResponse::validationError($validator->getErrors());
    }

    try {
        $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
        $balanceDue = $data['total_amount'] - ($data['paid_amount'] ?? 0);

        $query = "INSERT INTO invoices (
                    organizer_id, event_id, invoice_number, client_name, client_email,
                    client_phone, client_address, subtotal, discount_amount, tax_amount,
                    total_amount, paid_amount, balance_due, items, invoice_date, due_date, status, notes
                ) VALUES (
                    :organizer_id, :event_id, :invoice_number, :client_name, :client_email,
                    :client_phone, :client_address, :subtotal, :discount_amount, :tax_amount,
                    :total_amount, :paid_amount, :balance_due, :items, :invoice_date, :due_date, :status, :notes
                )";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':organizer_id', $organizerId);
        $stmt->bindParam(':event_id', $data['event_id'] ?? null);
        $stmt->bindParam(':invoice_number', $invoiceNumber);
        $stmt->bindParam(':client_name', $data['client_name']);
        $stmt->bindParam(':client_email', $data['client_email'] ?? null);
        $stmt->bindParam(':client_phone', $data['client_phone'] ?? null);
        $stmt->bindParam(':client_address', $data['client_address'] ?? null);
        $stmt->bindParam(':subtotal', $data['subtotal'] ?? $data['total_amount']);
        $stmt->bindParam(':discount_amount', $data['discount_amount'] ?? 0);
        $stmt->bindParam(':tax_amount', $data['tax_amount'] ?? 0);
        $stmt->bindParam(':total_amount', $data['total_amount']);
        $stmt->bindParam(':paid_amount', $data['paid_amount'] ?? 0);
        $stmt->bindParam(':balance_due', $balanceDue);
        $stmt->bindParam(':items', json_encode($data['items']));
        $stmt->bindParam(':invoice_date', $data['invoice_date'] ?? date('Y-m-d'));
        $stmt->bindParam(':due_date', $data['due_date'] ?? null);
        $stmt->bindParam(':status', $data['status'] ?? 'draft');
        $stmt->bindParam(':notes', $data['notes'] ?? null);
        
        $stmt->execute();
        $invoiceId = $db->lastInsertId();

        ApiResponse::success([
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber
        ], "Invoice created successfully", 201);

    } catch (Exception $e) {
        error_log("Create invoice error: " . $e->getMessage());
        ApiResponse::error("Failed to create invoice");
    }
}

function updateInvoice($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    $data = Request::getJson();
    $invoiceId = $data['invoice_id'] ?? null;

    if (!$invoiceId) {
        ApiResponse::error("Invoice ID is required");
    }

    try {
        $updates = [];
        $params = [':invoice_id' => $invoiceId, ':organizer_id' => $organizerId];
        
        $allowedFields = ['status', 'paid_amount', 'notes'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        // Update balance due if paid amount changes
        if (isset($data['paid_amount'])) {
            // Get total amount first
            $getQuery = "SELECT total_amount FROM invoices WHERE id = :invoice_id";
            $getStmt = $db->prepare($getQuery);
            $getStmt->bindParam(':invoice_id', $invoiceId);
            $getStmt->execute();
            $result = $getStmt->fetch();
            
            $balanceDue = $result['total_amount'] - $data['paid_amount'];
            $updates[] = "balance_due = :balance_due";
            $params[':balance_due'] = $balanceDue;

            // Auto-update status based on payment
            if ($balanceDue == 0) {
                $updates[] = "status = 'paid'";
            } elseif ($data['paid_amount'] > 0) {
                $updates[] = "status = 'partial'";
            }
        }

        if (empty($updates)) {
            ApiResponse::error("No fields to update");
        }

        $query = "UPDATE invoices SET " . implode(', ', $updates) . 
                " WHERE id = :invoice_id AND organizer_id = :organizer_id";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);

        if ($stmt->rowCount() == 0) {
            ApiResponse::error("Invoice not found or not authorized");
        }

        ApiResponse::success(null, "Invoice updated successfully");

    } catch (Exception $e) {
        error_log("Update invoice error: " . $e->getMessage());
        ApiResponse::error("Failed to update invoice");
    }
}

// ==================== REVIEWS ====================

function getReviews($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];

    try {
        $query = "SELECT r.*, e.title as event_title
                 FROM reviews r
                 LEFT JOIN events e ON r.event_id = e.id
                 WHERE r.organizer_id = :organizer_id
                 ORDER BY r.created_at DESC";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':organizer_id', $organizerId);
        $stmt->execute();
        
        ApiResponse::success($stmt->fetchAll());

    } catch (Exception $e) {
        error_log("Get reviews error: " . $e->getMessage());
        ApiResponse::error("Failed to fetch reviews");
    }
}

function respondToReview($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    $data = Request::getJson();

    $validator = new Validator();
    $validator->required($data['review_id'] ?? '', 'review_id')
              ->required($data['response'] ?? '', 'response');

    if (!$validator->isValid()) {
        ApiResponse::validationError($validator->getErrors());
    }

    try {
        $query = "UPDATE reviews SET response = :response 
                 WHERE id = :review_id AND organizer_id = :organizer_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':response', $data['response']);
        $stmt->bindParam(':review_id', $data['review_id']);
        $stmt->bindParam(':organizer_id', $organizerId);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            ApiResponse::error("Review not found or not authorized");
        }

        ApiResponse::success(null, "Response posted successfully");

    } catch (Exception $e) {
        error_log("Respond to review error: " . $e->getMessage());
        ApiResponse::error("Failed to post response");
    }
}

// ==================== ANALYTICS ====================

function getAnalytics($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    $startDate = Request::get('start_date', date('Y-m-01'));
    $endDate = Request::get('end_date', date('Y-m-d'));

    try {
        // Event statistics
        $eventStatsQuery = "SELECT 
                category,
                COUNT(*) as count,
                SUM(total_revenue) as revenue
            FROM events
            WHERE organizer_id = :organizer_id
            AND event_date BETWEEN :start_date AND :end_date
            GROUP BY category";

        $eventStatsStmt = $db->prepare($eventStatsQuery);
        $eventStatsStmt->bindParam(':organizer_id', $organizerId);
        $eventStatsStmt->bindParam(':start_date', $startDate);
        $eventStatsStmt->bindParam(':end_date', $endDate);
        $eventStatsStmt->execute();
        $eventsByCategory = $eventStatsStmt->fetchAll();

        // Booking conversion rate
        $conversionQuery = "SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted,
                (SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) / COUNT(*) * 100) as conversion_rate
            FROM booking_requests
            WHERE organizer_id = :organizer_id
            AND created_at BETWEEN :start_date AND :end_date";

        $conversionStmt = $db->prepare($conversionQuery);
        $conversionStmt->bindParam(':organizer_id', $organizerId);
        $conversionStmt->bindParam(':start_date', $startDate);
        $conversionStmt->bindParam(':end_date', $endDate);
        $conversionStmt->execute();
        $conversionStats = $conversionStmt->fetch();

        // Top performing team members
        $topTeamQuery = "SELECT 
                tm.name, tm.role,
                COUNT(eta.id) as events_count,
                SUM(eta.payment_amount) as total_earnings
            FROM team_members tm
            INNER JOIN event_team_assignments eta ON tm.id = eta.team_member_id
            INNER JOIN events e ON eta.event_id = e.id
            WHERE tm.organizer_id = :organizer_id
            AND e.event_date BETWEEN :start_date AND :end_date
            GROUP BY tm.id
            ORDER BY events_count DESC
            LIMIT 10";

        $topTeamStmt = $db->prepare($topTeamQuery);
        $topTeamStmt->bindParam(':organizer_id', $organizerId);
        $topTeamStmt->bindParam(':start_date', $startDate);
        $topTeamStmt->bindParam(':end_date', $endDate);
        $topTeamStmt->execute();
        $topTeam = $topTeamStmt->fetchAll();

        ApiResponse::success([
            'events_by_category' => $eventsByCategory,
            'conversion_stats' => $conversionStats,
            'top_team_members' => $topTeam
        ]);

    } catch (Exception $e) {
        error_log("Get analytics error: " . $e->getMessage());
        ApiResponse::error("Failed to fetch analytics");
    }
}

function getEventTrends($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    $months = Request::get('months', 6);

    try {
        $query = "SELECT 
                DATE_FORMAT(event_date, '%Y-%m') as month,
                category,
                COUNT(*) as count
            FROM events
            WHERE organizer_id = :organizer_id
            AND event_date >= DATE_SUB(CURDATE(), INTERVAL :months MONTH)
            GROUP BY DATE_FORMAT(event_date, '%Y-%m'), category
            ORDER BY month ASC";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':organizer_id', $organizerId);
        $stmt->bindParam(':months', $months, PDO::PARAM_INT);
        $stmt->execute();
        
        ApiResponse::success($stmt->fetchAll());

    } catch (Exception $e) {
        error_log("Get event trends error: " . $e->getMessage());
        ApiResponse::error("Failed to fetch event trends");
    }
}

function getRevenueTrends($db) {
    $user = JWT::verifyToken();
    $organizerId = $user['organizer_id'];
    $months = Request::get('months', 12);

    try {
        $query = "SELECT 
                DATE_FORMAT(payment_date, '%Y-%m') as month,
                SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END) as revenue,
                COUNT(*) as transactions
            FROM payments
            WHERE organizer_id = :organizer_id
            AND payment_date >= DATE_SUB(CURDATE(), INTERVAL :months MONTH)
            GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
            ORDER BY month ASC";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':organizer_id', $organizerId);
        $stmt->bindParam(':months', $months, PDO::PARAM_INT);
        $stmt->execute();
        
        ApiResponse::success($stmt->fetchAll());

    } catch (Exception $e) {
        error_log("Get revenue trends error: " . $e->getMessage());
        ApiResponse::error("Failed to fetch revenue trends");
    }
}

// ==================== NOTIFICATIONS ====================

function getNotifications($db) {
    $user = JWT::verifyToken();
    $unreadOnly = Request::get('unread_only', false);
    $limit = Request::get('limit', 20);

    try {
        $query = "SELECT * FROM notifications WHERE user_id = :user_id";
        
        if ($unreadOnly) {
            $query .= " AND is_read = 0";
        }

        $query .= " ORDER BY created_at DESC LIMIT :limit";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user['user_id']);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        ApiResponse::success($stmt->fetchAll());

    } catch (Exception $e) {
        error_log("Get notifications error: " . $e->getMessage());
        ApiResponse::error("Failed to fetch notifications");
    }
}

function markNotificationRead($db) {
    $user = JWT::verifyToken();
    $notificationId = Request::get('notification_id');

    if (!$notificationId) {
        ApiResponse::error("Notification ID is required");
    }

    try {
        $query = "UPDATE notifications SET is_read = 1, read_at = NOW() 
                 WHERE id = :notification_id AND user_id = :user_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':notification_id', $notificationId);
        $stmt->bindParam(':user_id', $user['user_id']);
        $stmt->execute();

        ApiResponse::success(null, "Notification marked as read");

    } catch (Exception $e) {
        error_log("Mark notification read error: " . $e->getMessage());
        ApiResponse::error("Failed to mark notification as read");
    }
}

// ==================== HELPER FUNCTIONS ====================

function logActivity($db, $userId, $action, $entityType, $entityId, $description) {
    try {
        $query = "INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description) 
                 VALUES (:user_id, :action, :entity_type, :entity_id, :description)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':entity_type', $entityType);
        $stmt->bindParam(':entity_id', $entityId);
        $stmt->bindParam(':description', $description);
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}

?>