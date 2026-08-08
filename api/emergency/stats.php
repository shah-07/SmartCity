<?php
// api/emergency/stats.php - Data for the two charts on the emergency page.
require_once __DIR__ . '/../_guard.php';
require_once __DIR__ . '/../../config.php';

handle_preflight();
require_auth();

try {
    // Chart 1: Incidents by Type
    $stmt1 = $pdo->query("SELECT incidentType as type, COUNT(*) as count
                          FROM Incident_T
                          GROUP BY incidentType
                          ORDER BY count DESC");
    $byType = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    // Chart 2: Avg Response Time by Vehicle Type
    $sql2 = "SELECT V.unitType,
                    ROUND(AVG(TIMESTAMPDIFF(MINUTE, R.dispatchTime, R.arrivalTime)), 1) as avgTime
             FROM Response_Times_T R
             JOIN Emergency_Vehicle_T V ON R.registrationNumber = V.registrationNumber
             WHERE R.dispatchTime IS NOT NULL AND R.arrivalTime IS NOT NULL
             GROUP BY V.unitType
             ORDER BY avgTime ASC";
    $stmt2 = $pdo->query($sql2);
    $byResponse = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // Both lists are returned exactly as measured. The previous version
    // substituted invented figures (Ambulance 15, Fire Truck 12, Police 8)
    // whenever a query came back empty, so an empty database drew a chart that
    // looked like real response times.
    echo json_encode(['byType' => $byType, 'byResponse' => $byResponse]);
} catch (PDOException $e) {
    json_db_error($e);
}
