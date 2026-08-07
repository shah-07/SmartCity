<?php
// api/emergency/vehicles.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
include_once '../../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents("php://input"));

// Handle preflight requests
if ($method == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM Emergency_Vehicle_T ORDER BY registrationNumber");
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
    } 
    elseif ($method === 'POST') {
        $pdo->beginTransaction();
        
        // Check if vehicle already exists in Vehicle_T
        $check = $pdo->prepare("SELECT registrationNumber FROM Vehicle_T WHERE registrationNumber = ?");
        $check->execute([$input->registrationNumber]);
        
        if ($check->rowCount() == 0) {
            // Insert into Vehicle_T
            $stmt = $pdo->prepare("INSERT INTO Vehicle_T (registrationNumber, vehicleType) VALUES (?, ?)");
            $stmt->execute([$input->registrationNumber, $input->unitType]);
        }
        
        // Insert into Emergency_Vehicle_T
        $stmt = $pdo->prepare("INSERT INTO Emergency_Vehicle_T (registrationNumber, unitType, status, assignedIncidentID) 
                               VALUES (?, ?, ?, NULL)");
        $stmt->execute([$input->registrationNumber, $input->unitType, $input->status ?? 'Available']);
        
        $pdo->commit();
        echo json_encode(['message' => 'Vehicle added successfully']);
    }
    elseif ($method === 'PUT') {
        $assigned = (!empty($input->assignedIncidentID) && $input->assignedIncidentID != '-') ? $input->assignedIncidentID : NULL;
        
        $sql = "UPDATE Emergency_Vehicle_T SET unitType=?, status=?, assignedIncidentID=? WHERE registrationNumber=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$input->unitType, $input->status, $assigned, $input->registrationNumber]);
        echo json_encode(['message' => 'Vehicle updated successfully']);
    }
    elseif ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Registration number required']);
            exit();
        }
        $stmt = $pdo->prepare("DELETE FROM Emergency_Vehicle_T WHERE registrationNumber = ?");
        $stmt->execute([$id]);
        echo json_encode(['message' => 'Vehicle deleted successfully']);
    }
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>