<?php
// api/emergency/responses.php
require_once __DIR__ . '/../_guard.php';
require_once __DIR__ . '/../../config.php';

handle_preflight();
require_auth();

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents("php://input"));

try {
    if ($method === 'GET') {
        $sql = "SELECT R.responseID, R.incidentID, I.incidentType, I.location, I.incidentTime as reportTime,
                       R.registrationNumber, R.dispatchTime, R.arrivalTime, R.completionTime,
                       TIMESTAMPDIFF(MINUTE, R.dispatchTime, R.arrivalTime) as responseTime,
                       TIMESTAMPDIFF(MINUTE, R.arrivalTime, R.completionTime) as serviceTime
                FROM Response_Times_T R
                LEFT JOIN Incident_T I ON R.incidentID = I.incidentID
                ORDER BY R.dispatchTime DESC";
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($data as &$row) {
            $t = $row['responseTime'];
            if ($t === null) {
                $row['performance'] = 'Pending';
            } elseif ($t <= 10) {
                $row['performance'] = 'Excellent';
            } elseif ($t <= 20) {
                $row['performance'] = 'Good';
            } else {
                $row['performance'] = 'Delayed';
            }
        }
        unset($row);

        echo json_encode($data);
    }
    elseif ($method === 'POST') {
        require_admin();

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO Response_Times_T (incidentID, registrationNumber, dispatchTime, arrivalTime, completionTime, outcome)
                               VALUES (?, ?, ?, ?, ?, 'Resolved')");
        $stmt->execute([
            $input->incidentID,
            $input->registrationNumber,
            $input->dispatchTime,
            $input->arrivalTime,
            $input->completionTime
        ]);

        // Logging a completed response closes the incident and releases the
        // vehicle; all three must land together or none of them.
        $pdo->prepare("UPDATE Incident_T SET status='Resolved' WHERE incidentID=?")
            ->execute([$input->incidentID]);

        $pdo->prepare("UPDATE Emergency_Vehicle_T SET status='Available', assignedIncidentID=NULL WHERE registrationNumber=?")
            ->execute([$input->registrationNumber]);

        $pdo->commit();
        echo json_encode(['message' => 'Response logged successfully']);
    }
    elseif ($method === 'PUT') {
        require_admin();

        $stmt = $pdo->prepare("UPDATE Response_Times_T SET incidentID=?, registrationNumber=?, dispatchTime=?, arrivalTime=?, completionTime=? WHERE responseID=?");
        $stmt->execute([
            $input->incidentID,
            $input->registrationNumber,
            $input->dispatchTime,
            $input->arrivalTime,
            $input->completionTime,
            $input->responseID
        ]);
        echo json_encode(['message' => 'Response updated successfully']);
    }
    elseif ($method === 'DELETE') {
        require_admin();

        $id = $_GET['id'] ?? null;
        if (!$id) {
            json_fail(400, 'Response ID required');
        }
        $stmt = $pdo->prepare("DELETE FROM Response_Times_T WHERE responseID = ?");
        $stmt->execute([$id]);
        echo json_encode(['message' => 'Response deleted successfully']);
    }
    else {
        json_fail(405, 'Method not allowed');
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_db_error($e);
}
