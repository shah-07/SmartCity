<?php
// api/traffic/records.php
require_once __DIR__ . '/../_guard.php';
require_once __DIR__ . '/../../config.php';

handle_preflight();
require_auth();

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents("php://input"));

// Single record by id
if ($method === 'GET' && isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT recordID, sensorID, location, timestamp, trafficFlow, congestionLevel, numberOfRoadAccidents
                               FROM Traffic_Data_T WHERE recordID = ?");
        $stmt->execute([$_GET['id']]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($record) {
            echo json_encode($record);
        } else {
            json_fail(404, 'Record not found');
        }
    } catch (PDOException $e) {
        json_db_error($e);
    }
    exit;
}

try {
    if ($method === 'GET') {
        $sql = "SELECT recordID, sensorID, location, timestamp, trafficFlow, congestionLevel, numberOfRoadAccidents
                FROM Traffic_Data_T ORDER BY timestamp DESC";
        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    elseif ($method === 'POST') {
        require_admin();

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
        require_admin();

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
        require_admin();

        $id = $_GET['id'] ?? null;
        if (!$id) {
            json_fail(400, 'Record ID is required');
        }

        $stmt = $pdo->prepare("DELETE FROM Traffic_Data_T WHERE recordID=?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Record Deleted Successfully']);
    }
    else {
        json_fail(405, 'Method not allowed');
    }
} catch (PDOException $e) {
    json_db_error($e);
}
