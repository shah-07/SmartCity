<?php
require_once '../../config.php';

try {
    $query = "SELECT sensorID, road, area FROM Iot_Sensor_T ORDER BY sensorID";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($result);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
?>