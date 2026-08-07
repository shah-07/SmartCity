<?php
// api/traffic/sensors.php
error_reporting(0);
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
include_once '../../config.php';

try {
    // Fetch sensors
    $sql = "SELECT sensorID, location, type FROM Iot_Sensor_T WHERE status = 'active'";
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($data)) {
        // Return empty array instead of error
        echo json_encode([]);
    } else {
        echo json_encode($data);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>