<?php
// api/parking/chart.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
include_once '../../config.php';

try {
    // First, check if we have any Reserved reservations with amounts
    $checkSql = "SELECT COUNT(*) as count FROM Reservation_T WHERE status = 'Reserved' AND amount IS NOT NULL AND amount > 0";
    $checkStmt = $pdo->query($checkSql);
    $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($checkResult['count'] == 0) {
        echo json_encode([
            'data' => [],
            'message' => 'No revenue data available. Please create some reservations with amounts.',
            'debug' => ['reserved_count' => 0]
        ]);
        exit();
    }
    
    // Complete fix: All non-aggregated columns must be in GROUP BY
    $sql = "SELECT 
                YEAR(startTime) as year,
                WEEK(startTime) as week_number,
                SUM(amount) as total_revenue,
                COUNT(*) as total_bookings,
                MIN(startTime) as week_start
            FROM Reservation_T 
            WHERE status = 'Reserved'
                AND amount IS NOT NULL 
                AND amount > 0
            GROUP BY YEAR(startTime), WEEK(startTime)
            ORDER BY year DESC, week_number DESC
            LIMIT 12";
    
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data for the chart
    $formattedData = [];
    foreach ($data as $row) {
        $weekStart = new DateTime($row['week_start']);
        $formattedData[] = [
            'week_label' => 'Week ' . $row['week_number'] . ' (' . $weekStart->format('M d') . ')',
            'total_revenue' => (float)$row['total_revenue'],
            'total_bookings' => (int)$row['total_bookings'],
            'week_number' => (int)$row['week_number']
        ];
    }
    
    // Reverse to show oldest to newest
    $formattedData = array_reverse($formattedData);
    
    echo json_encode([
        'data' => $formattedData,
        'debug' => [
            'total_weeks' => count($formattedData),
            'sample' => !empty($formattedData) ? $formattedData[0] : null
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'data' => [],
        'debug' => 'SQL Error occurred'
    ]);
}
?>