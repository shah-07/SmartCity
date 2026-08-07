<?php
header("Content-Type: application/json");
include_once '../../config.php';

try {
    // Stats for Chart 1: Incidents by Type
    $stmt = $pdo->query("SELECT type, COUNT(*) as count FROM Incident_T GROUP BY type");
    $byType = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Stats for Chart 2: Average Response Time by Vehicle Type
    $stmt2 = $pdo->query("SELECT V.unitType, AVG(R.responseTime) as avgTime 
                          FROM Response_Times_T R 
                          JOIN Emergency_Vehicle_T V ON R.registrationNumber = V.registrationNumber 
                          GROUP BY V.unitType");
    $byResponse = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['byType' => $byType, 'byResponse' => $byResponse]);
} catch (PDOException $e) { echo json_encode(['error' => $e->getMessage()]); }
?>