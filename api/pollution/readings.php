<?php
// api/pollution/readings.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
include_once '../../config.php';

$method = $_SERVER['REQUEST_METHOD'];

// Handle preflight requests
if ($method == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// GET all pollution readings
if ($method == 'GET') {
    try {
        // Check if table exists
        $checkTable = $pdo->query("SHOW TABLES LIKE 'Pollution_Data_T'");
        if ($checkTable->rowCount() == 0) {
            // Create table if it doesn't exist
            $pdo->exec("CREATE TABLE IF NOT EXISTS Pollution_Data_T (
                sensorID INT NOT NULL,
                timestamp DATETIME NOT NULL,
                airQuality VARCHAR(50) DEFAULT 'Good',
                noiseLevel DECIMAL(10,2) DEFAULT 0,
                pm25Level DECIMAL(10,2) DEFAULT 0,
                noXLevel DECIMAL(10,2) DEFAULT 0,
                co2Level DECIMAL(10,2) DEFAULT 0,
                PRIMARY KEY (sensorID, timestamp)
            )");
            
            // Insert sample data
            $pdo->exec("INSERT INTO Pollution_Data_T (sensorID, timestamp, airQuality, noiseLevel, pm25Level, noXLevel, co2Level) VALUES 
                (1, NOW(), 'Good', 45.5, 12.3, 15.2, 380.5),
                (2, NOW(), 'Moderate', 52.1, 25.7, 22.4, 420.3),
                (3, NOW(), 'Poor', 68.3, 45.2, 35.6, 510.8),
                (1, DATE_SUB(NOW(), INTERVAL 1 DAY), 'Excellent', 38.2, 8.5, 10.1, 350.2),
                (2, DATE_SUB(NOW(), INTERVAL 1 DAY), 'Good', 48.7, 18.3, 18.7, 395.6),
                (3, DATE_SUB(NOW(), INTERVAL 1 DAY), 'Moderate', 55.9, 32.4, 28.9, 445.2)
            ");
        }
        
        $query = "
            SELECT 
                pd.sensorID,
                pd.timestamp,
                pd.airQuality,
                pd.noiseLevel,
                pd.pm25Level,
                pd.co2Level,
                pd.noXLevel,
                iot.road,
                iot.area,
                iot.latitude,
                iot.longitude
            FROM Pollution_Data_T pd
            LEFT JOIN Iot_Sensor_T iot ON pd.sensorID = iot.sensorID
            ORDER BY pd.timestamp DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($result);
        
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

// POST new reading
else if ($method == 'POST') {
    try {
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validate required fields
        $required = ['sensorID', 'timestamp', 'airQuality', 'noiseLevel', 'pm25Level', 'noXLevel', 'co2Level'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Missing required field: $field"]);
                exit();
            }
        }
        
        // Check if record already exists
        $check = $pdo->prepare("SELECT COUNT(*) FROM Pollution_Data_T WHERE sensorID = ? AND timestamp = ?");
        $check->execute([$data['sensorID'], $data['timestamp']]);
        if ($check->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode(['error' => 'Record already exists for this sensor and timestamp']);
            exit();
        }
        
        $query = "INSERT INTO Pollution_Data_T 
                  (sensorID, timestamp, airQuality, noiseLevel, pm25Level, noXLevel, co2Level) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $data['sensorID'],
            $data['timestamp'],
            $data['airQuality'],
            $data['noiseLevel'],
            $data['pm25Level'],
            $data['noXLevel'],
            $data['co2Level']
        ]);
        
        echo json_encode(["message" => "Reading added successfully"]);
        
    } catch(PDOException $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
    }
}

// PUT (Update) - Note: This is handled by reading.php
else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>