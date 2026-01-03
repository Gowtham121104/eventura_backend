<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        $organizer_id = isset($_GET['organizer_id']) ? $_GET['organizer_id'] : die(json_encode(["message" => "Organizer ID required"]));
        
        $query = "SELECT e.*, 
                  COUNT(DISTINCT b.id) as totalBookings,
                  COUNT(DISTINCT CASE WHEN b.status = 'confirmed' THEN b.id END) as confirmedBookings,
                  COUNT(DISTINCT CASE WHEN b.status = 'pending' THEN b.id END) as pendingBookings
                  FROM events e
                  LEFT JOIN bookings b ON e.id = b.event_id
                  WHERE e.organizer_id = :organizer_id
                  GROUP BY e.id
                  ORDER BY e.event_date DESC";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":organizer_id", $organizer_id);
        $stmt->execute();
        
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($events);
        break;
        
    case 'POST':
        $data = $_POST;
        
        $query = "INSERT INTO events (organizer_id, title, description, event_date, start_time, end_time, location, venue, category, price, max_attendees, image_url, status, created_at)
                  VALUES (:organizer_id, :title, :description, :event_date, :start_time, :end_time, :location, :venue, :category, :price, :max_attendees, :image_url, 'upcoming', NOW())";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":organizer_id", $data['organizer_id']);
        $stmt->bindParam(":title", $data['title']);
        $stmt->bindParam(":description", $data['description']);
        $stmt->bindParam(":event_date", $data['event_date']);
        $stmt->bindParam(":start_time", $data['start_time']);
        $stmt->bindParam(":end_time", $data['end_time']);
        $stmt->bindParam(":location", $data['location']);
        $stmt->bindParam(":venue", $data['venue']);
        $stmt->bindParam(":category", $data['category']);
        $stmt->bindParam(":price", $data['price']);
        $stmt->bindParam(":max_attendees", $data['max_attendees']);
        $stmt->bindParam(":image_url", $data['image_url']);
        
        if($stmt->execute()) {
            $event_id = $db->lastInsertId();
            echo json_encode(["message" => "Event created", "id" => $event_id]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to create event"]);
        }
        break;
        
    case 'PUT':
        parse_str(file_get_contents("php://input"), $data);
        
        $query = "UPDATE events SET title = :title, description = :description, event_date = :event_date, 
                  start_time = :start_time, end_time = :end_time, location = :location, venue = :venue, 
                  category = :category, price = :price, max_attendees = :max_attendees, image_url = :image_url, status = :status
                  WHERE id = :event_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":event_id", $data['event_id']);
        $stmt->bindParam(":title", $data['title']);
        $stmt->bindParam(":description", $data['description']);
        $stmt->bindParam(":event_date", $data['event_date']);
        $stmt->bindParam(":start_time", $data['start_time']);
        $stmt->bindParam(":end_time", $data['end_time']);
        $stmt->bindParam(":location", $data['location']);
        $stmt->bindParam(":venue", $data['venue']);
        $stmt->bindParam(":category", $data['category']);
        $stmt->bindParam(":price", $data['price']);
        $stmt->bindParam(":max_attendees", $data['max_attendees']);
        $stmt->bindParam(":image_url", $data['image_url']);
        $stmt->bindParam(":status", $data['status']);
        
        if($stmt->execute()) {
            echo json_encode(["message" => "Event updated"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to update event"]);
        }
        break;
        
    case 'DELETE':
        $event_id = isset($_GET['event_id']) ? $_GET['event_id'] : die(json_encode(["message" => "Event ID required"]));
        
        $query = "DELETE FROM events WHERE id = :event_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":event_id", $event_id);
        
        if($stmt->execute()) {
            echo json_encode(["message" => "Event deleted"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to delete event"]);
        }
        break;
}
?>