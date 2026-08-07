<?php
// api/parking/reserve_atomic.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
include_once '../../config.php';

$input = json_decode(file_get_contents("php://input"));

try {
    $pdo->beginTransaction();
    
    // Lock the relevant rows
    $spotID = $input->spotID;
    $startTime = date('Y-m-d H:i:s', strtotime($input->startTime));
    $endTime = date('Y-m-d H:i:s', strtotime($input->endTime));
    
    // Method 1: Use SELECT FOR UPDATE (MySQL/PostgreSQL)
    $lockSql = "SELECT * FROM Reservation_T 
                WHERE spotID = ? 
                AND status IN ('Pending', 'Reserved')
                AND (
                    (? BETWEEN startTime AND DATE_SUB(endTime, INTERVAL 1 SECOND))
                    OR (? BETWEEN DATE_ADD(startTime, INTERVAL 1 SECOND) AND endTime)
                    OR (startTime <= ? AND endTime >= ?)
                )
                FOR UPDATE";
    
    $lockStmt = $pdo->prepare($lockSql);
    $lockStmt->execute([$spotID, $startTime, $endTime, $startTime, $endTime]);
    $existing = $lockStmt->fetchAll();
    
    if (count($existing) > 0) {
        $pdo->rollBack();
        echo json_encode([
            'error' => 'Spot is no longer available',
            'conflicts' => $existing
        ]);
        exit();
    }
    
    // Method 2: Use INSERT with unique constraint (simpler)
    $status = ($input->userRole === 'Citizen') ? 'Pending' : 'Reserved';
    
    $sql = "INSERT INTO Reservation_T 
            (citizenID, spotID, startTime, endTime, amount, paymentGateway, status, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([
        $input->citizenID,
        $spotID,
        $startTime,
        $endTime,
        $input->amount,
        $input->paymentGateway,
        $status,
        $input->userRole
    ]);
    
    if ($success) {
        $pdo->commit();
        $reservationID = $pdo->lastInsertId();
        echo json_encode([
            'success' => true,
            'reservationID' => $reservationID,
            'status' => $status,
            'message' => $status === 'Pending' 
                ? 'Reservation request submitted (pending approval)' 
                : 'Reservation created successfully'
        ]);
    } else {
        $pdo->rollBack();
        // Check for duplicate error
        if ($stmt->errorCode() === '23000') { // MySQL duplicate entry
            echo json_encode([
                'error' => 'This time slot was just booked by another user. Please try a different time.'
            ]);
        } else {
            echo json_encode(['error' => 'Reservation failed']);
        }
    }
    
} catch (PDOException $e) {
    $pdo->rollBack();
    
    // Check for duplicate entry error (MySQL error code 1062)
    if (strpos($e->getMessage(), '1062') !== false || 
        strpos($e->getMessage(), 'Duplicate entry') !== false) {
        echo json_encode([
            'error' => 'This parking spot was just booked by another user. Please select a different time.',
            'code' => 'DUPLICATE_ENTRY'
        ]);
    } else {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>