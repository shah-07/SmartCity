<?php
// api/parking/check_conflict.php
//
// Answers "can this spot be booked for this window?" for the reservation
// forms in parking.html. The page calls this before every create and every
// edit, so it must always return JSON: the form treats an unreadable response
// as a conflict and refuses the booking.
//
// Request  (POST, JSON): spotID, startTime, endTime, excludeReservationID?
// Response (200,  JSON): hasConflict, spotStatus, conflictingReservations[],
//                        and errorType/message when the window itself is bad.
require_once __DIR__ . '/../_guard.php';
require_once __DIR__ . '/../../config.php';

handle_preflight();
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_fail(405, 'Method not allowed');
}

// A booking may not run longer than this. Kept here rather than in the page so
// the limit cannot be edited away from the browser.
const MAX_DURATION_HOURS = 24;

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    json_fail(400, 'Invalid JSON body');
}

$spotID    = $input['spotID']    ?? null;
$startTime = $input['startTime'] ?? null;
$endTime   = $input['endTime']   ?? null;
$excludeID = $input['excludeReservationID'] ?? null;

if (!$spotID || !$startTime || !$endTime) {
    json_fail(400, 'spotID, startTime and endTime are required');
}

// The form sends the datetime-local format ("2026-09-02T10:00"); MySQL wants a
// space separator. strtotime handles both, so normalise through it.
$startTs = strtotime($startTime);
$endTs   = strtotime($endTime);
if ($startTs === false || $endTs === false) {
    json_fail(400, 'startTime and endTime must be valid date/times');
}

$start = date('Y-m-d H:i:s', $startTs);
$end   = date('Y-m-d H:i:s', $endTs);

/** A rejected window: reported as a conflict so the form blocks submission. */
function reject($errorType, $message, $spotStatus = null)
{
    echo json_encode([
        'hasConflict'             => true,
        'errorType'               => $errorType,
        'message'                 => $message,
        'conflictingReservations' => [],
        'spotStatus'              => $spotStatus,
    ]);
    exit;
}

if ($endTs <= $startTs) {
    reject('duration', 'End time must be after the start time.');
}

if (($endTs - $startTs) > MAX_DURATION_HOURS * 3600) {
    reject('max_duration', 'A reservation may not be longer than ' . MAX_DURATION_HOURS . ' hours.');
}

try {
    // The spot itself must exist; its status is reported back so the form can
    // warn about spots under maintenance.
    $spotStmt = $pdo->prepare('SELECT status FROM Parking_Spot_T WHERE spotID = ?');
    $spotStmt->execute([$spotID]);
    $spot = $spotStmt->fetch(PDO::FETCH_ASSOC);

    if (!$spot) {
        json_fail(404, 'That parking spot no longer exists.');
    }

    // Two windows overlap when each starts before the other ends. Using strict
    // comparisons keeps back-to-back bookings legal: a slot ending at 11:00
    // does not collide with one starting at 11:00.
    $sql = "SELECT R.reservationID, R.citizenID, R.startTime, R.endTime, R.status,
                   CONCAT(C.fname, ' ', C.lname) AS citizenName
            FROM Reservation_T R
            LEFT JOIN Citizen_T C ON R.citizenID = C.citizenID
            WHERE R.spotID = ?
              AND R.status IN ('Pending', 'Reserved')
              AND R.startTime < ?
              AND R.endTime   > ?";
    $params = [$spotID, $end, $start];

    // When editing, the reservation being edited must not conflict with itself.
    if ($excludeID !== null && $excludeID !== '') {
        $sql .= ' AND R.reservationID <> ?';
        $params[] = $excludeID;
    }

    $sql .= ' ORDER BY R.startTime ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($conflicts as &$row) {
        $row['conflict_type'] = 'Time Overlap';
    }
    unset($row);

    echo json_encode([
        'hasConflict'             => count($conflicts) > 0,
        'errorType'               => count($conflicts) > 0 ? 'time_conflict' : null,
        'message'                 => count($conflicts) > 0
            ? 'This spot is already booked during the selected window.'
            : '',
        'conflictingReservations' => $conflicts,
        'spotStatus'              => $spot['status'],
    ]);
} catch (PDOException $e) {
    json_db_error($e);
}
