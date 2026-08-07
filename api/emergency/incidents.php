<?php
// api/emergency/incidents.php
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
        $sql = "SELECT incidentID, incidentType, location, incidentTime, priority, status, latitude, longitude, description 
                FROM Incident_T ORDER BY incidentTime DESC";
        $stmt = $pdo->query($sql);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($result);
    } 
    elseif ($method === 'POST') {
        $stmt = $pdo->prepare("INSERT INTO Incident_T (incidentType, location, latitude, longitude, description, priority, status) 
                               VALUES (?, ?, ?, ?, ?, ?, 'Active')");
        $stmt->execute([
            $input->incidentType, 
            $input->location, 
            $input->latitude ?? 23.8103, 
            $input->longitude ?? 90.4125, 
            $input->description ?? '', 
            $input->priority ?? 'Medium'
        ]);
        echo json_encode(['message' => 'Incident reported successfully', 'id' => $pdo->lastInsertId()]);
    } 
    elseif ($method === 'PUT') {
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
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Incident ID required']);
            exit();
        }
        $stmt = $pdo->prepare("DELETE FROM Incident_T WHERE incidentID = ?");
        $stmt->execute([$id]);
        echo json_encode(['message' => 'Incident deleted successfully']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>