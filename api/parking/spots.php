<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
include_once '../../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents("php://input"));

try {
    if ($method === 'GET') {
        // Joins with Parking_Lot_T to get Location & Capacity info
        $sql = "SELECT S.spotID, S.sensorID, S.status, 
                       S.lotID, L.road, L.area, L.city, L.capacity
                FROM Parking_Spot_T S
                LEFT JOIN Parking_Lot_T L ON S.lotID = L.lotID
                ORDER BY S.spotID ASC";
        $stmt = $pdo->query($sql);
        $spots = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no spots, return empty array
        if (empty($spots)) {
            echo json_encode([]);
        } else {
            echo json_encode($spots);
        }
    } 
    elseif ($method === 'POST') {
        // Insert new spot
        $stmt = $pdo->prepare("INSERT INTO Parking_Spot_T (lotID, sensorID, status) VALUES (?, ?, ?)");
        $stmt->execute([
            $input->lotID, 
            $input->sensorID, 
            $input->status
        ]);
        echo json_encode(['message' => 'Parking spot created successfully']);
    } 
    elseif ($method === 'PUT') {
        // Update existing spot
        if (!isset($input->spotID) || empty($input->spotID)) {
            http_response_code(400);
            echo json_encode(['error' => 'Spot ID is required']);
            exit();
        }
        
        $stmt = $pdo->prepare("UPDATE Parking_Spot_T SET lotID=?, sensorID=?, status=? WHERE spotID=?");
        $stmt->execute([
            $input->lotID, 
            $input->sensorID, 
            $input->status, 
            $input->spotID
        ]);
        echo json_encode(['message' => 'Parking spot updated successfully']);
    }
    elseif ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Spot ID is required']);
            exit();
        }
        
        $stmt = $pdo->prepare("DELETE FROM Parking_Spot_T WHERE spotID = ?");
        $stmt->execute([$id]);
        echo json_encode(['message' => 'Parking spot deleted successfully']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>