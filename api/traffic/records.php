<?php
// api/traffic/records.php
error_reporting(0);
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
session_start();
include_once '../../config.php';

// Check if user is authenticated
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized - Please login first']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents("php://input"));

// For GET requests with id parameter (single record)
if ($method === 'GET' && isset($_GET['id'])) {
    try {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("SELECT recordID, sensorID, location, timestamp, trafficFlow, congestionLevel, numberOfRoadAccidents 
                               FROM Traffic_Data_T WHERE recordID = ?");
        $stmt->execute([$id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($record) {
            echo json_encode($record);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Record not found']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

try {
    if ($method === 'GET') {
        // Fetch all traffic records
        $sql = "SELECT recordID, sensorID, location, timestamp, trafficFlow, congestionLevel, numberOfRoadAccidents 
                FROM Traffic_Data_T ORDER BY timestamp DESC";
        $stmt = $pdo->query($sql);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($records);
    } 
    elseif ($method === 'POST') {
        // Add Record - Only admin can add
        if ($_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden - Admin access required']);
            exit;
        }
        
        $sql = "INSERT INTO Traffic_Data_T (sensorID, location, timestamp, trafficFlow, congestionLevel, numberOfRoadAccidents) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $input->sensorID,
            $input->location,
            $input->timestamp,
            $input->trafficFlow,
            $input->congestionLevel,
            $input->numberOfRoadAccidents
        ]);
        echo json_encode(['success' => true, 'message' => 'Record Added Successfully']);
    } 
    elseif ($method === 'PUT') {
        // Update Record - Only admin can update
        if ($_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden - Admin access required']);
            exit;
        }
        
        $sql = "UPDATE Traffic_Data_T SET 
                sensorID=?, location=?, timestamp=?, trafficFlow=?, congestionLevel=?, numberOfRoadAccidents=? 
                WHERE recordID=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $input->sensorID,
            $input->location,
            $input->timestamp,
            $input->trafficFlow,
            $input->congestionLevel,
            $input->numberOfRoadAccidents,
            $input->recordID
        ]);
        echo json_encode(['success' => true, 'message' => 'Record Updated Successfully']);
    } 
    elseif ($method === 'DELETE') {
        // Delete Record - Only admin can delete
        if ($_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden - Admin access required']);
            exit;
        }
        
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Record ID is required']);
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM Traffic_Data_T WHERE recordID=?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Record Deleted Successfully']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
?>