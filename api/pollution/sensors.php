<?php
// api/pollution/sensors.php - Active sensors for the pollution page dropdown.
require_once __DIR__ . '/../_guard.php';
require_once __DIR__ . '/../../config.php';

handle_preflight();
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_fail(405, 'Method not allowed');
}

try {
    // Read-only: this endpoint used to create Iot_Sensor_T and seed it with
    // invented sensors when the table was missing, so a GET could silently
    // write rows that then looked like real installations.
    $query = "SELECT sensorID, road, area, latitude, longitude
              FROM Iot_Sensor_T
              WHERE status = 'active'
              ORDER BY sensorID";
    $stmt = $pdo->query($query);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    json_db_error($e);
}
