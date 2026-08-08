<?php
// api/traffic/stats.php
error_reporting(0);
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
include_once '../../config.php';

try {
    // Fetch weekly average traffic data
    $sql = "SELECT 
                DATE_FORMAT(MIN(timestamp), '%d-%m') as week_start,
                DATE_FORMAT(MAX(timestamp), '%d-%m') as week_end,
                CONCAT(DATE_FORMAT(MIN(timestamp), '%d-%m'), ' to ', DATE_FORMAT(MAX(timestamp), '%d-%m')) as week_range,
                ROUND(AVG(trafficFlow), 0) as avg_traffic,
                WEEK(timestamp) as week_number
            FROM Traffic_Data_T 
            GROUP BY WEEK(timestamp), YEAR(timestamp)
            ORDER BY MIN(timestamp) ASC
            LIMIT 10";
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no data, return sample data for testing
    if (empty($data)) {
        $data = [
            ['week_range' => '01-01 to 07-01', 'avg_traffic' => '150'],
            ['week_range' => '08-01 to 14-01', 'avg_traffic' => '180'],
            ['week_range' => '15-01 to 21-01', 'avg_traffic' => '200'],
            ['week_range' => '22-01 to 28-01', 'avg_traffic' => '240'],
            ['week_range' => '29-01 to 04-02', 'avg_traffic' => '220']
        ];
    }
    
    echo json_encode($data);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>