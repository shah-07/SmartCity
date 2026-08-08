<?php
// api/sensors/sensors.php - Management of the shared IoT sensor registry.
//
// Iot_Sensor_T is the one table the other modules all build on: traffic
// records, parking spots, pollution readings and energy readings each point
// back at a sensorID. That is why this endpoint lives on its own rather than
// under one module, and why only an admin may change it — a sensor removed or
// retyped here changes what every other page shows.
//
// Reading the list is allowed for any signed-in user; creating, editing and
// deleting require an admin.
require_once __DIR__ . '/../_guard.php';
require_once __DIR__ . '/../../config.php';

handle_preflight();
require_auth();

$method = $_SERVER['REQUEST_METHOD'];

/**
 * Sensor types the rest of the portal recognises.
 *
 * The type is not decoration: api/energy/lists.php decides which sensors can
 * take an energy reading by matching these words, so a free-text value would
 * create a sensor no module ever offers. Keeping the list here means the
 * server rejects unknown types even if the page is bypassed.
 */
const SENSOR_TYPES = [
    'Camera', 'Induction Loop', 'Radar',   // traffic
    'pollution',                            // air quality
    'Electricity', 'Water', 'Gas',          // energy
    'Parking',                              // parking occupancy
];

const SENSOR_STATUSES = ['active', 'inactive'];

/**
 * Validates and normalises a submitted sensor.
 * Returns [values, errors]; values is only meaningful when errors is empty.
 */
function clean_sensor($input)
{
    $errors = [];

    $location = trim((string)($input->location ?? ''));
    if ($location === '') {
        $errors[] = 'Location is required.';
    } elseif (mb_strlen($location) > 100) {
        $errors[] = 'Location must be 100 characters or fewer.';
    }

    $road = trim((string)($input->road ?? ''));
    $area = trim((string)($input->area ?? ''));
    if (mb_strlen($road) > 100) $errors[] = 'Road must be 100 characters or fewer.';
    if (mb_strlen($area) > 100) $errors[] = 'Area must be 100 characters or fewer.';

    $type = (string)($input->type ?? '');
    if (!in_array($type, SENSOR_TYPES, true)) {
        $errors[] = 'Sensor type must be one of: ' . implode(', ', SENSOR_TYPES) . '.';
    }

    $status = (string)($input->status ?? 'active');
    if (!in_array($status, SENSOR_STATUSES, true)) {
        $errors[] = 'Status must be active or inactive.';
    }

    // Coordinates are optional, but a half-filled pair puts a marker in the
    // wrong place on the emergency and pollution maps, so both or neither.
    $latRaw = $input->latitude  ?? '';
    $lngRaw = $input->longitude ?? '';
    $latGiven = ($latRaw !== '' && $latRaw !== null);
    $lngGiven = ($lngRaw !== '' && $lngRaw !== null);

    $latitude = $longitude = null;
    if ($latGiven !== $lngGiven) {
        $errors[] = 'Give both latitude and longitude, or neither.';
    } elseif ($latGiven) {
        if (!is_numeric($latRaw) || $latRaw < -90 || $latRaw > 90) {
            $errors[] = 'Latitude must be a number between -90 and 90.';
        } else {
            $latitude = (float)$latRaw;
        }
        if (!is_numeric($lngRaw) || $lngRaw < -180 || $lngRaw > 180) {
            $errors[] = 'Longitude must be a number between -180 and 180.';
        } else {
            $longitude = (float)$lngRaw;
        }
    }

    // Optional, but a sensor cannot have been installed in the future.
    $installed = trim((string)($input->installed_date ?? ''));
    $installedDate = null;
    if ($installed !== '') {
        $d = DateTime::createFromFormat('!Y-m-d', $installed);
        if (!$d || $d->format('Y-m-d') !== $installed) {
            $errors[] = 'Installed date must be a valid date.';
        } elseif ($d > new DateTime('today')) {
            $errors[] = 'Installed date cannot be in the future.';
        } else {
            $installedDate = $installed;
        }
    }

    return [[
        'road'           => $road !== '' ? $road : null,
        'area'           => $area !== '' ? $area : null,
        'latitude'       => $latitude,
        'longitude'      => $longitude,
        'location'       => $location,
        'type'           => $type,
        'status'         => $status,
        'installed_date' => $installedDate,
    ], $errors];
}

try {
    if ($method === 'GET') {
        // Each sensor carries a count of what depends on it, so the page can
        // warn before a delete that would be refused.
        $sql = "SELECT s.sensorID, s.road, s.area, s.latitude, s.longitude,
                       s.location, s.type, s.status, s.installed_date,
                       (SELECT COUNT(*) FROM Traffic_Data_T  t WHERE t.sensorID = s.sensorID) AS trafficRecords,
                       (SELECT COUNT(*) FROM Parking_Spot_T  p WHERE p.sensorID = s.sensorID) AS parkingSpots,
                       (SELECT COUNT(*) FROM Pollution_Data_T d WHERE d.sensorID = s.sensorID) AS pollutionReadings,
                       (SELECT COUNT(*) FROM Energy_Consumption_Data_T e WHERE e.sensorID = s.sensorID) AS energyReadings
                FROM Iot_Sensor_T s
                ORDER BY s.sensorID ASC";
        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    elseif ($method === 'POST') {
        require_admin();

        $input = json_decode(file_get_contents('php://input'));
        if (!$input) {
            json_fail(400, 'Invalid JSON body');
        }

        [$values, $errors] = clean_sensor($input);
        if ($errors) {
            json_fail(400, implode(' ', $errors));
        }

        $sql = "INSERT INTO Iot_Sensor_T (road, area, latitude, longitude, location, type, status, installed_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $values['road'], $values['area'], $values['latitude'], $values['longitude'],
            $values['location'], $values['type'], $values['status'], $values['installed_date'],
        ]);

        echo json_encode([
            'message'  => 'Sensor added successfully',
            'sensorID' => $pdo->lastInsertId()
        ]);
    }

    elseif ($method === 'PUT') {
        require_admin();

        $input = json_decode(file_get_contents('php://input'));
        if (!$input || empty($input->sensorID)) {
            json_fail(400, 'sensorID is required');
        }

        [$values, $errors] = clean_sensor($input);
        if ($errors) {
            json_fail(400, implode(' ', $errors));
        }

        $sql = "UPDATE Iot_Sensor_T
                SET road=?, area=?, latitude=?, longitude=?, location=?, type=?, status=?, installed_date=?
                WHERE sensorID=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $values['road'], $values['area'], $values['latitude'], $values['longitude'],
            $values['location'], $values['type'], $values['status'], $values['installed_date'],
            $input->sensorID,
        ]);

        echo json_encode(['message' => 'Sensor updated successfully']);
    }

    elseif ($method === 'DELETE') {
        require_admin();

        $id = $_GET['id'] ?? null;
        if (!$id) {
            json_fail(400, 'Sensor ID is required');
        }

        // Traffic records and parking spots hold a foreign key to this row, so
        // the database would refuse anyway; checking first lets us say what is
        // actually in the way. Marking a decommissioned sensor 'inactive'
        // keeps its history, which is why that is the suggestion here.
        // One placeholder per subquery: with emulated prepares switched off in
        // config.php these go to MySQL as real prepared statements, and a
        // native statement cannot reuse the same named placeholder twice.
        $depSql = "SELECT
                     (SELECT COUNT(*) FROM Traffic_Data_T  WHERE sensorID = ?) AS traffic,
                     (SELECT COUNT(*) FROM Parking_Spot_T  WHERE sensorID = ?) AS parking,
                     (SELECT COUNT(*) FROM Pollution_Data_T WHERE sensorID = ?) AS pollution,
                     (SELECT COUNT(*) FROM Energy_Consumption_Data_T WHERE sensorID = ?) AS energy";
        $depStmt = $pdo->prepare($depSql);
        $depStmt->execute([$id, $id, $id, $id]);
        $deps = $depStmt->fetch(PDO::FETCH_ASSOC);

        $blocking = [];
        foreach (['traffic' => 'traffic record', 'parking' => 'parking spot',
                  'pollution' => 'pollution reading', 'energy' => 'energy reading'] as $key => $label) {
            $n = (int)$deps[$key];
            if ($n > 0) {
                $blocking[] = $n . ' ' . $label . ($n === 1 ? '' : 's');
            }
        }

        if ($blocking) {
            json_fail(409, 'This sensor still has ' . implode(', ', $blocking)
                . ' attached. Set it to inactive instead of deleting it, so the existing data keeps its source.');
        }

        $stmt = $pdo->prepare("DELETE FROM Iot_Sensor_T WHERE sensorID = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            json_fail(404, 'Sensor not found');
        }

        echo json_encode(['message' => 'Sensor deleted successfully']);
    }

    else {
        json_fail(405, 'Method not allowed');
    }
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        json_fail(409, 'This sensor is still referenced by other records and cannot be removed.');
    }
    json_db_error($e);
}
