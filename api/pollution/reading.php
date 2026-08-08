<?php
// api/pollution/reading.php - Read, update or delete one reading, keyed by
// (sensorID, timestamp).
require_once __DIR__ . '/../_guard.php';
require_once __DIR__ . '/../../config.php';

handle_preflight();
require_auth();

$method = $_SERVER['REQUEST_METHOD'];

$sensorID  = $_GET['sensorID']  ?? null;
$timestamp = $_GET['timestamp'] ?? null;

switch ($method) {
    case 'GET':
        if (!$sensorID || !$timestamp) {
            json_fail(400, 'sensorID and timestamp are required');
        }
        try {
            $query = "SELECT * FROM Pollution_Data_T WHERE sensorID = ? AND timestamp = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$sensorID, $timestamp]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                echo json_encode($result);
            } else {
                json_fail(404, 'Reading not found');
            }
        } catch (PDOException $e) {
            json_db_error($e);
        }
        break;

    case 'PUT':
        require_admin();
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            if (!is_array($data)) {
                json_fail(400, 'Invalid JSON body');
            }

            if (!isset($data['sensorID'], $data['timestamp'])) {
                json_fail(400, 'sensorID and timestamp are required');
            }

            // sensorID and timestamp form the key, so they identify the row
            // rather than being updated.
            $query = "UPDATE Pollution_Data_T SET
                      airQuality = ?,
                      noiseLevel = ?,
                      pm25Level  = ?,
                      noXLevel   = ?,
                      co2Level   = ?
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
        } catch (PDOException $e) {
            json_db_error($e);
        }
        break;

    case 'DELETE':
        require_admin();
        if (!$sensorID || !$timestamp) {
            json_fail(400, 'sensorID and timestamp are required');
        }
        try {
            $query = "DELETE FROM Pollution_Data_T WHERE sensorID = ? AND timestamp = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$sensorID, $timestamp]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["message" => "Reading deleted successfully"]);
            } else {
                json_fail(404, 'Reading not found');
            }
        } catch (PDOException $e) {
            json_db_error($e);
        }
        break;

    default:
        json_fail(405, 'Method not allowed');
}
