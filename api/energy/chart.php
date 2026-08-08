<?php
// api/energy/chart.php - Monthly consumption per utility.
require_once __DIR__ . '/../_guard.php';
require_once __DIR__ . '/../../config.php';

handle_preflight();
require_auth();

try {
    $sql = "SELECT
                DATE_FORMAT(timestamp, '%Y-%m') as month_key,
                energyType,
                SUM(totalConsumedUnit) as total
            FROM Energy_Consumption_Data_T
            WHERE energyType IN ('Electricity', 'Gas', 'Water')
            GROUP BY DATE_FORMAT(timestamp, '%Y-%m'), energyType
            ORDER BY month_key ASC";

    $stmt = $pdo->query($sql);
    $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organise data by month
    $monthData = [];

    foreach ($rawData as $row) {
        $monthKey = $row['month_key'];

        if (!isset($monthData[$monthKey])) {
            // Parse with an explicit day and a leading "!", which resets the
            // unspecified fields. Without it PHP fills the day from today, so
            // on the 31st "2026-02" would overflow into March.
            $dateObj = DateTime::createFromFormat('!Y-m-d', $monthKey . '-01');

            $monthData[$monthKey] = [
                'label'       => $dateObj ? $dateObj->format('M Y') : $monthKey,
                'Electricity' => 0,
                'Gas'         => 0,
                'Water'       => 0
            ];
        }

        $monthData[$monthKey][$row['energyType']] = (float)$row['total'];
    }

    ksort($monthData);

    // Keep the last 12 months so the axis stays readable.
    if (count($monthData) > 12) {
        $monthData = array_slice($monthData, -12, 12, true);
    }

    $response = [
        'months'      => [],
        'electricity' => [],
        'gas'         => [],
        'water'       => []
    ];

    foreach ($monthData as $data) {
        $response['months'][]      = $data['label'];
        $response['electricity'][] = $data['Electricity'];
        $response['gas'][]         = $data['Gas'];
        $response['water'][]       = $data['Water'];
    }

    echo json_encode($response);
} catch (PDOException $e) {
    json_db_error($e);
}
