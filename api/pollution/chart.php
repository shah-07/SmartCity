<?php
// api/pollution/chart.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
include_once '../../config.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    try {
        // Check if table exists
        $checkTable = $pdo->query("SHOW TABLES LIKE 'Pollution_Data_T'");
        if ($checkTable->rowCount() == 0) {
            echo json_encode([]);
            exit();
        }
        
        // FIX: Use proper GROUP BY with only aggregated columns
        $query = "
            SELECT 
                DATE(timestamp) as date,
                AVG(pm25Level) as avg_pm25,
                AVG(co2Level) as avg_co2,
                AVG(noXLevel) as avg_nox,
                COUNT(*) as readings_count
            FROM Pollution_Data_T 
            GROUP BY DATE(timestamp)
            ORDER BY date DESC
            LIMIT 7
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert to proper numeric types
        foreach ($result as &$row) {
            $row['avg_pm25'] = (float)$row['avg_pm25'];
            $row['avg_co2'] = (float)$row['avg_co2'];
            $row['avg_nox'] = (float)$row['avg_nox'];
            $row['readings_count'] = (int)$row['readings_count'];
        }
        
        // If no data, return sample data for demo
        if (empty($result)) {
            $sampleData = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $sampleData[] = [
                    'date' => $date,
                    'avg_pm25' => rand(10, 50) + (rand(0, 100) / 100),
                    'avg_co2' => rand(350, 500) + (rand(0, 100) / 100),
                    'avg_nox' => rand(10, 40) + (rand(0, 100) / 100),
                    'readings_count' => rand(3, 8)
                ];
            }
            echo json_encode($sampleData);
        } else {
            echo json_encode($result);
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>