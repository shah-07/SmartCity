<?php
// api/emergency/vehicles.php
require_once __DIR__ . '/../_guard.php';
require_once __DIR__ . '/../../config.php';

handle_preflight();
require_auth();

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents("php://input"));

try {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT registrationNumber, unitType, status, assignedIncidentID
                             FROM Emergency_Vehicle_T ORDER BY registrationNumber");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    elseif ($method === 'POST') {
        require_admin();

        $pdo->beginTransaction();

        // Emergency_Vehicle_T.registrationNumber references Vehicle_T, so the
        // parent row has to exist first.
        $check = $pdo->prepare("SELECT registrationNumber FROM Vehicle_T WHERE registrationNumber = ?");
        $check->execute([$input->registrationNumber]);

        if ($check->fetchColumn() === false) {
            $stmt = $pdo->prepare("INSERT INTO Vehicle_T (registrationNumber, vehicleType) VALUES (?, ?)");
            $stmt->execute([$input->registrationNumber, $input->unitType]);
        }

        $stmt = $pdo->prepare("INSERT INTO Emergency_Vehicle_T (registrationNumber, unitType, status, assignedIncidentID)
                               VALUES (?, ?, ?, NULL)");
        $stmt->execute([$input->registrationNumber, $input->unitType, $input->status ?? 'Available']);

        $pdo->commit();
        echo json_encode(['message' => 'Vehicle added successfully']);
    }
    elseif ($method === 'PUT') {
        require_admin();

        // The form sends '' (or '-') for "not assigned"; the column is a
        // nullable foreign key, so that has to become a real NULL.
        $assigned = (!empty($input->assignedIncidentID) && $input->assignedIncidentID != '-')
            ? $input->assignedIncidentID
            : null;

        $sql = "UPDATE Emergency_Vehicle_T SET unitType=?, status=?, assignedIncidentID=? WHERE registrationNumber=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$input->unitType, $input->status, $assigned, $input->registrationNumber]);
        echo json_encode(['message' => 'Vehicle updated successfully']);
    }
    elseif ($method === 'DELETE') {
        require_admin();

        $id = $_GET['id'] ?? null;
        if (!$id) {
            json_fail(400, 'Registration number required');
        }
        $stmt = $pdo->prepare("DELETE FROM Emergency_Vehicle_T WHERE registrationNumber = ?");
        $stmt->execute([$id]);
        echo json_encode(['message' => 'Vehicle deleted successfully']);
    }
    else {
        json_fail(405, 'Method not allowed');
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Adding a duplicate registration, or deleting a vehicle that still has
    // response history, both surface here.
    if ($e->getCode() === '23000') {
        json_fail(409, 'That registration number is already in use, or the vehicle still has response records attached.');
    }
    json_db_error($e);
}
