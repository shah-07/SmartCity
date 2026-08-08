<?php
// api/parking/lists.php - Dropdown sources for the parking page.
require_once __DIR__ . '/../_guard.php';
require_once __DIR__ . '/../../config.php';

handle_preflight();
require_auth();

$type = $_GET['type'] ?? '';
$data = [];

try {
    if ($type === 'citizens') {
        // Fetch citizens for Reservation dropdown
        $stmt = $pdo->query("SELECT citizenID, fname, lname FROM Citizen_T ORDER BY citizenID ASC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    elseif ($type === 'spots') {
        // Fetch spots for Reservation dropdown
        $stmt = $pdo->query("SELECT spotID FROM Parking_Spot_T ORDER BY spotID ASC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    elseif ($type === 'lots') {
        // Fetch lots for Spot creation dropdown
        $stmt = $pdo->query("SELECT lotID, road, area FROM Parking_Lot_T ORDER BY lotID ASC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    elseif ($type === 'sensors') {
        // Fetch sensors for Spot creation dropdown
        $stmt = $pdo->query("SELECT sensorID, road FROM Iot_Sensor_T ORDER BY sensorID ASC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    else {
        json_fail(400, 'Unknown list type');
    }

    echo json_encode($data);
} catch (PDOException $e) {
    json_db_error($e);
}
