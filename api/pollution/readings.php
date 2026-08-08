<?php
// api/pollution/readings.php - List all readings, and create new ones.
// Updating and deleting a single reading live in reading.php.
require_once __DIR__ . '/../_guard.php';
require_once __DIR__ . '/../../config.php';

handle_preflight();
require_auth();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $query = "SELECT
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
                  ORDER BY pd.timestamp DESC";

        $stmt = $pdo->query($query);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        json_db_error($e);
    }
}

else if ($method === 'POST') {
    require_admin();

    try {
        $data = json_decode(file_get_contents("php://input"), true);
        if (!is_array($data)) {
            json_fail(400, 'Invalid JSON body');
        }

        // A reading of 0 is legitimate, so presence is checked rather than
        // truthiness: empty() would reject a genuine zero measurement.
        $required = ['sensorID', 'timestamp', 'airQuality', 'noiseLevel', 'pm25Level', 'noXLevel', 'co2Level'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                json_fail(400, "Missing required field: $field");
            }
        }

        // (sensorID, timestamp) is the primary key.
        $check = $pdo->prepare("SELECT COUNT(*) FROM Pollution_Data_T WHERE sensorID = ? AND timestamp = ?");
        $check->execute([$data['sensorID'], $data['timestamp']]);
        if ($check->fetchColumn() > 0) {
            json_fail(409, 'A reading already exists for this sensor and timestamp');
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
    } catch (PDOException $e) {
        json_db_error($e);
    }
}

else {
    json_fail(405, 'Method not allowed');
}
