<?php
// api/energy/readings.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
include_once '../../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents("php://input"));

try {
    if ($method === 'GET') {
        // Fetch all columns required
        $sql = "SELECT sensorID, energyType, timestamp, totalConsumedUnit, lastRechargedDate, lastRechargedAmount 
                FROM Energy_Consumption_Data_T 
                ORDER BY timestamp DESC";
        $stmt = $pdo->query($sql);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($result)) {
            echo json_encode([]);
        } else {
            echo json_encode($result);
        }
    } 
    elseif ($method === 'POST') {
        // Check if record with same sensorID and timestamp exists
        $check = $pdo->prepare("SELECT COUNT(*) FROM Energy_Consumption_Data_T WHERE sensorID = ? AND timestamp = ?");
        $check->execute([$input->sensorID, $input->timestamp]);
        if ($check->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode(['error' => 'Record with this sensor and timestamp already exists']);
            exit();
        }
        
        $sql = "INSERT INTO Energy_Consumption_Data_T (sensorID, energyType, timestamp, totalConsumedUnit, lastRechargedDate, lastRechargedAmount) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $input->sensorID,
            $input->energyType,
            $input->timestamp,
            $input->totalConsumedUnit,
            $input->lastRechargedDate,
            $input->lastRechargedAmount
        ]);
        echo json_encode(['message' => 'Record added successfully']);
    } 
    elseif ($method === 'PUT') {
        // Identify record by OLD sensorID and timestamp (Composite Key)
        $sql = "UPDATE Energy_Consumption_Data_T SET 
                sensorID=?, energyType=?, timestamp=?, totalConsumedUnit=?, lastRechargedDate=?, lastRechargedAmount=? 
                WHERE sensorID=? AND timestamp=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $input->sensorID,
            $input->energyType,
            $input->timestamp,
            $input->totalConsumedUnit,
            $input->lastRechargedDate,
            $input->lastRechargedAmount,
            $input->originalSensorID, 
            $input->originalTimestamp
        ]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['message' => 'Record updated successfully']);
        } else {
            echo json_encode(['message' => 'No changes made or record not found']);
        }
    } 
    elseif ($method === 'DELETE') {
        $sid = isset($_GET['sensorID']) ? $_GET['sensorID'] : null;
        $ts = isset($_GET['timestamp']) ? $_GET['timestamp'] : null;
        
        if (!$sid || !$ts) {
            http_response_code(400);
            echo json_encode(['error' => 'sensorID and timestamp are required']);
            exit();
        }
        
        $stmt = $pdo->prepare("DELETE FROM Energy_Consumption_Data_T WHERE sensorID=? AND timestamp=?");
        $stmt->execute([$sid, $ts]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['message' => 'Record deleted successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Record not found']);
        }
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>