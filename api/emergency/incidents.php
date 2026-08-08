<?php
// api/emergency/incidents.php
require_once __DIR__ . '/../_guard.php';
require_once __DIR__ . '/../../config.php';

handle_preflight();
require_auth();

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents("php://input"));

try {
    if ($method === 'GET') {
        $sql = "SELECT incidentID, incidentType, location, incidentTime, priority, status, latitude, longitude, description
                FROM Incident_T ORDER BY incidentTime DESC";
        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    elseif ($method === 'POST') {
        require_admin();

        $stmt = $pdo->prepare("INSERT INTO Incident_T (incidentType, location, latitude, longitude, description, priority, status)
                               VALUES (?, ?, ?, ?, ?, ?, 'Active')");
        $stmt->execute([
            $input->incidentType,
            $input->location,
            $input->latitude  ?? 23.8103,
            $input->longitude ?? 90.4125,
            $input->description ?? '',
            $input->priority ?? 'Medium'
        ]);
        echo json_encode(['message' => 'Incident reported successfully', 'id' => $pdo->lastInsertId()]);
    }
    elseif ($method === 'PUT') {
        require_admin();

        $stmt = $pdo->prepare("UPDATE Incident_T SET
                               incidentType=?, location=?, latitude=?, longitude=?, priority=?, status=?, description=?
                               WHERE incidentID=?");
        $stmt->execute([
            $input->incidentType,
            $input->location,
            $input->latitude,
            $input->longitude,
            $input->priority,
            $input->status,
            $input->description,
            $input->incidentID
        ]);
        echo json_encode(['message' => 'Incident updated successfully']);
    }
    elseif ($method === 'DELETE') {
        require_admin();

        $id = $_GET['id'] ?? null;
        if (!$id) {
            json_fail(400, 'Incident ID required');
        }
        $stmt = $pdo->prepare("DELETE FROM Incident_T WHERE incidentID = ?");
        $stmt->execute([$id]);
        echo json_encode(['message' => 'Incident deleted successfully']);
    }
    else {
        json_fail(405, 'Method not allowed');
    }
} catch (PDOException $e) {
    // Response history and assigned vehicles both reference an incident.
    if ($e->getCode() === '23000') {
        json_fail(409, 'This incident still has response records or an assigned vehicle, so it cannot be deleted.');
    }
    json_db_error($e);
}
