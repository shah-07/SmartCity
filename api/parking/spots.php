<?php
// api/parking/spots.php
require_once __DIR__ . '/../_guard.php';
require_once __DIR__ . '/../../config.php';

handle_preflight();
require_auth();

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents("php://input"));

try {
    if ($method === 'GET') {
        // Joins with Parking_Lot_T to get Location & Capacity info
        $sql = "SELECT S.spotID, S.sensorID, S.status,
                       S.lotID, L.road, L.area, L.city, L.capacity
                FROM Parking_Spot_T S
                LEFT JOIN Parking_Lot_T L ON S.lotID = L.lotID
                ORDER BY S.spotID ASC";
        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    elseif ($method === 'POST') {
        require_admin();

        $stmt = $pdo->prepare("INSERT INTO Parking_Spot_T (lotID, sensorID, status) VALUES (?, ?, ?)");
        $stmt->execute([
            $input->lotID,
            $input->sensorID,
            $input->status
        ]);
        echo json_encode(['message' => 'Parking spot created successfully']);
    }
    elseif ($method === 'PUT') {
        require_admin();

        if (!isset($input->spotID) || empty($input->spotID)) {
            json_fail(400, 'Spot ID is required');
        }

        $stmt = $pdo->prepare("UPDATE Parking_Spot_T SET lotID=?, sensorID=?, status=? WHERE spotID=?");
        $stmt->execute([
            $input->lotID,
            $input->sensorID,
            $input->status,
            $input->spotID
        ]);
        echo json_encode(['message' => 'Parking spot updated successfully']);
    }
    elseif ($method === 'DELETE') {
        require_admin();

        $id = $_GET['id'] ?? null;
        if (!$id) {
            json_fail(400, 'Spot ID is required');
        }

        $stmt = $pdo->prepare("DELETE FROM Parking_Spot_T WHERE spotID = ?");
        $stmt->execute([$id]);
        echo json_encode(['message' => 'Parking spot deleted successfully']);
    }
    else {
        json_fail(405, 'Method not allowed');
    }
} catch (PDOException $e) {
    // A spot that still has reservations cannot be removed; say so rather than
    // reporting a generic failure.
    if ($e->getCode() === '23000') {
        json_fail(409, 'This spot still has reservations attached and cannot be deleted.');
    }
    json_db_error($e);
}
