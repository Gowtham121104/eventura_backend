<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

if(
    !empty($data->title) &&
    !empty($data->organizer_id) &&
    !empty($data->event_date) &&
    !empty($data->location)
) {
    try {
        $query = "INSERT INTO events 
                  (organizer_id, title, description, event_date, start_time, end_time, 
                   location, venue, category, price, max_attendees, image_url, status, created_at)
                  VALUES 
                  (:organizer_id, :title, :description, :event_date, :start_time, :end_time,
                   :location, :venue, :category, :price, :max_attendees, :image_url, 'upcoming', NOW())";
        
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(':organizer_id', $data->organizer_id);
        $stmt->bindParam(':title', $data->title);
        $stmt->bindParam(':description', $data->description);
        $stmt->bindParam(':event_date', $data->event_date);
        $stmt->bindParam(':start_time', $data->start_time);
        $stmt->bindParam(':end_time', $data->end_time);
        $stmt->bindParam(':location', $data->location);
        $stmt->bindParam(':venue', $data->venue);
        $stmt->bindParam(':category', $data->category);
        $stmt->bindParam(':price', $data->price);
        $stmt->bindParam(':max_attendees', $data->max_attendees);
        $stmt->bindParam(':image_url', $data->image_url);
        
        if($stmt->execute()) {
            http_response_code(201);
            echo json_json(array(
                "success" => true,
                "message" => "Event created successfully",
                "event_id" => $db->lastInsertId()
            ));
        } else {
            http_response_code(500);
            echo json_encode(array(
                "success" => false,
                "message" => "Failed to create event"
            ));
        }
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(array(
            "success" => false,
            "message" => "Error: " . $e->getMessage()
        ));
    }
} else {
    http_response_code(400);
    echo json_encode(array(
        "success" => false,
        "message" => "Incomplete data"
    ));
}
?>