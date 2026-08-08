<?php
// api/energy/readings.php
require_once __DIR__ . '/../_guard.php';
require_once __DIR__ . '/../../config.php';

handle_preflight();
require_auth();

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents("php://input"));

try {
    if ($method === 'GET') {
        $sql = "SELECT sensorID, energyType, timestamp, totalConsumedUnit, lastRechargedDate, lastRechargedAmount
                FROM Energy_Consumption_Data_T
                ORDER BY timestamp DESC";
        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    elseif ($method === 'POST') {
        require_admin();

        // (sensorID, timestamp) is the primary key, so a repeat would be a
        // duplicate-key error; report it as a conflict instead.
        $check = $pdo->prepare("SELECT COUNT(*) FROM Energy_Consumption_Data_T WHERE sensorID = ? AND timestamp = ?");
        $check->execute([$input->sensorID, $input->timestamp]);
        if ($check->fetchColumn() > 0) {
            json_fail(409, 'A record for this sensor and timestamp already exists');
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
        require_admin();

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
        require_admin();

        $sid = $_GET['sensorID'] ?? null;
        $ts  = $_GET['timestamp'] ?? null;

        if (!$sid || !$ts) {
            json_fail(400, 'sensorID and timestamp are required');
        }

        $stmt = $pdo->prepare("DELETE FROM Energy_Consumption_Data_T WHERE sensorID=? AND timestamp=?");
        $stmt->execute([$sid, $ts]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['message' => 'Record deleted successfully']);
        } else {
            json_fail(404, 'Record not found');
        }
    }
    else {
        json_fail(405, 'Method not allowed');
    }
} catch (PDOException $e) {
    json_db_error($e);
}
