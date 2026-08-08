<?php
// api/parking/chart.php - Weekly confirmed-reservation revenue.
require_once __DIR__ . '/../_guard.php';
require_once __DIR__ . '/../../config.php';

handle_preflight();
require_auth();

try {
    // Group by YEARWEEK rather than by YEAR() plus WEEK(): around new year the
    // two disagree, because a week that straddles the boundary carries a week
    // number belonging to the other year and would be split into two points.
    $sql = "SELECT
                YEARWEEK(startTime, 3) as year_week,
                WEEK(MIN(startTime), 3) as week_number,
                SUM(amount)            as total_revenue,
                COUNT(*)               as total_bookings,
                MIN(startTime)         as week_start
            FROM Reservation_T
            WHERE status = 'Reserved'
              AND amount IS NOT NULL
              AND amount > 0
            GROUP BY YEARWEEK(startTime, 3)
            ORDER BY year_week DESC
            LIMIT 12";

    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // The query takes the twelve most recent weeks, so it reads newest first;
    // the chart plots left to right in time order.
    $data = array_reverse($data);

    $formattedData = [];
    foreach ($data as $row) {
        $weekStart = new DateTime($row['week_start']);
        $formattedData[] = [
            'week_label'     => 'Week ' . $row['week_number'] . ' (' . $weekStart->format('M d') . ')',
            'total_revenue'  => (float)$row['total_revenue'],
            'total_bookings' => (int)$row['total_bookings'],
            'week_number'    => (int)$row['week_number']
        ];
    }

    echo json_encode(['data' => $formattedData]);
} catch (PDOException $e) {
    json_db_error($e);
}
