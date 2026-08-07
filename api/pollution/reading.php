<?php
// api/pollution/reading.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
include_once '../../config.php';

$method = $_SERVER['REQUEST_METHOD'];

// Handle preflight requests
if ($method == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$sensorID = isset($_GET['sensorID']) ? $_GET['sensorID'] : null;
$timestamp = isset($_GET['timestamp']) ? $_GET['timestamp'] : null;

switch ($method) {
    case 'GET':
        if ($sensorID && $timestamp) {
            try {
                $query = "SELECT * FROM Pollution_Data_T 
                          WHERE sensorID = ? AND timestamp = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$sensorID, $timestamp]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    echo json_encode($result);
                } else {
                    http_response_code(404);
                    echo json_encode(["error" => "Reading not found"]);
                }
            } catch(PDOException $e) {
                http_response_code(500);
                echo json_encode(["error" => $e->getMessage()]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["error" => "sensorID and timestamp are required"]);
        }
        break;
        
    case 'PUT':
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            
            $query = "UPDATE Pollution_Data_T SET 
                      airQuality = ?,
                      noiseLevel = ?,
                      pm25Level = ?,
                      noXLevel = ?,
                      co2Level = ?
                      WHERE sensorID = ? AND timestamp = ?";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                $data['airQuality'],
                $data['noiseLevel'],
                $data['pm25Level'],
                $data['noXLevel'],
                $data['co2Level'],
                $data['sensorID'],
                $data['timestamp']
            ]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(["message" => "Reading updated successfully"]);
            } else {
                echo json_encode(["message" => "No changes made or record not found"]);
            }
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
        break;
        
    case 'DELETE':
        if ($sensorID && $timestamp) {
            try {
                $query = "DELETE FROM Pollution_Data_T 
                          WHERE sensorID = ? AND timestamp = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$sensorID, $timestamp]);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(["message" => "Reading deleted successfully"]);
                } else {
                    http_response_code(404);
                    echo json_encode(["error" => "Reading not found"]);
                }
            } catch(PDOException $e) {
                http_response_code(500);
                echo json_encode(["error" => $e->getMessage()]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["error" => "sensorID and timestamp are required"]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
?>