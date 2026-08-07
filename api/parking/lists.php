<?php
// api/parking/lists.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
include_once '../../config.php';

$type = isset($_GET['type']) ? $_GET['type'] : '';
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

    echo json_encode($data);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>