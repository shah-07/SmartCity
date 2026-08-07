<?php
// api/energy/chart.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
include_once '../../config.php';

try {
    // Check if table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'Energy_Consumption_Data_T'");
    if ($checkTable->rowCount() == 0) {
        echo json_encode([
            'months' => [],
            'electricity' => [],
            'gas' => [],
            'water' => []
        ]);
        exit();
    }
    
    // FIX: Remove month_label from GROUP BY, use only month_key
    $sql = "SELECT 
                DATE_FORMAT(timestamp, '%Y-%m') as month_key,
                energyType,
                SUM(totalConsumedUnit) as total
            FROM Energy_Consumption_Data_T 
            WHERE energyType IN ('Electricity', 'Gas', 'Water')
            GROUP BY DATE_FORMAT(timestamp, '%Y-%m'), energyType
            ORDER BY month_key ASC, 
                     CASE energyType 
                         WHEN 'Electricity' THEN 1
                         WHEN 'Gas' THEN 2
                         WHEN 'Water' THEN 3
                     END";
    
    $stmt = $pdo->query($sql);
    $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no data, create sample data for demonstration
    if (empty($rawData)) {
        // Generate sample data for last 6 months
        $sampleData = [];
        $currentDate = new DateTime();
        $energyTypes = ['Electricity', 'Gas', 'Water'];
        
        for ($i = 5; $i >= 0; $i--) {
            $date = clone $currentDate;
            $date->modify("-$i months");
            $monthKey = $date->format('Y-m');
            
            foreach ($energyTypes as $type) {
                $sampleData[] = [
                    'month_key' => $monthKey,
                    'energyType' => $type,
                    'total' => rand(100, 500) + (rand(0, 100) / 10)
                ];
            }
        }
        $rawData = $sampleData;
    }
    
    // Organize data by month
    $monthData = [];
    $monthKeys = [];
    
    foreach ($rawData as $row) {
        $monthKey = $row['month_key'];
        
        // Generate month label from month_key
        $dateObj = DateTime::createFromFormat('Y-m', $monthKey);
        $monthLabel = $dateObj ? $dateObj->format('M Y') : $monthKey;
        
        if (!isset($monthData[$monthKey])) {
            $monthData[$monthKey] = [
                'label' => $monthLabel,
                'Electricity' => 0,
                'Gas' => 0,
                'Water' => 0
            ];
            $monthKeys[$monthKey] = true;
        }
        
        $monthData[$monthKey][$row['energyType']] = (float)$row['total'];
    }
    
    // Sort months chronologically
    ksort($monthData);
    
    // Prepare data arrays for each energy type
    $monthLabels = [];
    $electricityData = [];
    $gasData = [];
    $waterData = [];
    
    foreach ($monthData as $monthKey => $data) {
        $monthLabels[] = $data['label'];
        $electricityData[] = $data['Electricity'];
        $gasData[] = $data['Gas'];
        $waterData[] = $data['Water'];
    }
    
    // Limit to last 12 months for better visibility
    if (count($monthLabels) > 12) {
        $start = count($monthLabels) - 12;
        $monthLabels = array_slice($monthLabels, $start);
        $electricityData = array_slice($electricityData, $start);
        $gasData = array_slice($gasData, $start);
        $waterData = array_slice($waterData, $start);
    }
    
    // Prepare final response
    $response = [
        'months' => $monthLabels,
        'electricity' => $electricityData,
        'gas' => $gasData,
        'water' => $waterData
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'months' => [],
        'electricity' => [],
        'gas' => [],
        'water' => []
    ]);
}
?>