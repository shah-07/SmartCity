<?php
// api/pollution/sensors.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
include_once '../../config.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Check if Iot_Sensor_T table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'Iot_Sensor_T'");
    if ($checkTable->rowCount() == 0) {
        // Create sensor table with pollution-specific data
        $pdo->exec("CREATE TABLE IF NOT EXISTS Iot_Sensor_T (
            sensorID INT AUTO_INCREMENT PRIMARY KEY,
            road VARCHAR(100),
            area VARCHAR(100),
            latitude DECIMAL(10,8),
            longitude DECIMAL(11,8),
            type VARCHAR(50) DEFAULT 'pollution',
            status ENUM('active', 'inactive') DEFAULT 'active'
        )");
        
        // Insert sample sensors
        $pdo->exec("INSERT INTO Iot_Sensor_T (road, area, latitude, longitude, type) VALUES 
            ('Main Street', 'Downtown', 40.7128, -74.0060, 'pollution'),
            ('Park Avenue', 'North Side', 40.7135, -74.0075, 'pollution'),
            ('Elm Street', 'West End', 40.7140, -74.0080, 'pollution')
        ");
    }
    
    $query = "SELECT sensorID, road, area, latitude, longitude FROM Iot_Sensor_T WHERE status = 'active' ORDER BY sensorID";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>