<?php
// api/pollution/chart.php - Daily pollutant averages for the last 7 days.
require_once __DIR__ . '/../_guard.php';
require_once __DIR__ . '/../../config.php';

handle_preflight();
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_fail(405, 'Method not allowed');
}

try {
    // DESC + LIMIT picks the seven most recent days; without the reversal
    // afterwards the chart would run backwards along the x-axis.
    $query = "SELECT
                  DATE(timestamp) as date,
                  AVG(pm25Level)  as avg_pm25,
                  AVG(co2Level)   as avg_co2,
                  AVG(noXLevel)   as avg_nox,
                  COUNT(*)        as readings_count
              FROM Pollution_Data_T
              GROUP BY DATE(timestamp)
              ORDER BY date DESC
              LIMIT 7";

    $stmt = $pdo->query($query);
    $result = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

    foreach ($result as &$row) {
        $row['avg_pm25']       = round((float)$row['avg_pm25'], 2);
        $row['avg_co2']        = round((float)$row['avg_co2'], 2);
        $row['avg_nox']        = round((float)$row['avg_nox'], 2);
        $row['readings_count'] = (int)$row['readings_count'];
    }
    unset($row);

    echo json_encode($result);
} catch (PDOException $e) {
    json_db_error($e);
}
