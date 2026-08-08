<?php
// api/traffic/stats.php - Weekly average traffic flow.
require_once __DIR__ . '/../_guard.php';
require_once __DIR__ . '/../../config.php';

handle_preflight();
require_auth();

try {
    // Group by YEARWEEK rather than by WEEK() plus YEAR(): at a year boundary
    // the two disagree and a single week gets split across two points.
    $sql = "SELECT
                CONCAT(DATE_FORMAT(MIN(timestamp), '%d-%m'), ' to ', DATE_FORMAT(MAX(timestamp), '%d-%m')) as week_range,
                DATE_FORMAT(MIN(timestamp), '%d-%m') as week_start,
                DATE_FORMAT(MAX(timestamp), '%d-%m') as week_end,
                ROUND(AVG(trafficFlow), 0) as avg_traffic,
                WEEK(MIN(timestamp), 3) as week_number
            FROM Traffic_Data_T
            GROUP BY YEARWEEK(timestamp, 3)
            ORDER BY MIN(timestamp) ASC
            LIMIT 10";
    $stmt = $pdo->query($sql);

    // Returned as-is: an empty result means there is no traffic data, and the
    // chart says so. Inventing placeholder weeks here would put numbers on the
    // page that were never measured.
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    json_db_error($e);
}
