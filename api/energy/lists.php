<?php
// api/energy/lists.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
include_once '../../config.php';

try {
    // Check if Iot_Sensor_T exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'Iot_Sensor_T'");
    if ($checkTable->rowCount() == 0) {
        echo json_encode([]);
        exit();
    }
    
    // Get all sensors with their types
    $sql = "SELECT sensorID, type, location FROM Iot_Sensor_T ORDER BY sensorID ASC";
    $stmt = $pdo->query($sql);
    $sensors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process sensor types to map to energy types
    $processedSensors = [];
    foreach ($sensors as $sensor) {
        $type = strtolower($sensor['type']);
        $energyType = '';
        
        // Map sensor type to energy type
        if (strpos($type, 'electricity') !== false || strpos($type, 'electric') !== false) {
            $energyType = 'Electricity';
        } elseif (strpos($type, 'water') !== false) {
            $energyType = 'Water';
        } elseif (strpos($type, 'gas') !== false) {
            $energyType = 'Gas';
        }
        
        // Only include sensors that match energy types
        if ($energyType) {
            $processedSensors[] = [
                'sensorID' => $sensor['sensorID'],
                'type' => $energyType,
                'originalType' => $sensor['type'],
                'location' => $sensor['location'] ?? ''
            ];
        }
    }
    
    // If no energy sensors found, create sample data
    if (empty($processedSensors)) {
        $processedSensors = [
            ['sensorID' => 1, 'type' => 'Electricity', 'originalType' => 'Electricity', 'location' => 'Building A'],
            ['sensorID' => 2, 'type' => 'Water', 'originalType' => 'Water', 'location' => 'Building A'],
            ['sensorID' => 3, 'type' => 'Gas', 'originalType' => 'Gas', 'location' => 'Building B'],
            ['sensorID' => 4, 'type' => 'Electricity', 'originalType' => 'Electricity', 'location' => 'Building C'],
            ['sensorID' => 5, 'type' => 'Water', 'originalType' => 'Water', 'location' => 'Building C']
        ];
    }
    
    echo json_encode($processedSensors);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>