<?php
// api/energy/lists.php - Sensors selectable on the energy page.
//
// A sensor belongs here if it already carries energy readings, or if its
// declared type in Iot_Sensor_T names one of the three utilities. The
// dropdown must offer only sensor IDs that really exist, so that a reading
// saved against one points at a real sensor.
require_once __DIR__ . '/../_guard.php';
require_once __DIR__ . '/../../config.php';

handle_preflight();
require_auth();

/** Maps a free-text sensor type onto an energy type, or '' if it is not one. */
function map_energy_type($rawType)
{
    $type = strtolower((string)$rawType);

    if (strpos($type, 'electric') !== false) {
        return 'Electricity';
    }
    if (strpos($type, 'water') !== false) {
        return 'Water';
    }
    if (strpos($type, 'gas') !== false) {
        return 'Gas';
    }
    return '';
}

try {
    // The energyType already recorded against a sensor wins over the sensor's
    // declared type: it is what the existing rows for that sensor use, so the
    // add form pre-fills with a value consistent with its history.
    $sql = "SELECT s.sensorID,
                   s.type AS declaredType,
                   COALESCE(NULLIF(s.location, ''), s.road, '') AS location,
                   (SELECT e.energyType
                      FROM Energy_Consumption_Data_T e
                     WHERE e.sensorID = s.sensorID
                     ORDER BY e.timestamp DESC
                     LIMIT 1) AS recordedType
            FROM Iot_Sensor_T s
            ORDER BY s.sensorID ASC";

    $stmt = $pdo->query($sql);
    $sensors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $processedSensors = [];
    foreach ($sensors as $sensor) {
        $energyType = $sensor['recordedType']
            ? map_energy_type($sensor['recordedType'])
            : map_energy_type($sensor['declaredType']);

        if ($energyType === '') {
            continue;
        }

        $processedSensors[] = [
            'sensorID'     => $sensor['sensorID'],
            'type'         => $energyType,
            'originalType' => $sensor['recordedType'] ?: $sensor['declaredType'],
            'location'     => $sensor['location'],
        ];
    }

    echo json_encode($processedSensors);
} catch (PDOException $e) {
    json_db_error($e);
}
