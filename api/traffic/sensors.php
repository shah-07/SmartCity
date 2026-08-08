<?php
// api/traffic/sensors.php
require_once __DIR__ . '/../_guard.php';
require_once __DIR__ . '/../../config.php';

handle_preflight();
require_auth();

try {
    $sql = "SELECT sensorID, location, type FROM Iot_Sensor_T WHERE status = 'active' ORDER BY sensorID";
    $stmt = $pdo->query($sql);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    json_db_error($e);
}
