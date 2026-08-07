<?php
// api/emergency/responses.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
include_once '../../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents("php://input"));

// Handle preflight requests
if ($method == 'OPTIONS') {
    http_response_code(200);
    exit();
}

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
        echo json_encode($data);
    } 
    elseif ($method === 'POST') {
        $pdo->beginTransaction();
        
        // Insert response
        $stmt = $pdo->prepare("INSERT INTO Response_Times_T (incidentID, registrationNumber, dispatchTime, arrivalTime, completionTime, outcome) 
                               VALUES (?, ?, ?, ?, ?, 'Resolved')");
        $stmt->execute([
            $input->incidentID, 
            $input->registrationNumber, 
            $input->dispatchTime, 
            $input->arrivalTime, 
            $input->completionTime
        ]);
        
        // Update incident to Resolved
        $pdo->prepare("UPDATE Incident_T SET status='Resolved' WHERE incidentID=?")->execute([$input->incidentID]);
        
        // Free the vehicle
        $pdo->prepare("UPDATE Emergency_Vehicle_T SET status='Available', assignedIncidentID=NULL WHERE registrationNumber=?")->execute([$input->registrationNumber]);
        
        $pdo->commit();
        echo json_encode(['message' => 'Response logged successfully']);
    }
    elseif ($method === 'PUT') {
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
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Response ID required']);
            exit();
        }
        $stmt = $pdo->prepare("DELETE FROM Response_Times_T WHERE responseID = ?");
        $stmt->execute([$id]);
        echo json_encode(['message' => 'Response deleted successfully']);
    }
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>