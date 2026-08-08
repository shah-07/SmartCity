<?php
// api/parking/reserve_atomic.php
//
// Creates a reservation while holding a lock on the overlapping rows, so two
// people submitting the same slot at the same time cannot both succeed. The
// plain check-then-insert in reservations.php has a race between the two steps.
require_once __DIR__ . '/../_guard.php';
require_once __DIR__ . '/../../config.php';

handle_preflight();
$user = require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_fail(405, 'Method not allowed');
}

$input = json_decode(file_get_contents('php://input'));
if (!$input) {
    json_fail(400, 'Invalid JSON body');
}

foreach (['citizenID', 'spotID', 'startTime', 'endTime'] as $field) {
    if (empty($input->$field)) {
        json_fail(400, "$field is required");
    }
}

// Decided from the session, never from the body: otherwise a citizen could ask
// for status 'Reserved' and bypass the approval queue.
$isCitizen = ($user['role'] !== 'admin');

$startTs = strtotime($input->startTime);
$endTs   = strtotime($input->endTime);
if ($startTs === false || $endTs === false || $endTs <= $startTs) {
    json_fail(400, 'End time must be after the start time.');
}

$spotID    = $input->spotID;
$startTime = date('Y-m-d H:i:s', $startTs);
$endTime   = date('Y-m-d H:i:s', $endTs);

try {
    $pdo->beginTransaction();

    // Lock every reservation whose window overlaps the requested one. Each
    // window starts before the other ends; strict comparisons keep
    // back-to-back bookings legal.
    $lockSql = "SELECT reservationID, citizenID, startTime, endTime, status
                FROM Reservation_T
                WHERE spotID = ?
                  AND status IN ('Pending', 'Reserved')
                  AND startTime < ?
                  AND endTime   > ?
                FOR UPDATE";

    $lockStmt = $pdo->prepare($lockSql);
    $lockStmt->execute([$spotID, $endTime, $startTime]);
    $existing = $lockStmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($existing) > 0) {
        $pdo->rollBack();
        echo json_encode([
            'error'     => 'That spot has just been taken for the selected time. Please pick another slot.',
            'conflicts' => $existing
        ]);
        exit;
    }

    $status    = $isCitizen ? 'Pending' : 'Reserved';
    $createdBy = $isCitizen ? 'Citizen' : 'Admin';

    $sql = "INSERT INTO Reservation_T
            (citizenID, spotID, startTime, endTime, amount, paymentGateway, paymentDate, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $input->citizenID,
        $spotID,
        $startTime,
        $endTime,
        $input->amount ?? 0,
        $input->paymentGateway ?? null,
        $status,
        $createdBy
    ]);

    $reservationID = $pdo->lastInsertId();
    $pdo->commit();

    echo json_encode([
        'success'       => true,
        'reservationID' => $reservationID,
        'status'        => $status,
        'message'       => $status === 'Pending'
            ? 'Reservation request submitted (pending approval)'
            : 'Reservation created successfully'
    ]);
} catch (PDOException $e) {
    // rollBack() on a connection with no open transaction throws in turn, which
    // would replace the real error with a fatal.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // A unique-index collision means a competing request won the race.
    if ($e->getCode() === '23000') {
        echo json_encode([
            'error' => 'That spot has just been taken for the selected time. Please pick another slot.',
            'code'  => 'DUPLICATE_ENTRY'
        ]);
        exit;
    }

    json_db_error($e);
}
