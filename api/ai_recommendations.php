<?php
// =============================================
// AI RECOMMENDATION ENGINE - FIXED VERSION
// File: ai_recommendations.php
// =============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

class AIRecommendationEngine {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // =============================================
    // NORMALIZE EVENT TYPE
    // =============================================
    public function normalizeEventType($eventType) {
        $eventType = trim($eventType);
        
        // Map common variations to database values
        $eventTypeMap = [
            'birthday' => 'Birthday Party',
            'birthday party' => 'Birthday Party',
            'wedding' => 'Wedding',
            'corporate' => 'Corporate Event',
            'corporate event' => 'Corporate Event',
            'party' => 'Party',
            'other' => 'Other'
        ];
        
        $normalized = strtolower($eventType);
        
        if (isset($eventTypeMap[$normalized])) {
            return $eventTypeMap[$normalized];
        }
        
        // Return original if no mapping found
        return $eventType;
    }
    
    // =============================================
    // INTELLIGENT RECOMMENDATION ENGINE
    // =============================================
    public function getRecommendations($requirements) {
        $packages = $this->getAllPackages($requirements['event_type']);
        
        if (empty($packages)) {
            return [];
        }
        
        $scoredPackages = [];
        
        foreach ($packages as $package) {
            $score = $this->intelligentScore($package, $requirements);
            
            if ($score >= 40) {
                $scoredPackages[] = [
                    'packageDetails' => [
                        'id' => (int)$package['id'],
                        'name' => $package['name'],
                        'description' => $package['description'],
                        'price' => (float)$package['price'],
                        'rating' => isset($package['rating']) ? (float)$package['rating'] : 4.5,
                        'imageUrl' => $package['image'] ?? null,
                        'eventType' => $package['event_type']
                    ],
                    'score' => (float)$score,
                    'matchReasons' => $this->getSmartMatchReasons($package, $requirements, $score),
                    'aiExplanation' => $this->generateSmartExplanation($package, $requirements, $score)
                ];
            }
        }
        
        usort($scoredPackages, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        return array_slice($scoredPackages, 0, 3);
    }
    
    private function getAllPackages($eventType) {
        // Filter by exact event type match
        $query = "SELECT * FROM packages WHERE status = 'active' AND event_type = ? ORDER BY rating DESC, price ASC";
        $stmt = $this->conn->prepare($query);
        
        if (!$stmt) {
            error_log("Query prepare error: " . $this->conn->error);
            return [];
        }
        
        $stmt->bind_param("s", $eventType);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result) {
            error_log("Query execution error: " . $this->conn->error);
            return [];
        }
        
        $packages = [];
        while ($row = $result->fetch_assoc()) {
            $packages[] = $row;
        }
        
        $stmt->close();
        return $packages;
    }
    
    // =============================================
    // INTELLIGENT SCORING ALGORITHM
    // =============================================
    private function intelligentScore($package, $requirements) {
        $score = 0;
        $packagePrice = (float)$package['price'];
        $userBudget = (float)$requirements['budget'];
        $guestCount = (int)$requirements['guest_count'];
        $requiredServices = array_map('strtolower', $requirements['services']);
        $packageName = strtolower($package['name']);
        
        // 1. EVENT TYPE MATCH (15 points)
        if (strtolower($package['event_type']) === strtolower($requirements['event_type'])) {
            $score += 15;
        }
        
        // 2. BUDGET MATCH (40 points)
        $budgetDiff = abs($packagePrice - $userBudget);
        $budgetPercentDiff = $budgetDiff / $userBudget;
        
        if ($budgetPercentDiff <= 0.05) {
            $score += 40;
        } elseif ($budgetPercentDiff <= 0.15) {
            $score += 35;
        } elseif ($budgetPercentDiff <= 0.25) {
            $score += 28;
        } elseif ($budgetPercentDiff <= 0.40) {
            $score += 20;
        } else {
            $score += 5;
        }
        
        // Bonus if under budget
        if ($packagePrice < $userBudget && $packagePrice >= $userBudget * 0.7) {
            $score += 5;
        }
        
        // 3. SERVICE MATCHING (30 points)
        $serviceMatchScore = $this->matchServices($packageName, $package['description'], $requiredServices);
        $score += $serviceMatchScore;
        
        // 4. GUEST CAPACITY (10 points)
        $guestScore = $this->matchGuestCount($packageName, $packagePrice, $guestCount);
        $score += $guestScore;
        
        // 5. RATING BONUS (5 points)
        $rating = isset($package['rating']) ? (float)$package['rating'] : 4.0;
        $score += ($rating / 5) * 5;
        
        return round($score, 2);
    }
    
    private function matchServices($packageName, $description, $requiredServices) {
        $score = 0;
        $maxScore = 30;
        
        $packageText = strtolower($packageName . ' ' . $description);
        $matchedCount = 0;
        $totalServices = count($requiredServices);
        
        foreach ($requiredServices as $service) {
            $service = trim($service);
            
            if ($this->serviceMatches($packageText, $service)) {
                $matchedCount++;
            }
        }
        
        if ($totalServices > 0) {
            $matchRatio = $matchedCount / $totalServices;
            $score = $matchRatio * $maxScore;
        }
        
        return $score;
    }
    
    private function serviceMatches($packageText, $service) {
        $serviceKeywords = [
            'photography' => ['photo', 'photograph', 'camera', 'pictures'],
            'catering' => ['catering', 'food', 'dining', 'buffet', 'meal', 'cuisine', 'gourmet'],
            'decoration' => ['decoration', 'decor', 'design', 'setup', 'theme'],
            'venue' => ['venue', 'hall', 'space', 'location', 'resort', 'hotel'],
            'entertainment' => ['entertainment', 'band', 'orchestra', 'performance', 'show'],
            'dj' => ['dj', 'music', 'sound', 'audio'],
            'videography' => ['video', 'videograph', 'film', 'coverage']
        ];
        
        $service = strtolower($service);
        
        if (strpos($packageText, $service) !== false) {
            return true;
        }
        
        foreach ($serviceKeywords as $category => $keywords) {
            if (strpos($service, $category) !== false || in_array($service, $keywords)) {
                foreach ($keywords as $keyword) {
                    if (strpos($packageText, $keyword) !== false) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
    
    private function matchGuestCount($packageName, $packagePrice, $guestCount) {
        $score = 0;
        
        if ($packagePrice < 50000) {
            $idealMin = 10;
            $idealMax = 100;
        } elseif ($packagePrice < 150000) {
            $idealMin = 50;
            $idealMax = 300;
        } elseif ($packagePrice < 350000) {
            $idealMin = 100;
            $idealMax = 500;
        } else {
            $idealMin = 200;
            $idealMax = 2000;
        }
        
        if ($guestCount >= $idealMin && $guestCount <= $idealMax) {
            $score = 10;
        } elseif ($guestCount < $idealMin && $guestCount >= $idealMin * 0.7) {
            $score = 7;
        } elseif ($guestCount > $idealMax && $guestCount <= $idealMax * 1.3) {
            $score = 7;
        } else {
            $score = 3;
        }
        
        return $score;
    }
    
    private function getSmartMatchReasons($package, $requirements, $score) {
        $reasons = [];
        $packagePrice = (float)$package['price'];
        $userBudget = (float)$requirements['budget'];
        $guestCount = (int)$requirements['guest_count'];
        
        $budgetDiff = abs($packagePrice - $userBudget);
        
        if ($packagePrice <= $userBudget) {
            $saved = $userBudget - $packagePrice;
            if ($saved > 0) {
                $reasons[] = "Saves you ₹" . number_format($saved) . " from your budget";
            } else {
                $reasons[] = "Perfect match for your ₹" . number_format($userBudget) . " budget";
            }
        } else {
            $extra = $packagePrice - $userBudget;
            $reasons[] = "Just ₹" . number_format($extra) . " above budget - Great value";
        }
        
        $requiredServices = array_map('strtolower', $requirements['services']);
        $packageText = strtolower($package['name'] . ' ' . $package['description']);
        $matchedServices = [];
        
        foreach ($requiredServices as $service) {
            if ($this->serviceMatches($packageText, $service)) {
                $matchedServices[] = ucfirst($service);
            }
        }
        
        if (count($matchedServices) === count($requiredServices)) {
            $reasons[] = "Includes all your preferred services";
        } elseif (count($matchedServices) > 0) {
            $reasons[] = "Includes " . implode(", ", array_slice($matchedServices, 0, 3));
        }
        
        $reasons[] = "Suitable for " . $guestCount . " guests";
        
        $rating = isset($package['rating']) ? (float)$package['rating'] : 4.0;
        if ($rating >= 4.7) {
            $reasons[] = "Excellent " . number_format($rating, 1) . "⭐ rating";
        } elseif ($rating >= 4.3) {
            $reasons[] = "Highly rated " . number_format($rating, 1) . "⭐";
        }
        
        return $reasons;
    }
    
    private function generateSmartExplanation($package, $requirements, $score) {
        $packagePrice = (float)$package['price'];
        $userBudget = (float)$requirements['budget'];
        $rating = isset($package['rating']) ? (float)$package['rating'] : 4.0;
        
        $explanation = "";
        
        if ($score >= 85) {
            $explanation = "This package is a perfect match for your " . $requirements['event_type'] . "! ";
        } elseif ($score >= 70) {
            $explanation = "This is an excellent choice for your " . $requirements['event_type'] . ". ";
        } else {
            $explanation = "This is a good option for your " . $requirements['event_type'] . ". ";
        }
        
        if ($packagePrice <= $userBudget) {
            $explanation .= "It fits perfectly within your budget";
        } else {
            $explanation .= "It offers premium features";
        }
        
        if ($rating >= 4.5) {
            $explanation .= " and has excellent reviews from our clients!";
        } else {
            $explanation .= " and offers quality service for your event.";
        }
        
        return $explanation;
    }
}

// =============================================
// API ENDPOINT
// =============================================

$method = $_SERVER['REQUEST_METHOD'];
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';

try {
    $database = new Database();
    $db = $database->getConnection();
    $engine = new AIRecommendationEngine($db);
    
    if ($endpoint === 'get_recommendations') {
        if ($method !== 'POST') {
            throw new Exception('Method not allowed');
        }
        
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            throw new Exception('Invalid JSON data');
        }
        
        if (!isset($data['event_type']) || !isset($data['budget']) || 
            !isset($data['guest_count']) || !isset($data['services']) || 
            !isset($data['date'])) {
            throw new Exception('Missing required fields');
        }
        
        $requirements = [
            'user_id' => isset($data['user_id']) ? $data['user_id'] : 1,
            'conversation_id' => isset($data['conversation_id']) ? $data['conversation_id'] : uniqid('conv_'),
            'event_type' => $engine->normalizeEventType($data['event_type']),
            'budget' => floatval($data['budget']),
            'guest_count' => intval($data['guest_count']),
            'services' => $data['services'],
            'date' => $data['date']
        ];
        
        $recommendations = $engine->getRecommendations($requirements);
        
        echo json_encode([
            'success' => true,
            'conversation_id' => $requirements['conversation_id'],
            'recommendations' => [
                'packages' => $recommendations
            ],
            'message' => count($recommendations) > 0 
                ? 'Found ' . count($recommendations) . ' perfect matches for you!' 
                : 'No exact matches found, but here are some great options!'
        ]);
        
    } else {
        throw new Exception('Invalid endpoint');
    }
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}
?>