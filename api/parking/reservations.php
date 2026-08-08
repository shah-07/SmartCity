<?php
// api/parking/reservations.php
require_once __DIR__ . '/../_guard.php';
require_once __DIR__ . '/../../config.php';

handle_preflight();
$user = require_auth();

$method = $_SERVER['REQUEST_METHOD'];
$input  = json_decode(file_get_contents("php://input"));

// The role comes from the session. The page also puts a userRole in the body,
// but trusting it would let a citizen self-approve a booking by editing the
// payload, skipping the Pending queue entirely.
$isCitizen = ($user['role'] !== 'admin');

try {
    if ($method === 'GET') {
        // Fetches all required columns including Citizen Name
        $sql = "SELECT R.reservationID, R.spotID, R.citizenID,
                       CONCAT(C.fname, ' ', C.lname) as citizenName,
                       R.startTime, R.endTime, R.amount,
                       R.paymentGateway, R.paymentDate,
                       R.status, R.created_by
                FROM Reservation_T R
                LEFT JOIN Citizen_T C ON R.citizenID = C.citizenID
                ORDER BY R.startTime DESC";
        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    elseif ($method === 'POST') {
        // A citizen's booking is a request; an admin's is already confirmed.
        $status    = $isCitizen ? 'Pending' : 'Reserved';
        $createdBy = $isCitizen ? 'Citizen' : 'Admin';

        $sql = "INSERT INTO Reservation_T (citizenID, spotID, startTime, endTime, amount, paymentGateway, paymentDate, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $input->citizenID,
            $input->spotID,
            $input->startTime,
            $input->endTime,
            $input->amount,
            $input->paymentGateway,
            $status,
            $createdBy
        ]);

        echo json_encode([
            'message'       => 'Reservation created successfully',
            'status'        => $status,
            'reservationID' => $pdo->lastInsertId()
        ]);
    }
    elseif ($method === 'PUT') {
        // Confirming and editing are both admin actions.
        if ($isCitizen) {
            json_fail(403, 'Forbidden - admin access required');
        }

        // Check if this is a status update or regular edit
        if (isset($input->newStatus) && $input->newStatus === 'Reserved') {
            // Confirm reservation (status update only)
            $stmt = $pdo->prepare("UPDATE Reservation_T SET status = ? WHERE reservationID = ?");
            $stmt->execute(['Reserved', $input->reservationID]);
            echo json_encode(['message' => 'Reservation confirmed successfully']);
        } else {
            // Regular edit
            $sql = "UPDATE Reservation_T SET
                    citizenID=?, spotID=?, startTime=?, endTime=?, amount=?, paymentGateway=?
                    WHERE reservationID=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $input->citizenID,
                $input->spotID,
                $input->startTime,
                $input->endTime,
                $input->amount,
                $input->paymentGateway,
                $input->reservationID
            ]);
            echo json_encode(['message' => 'Reservation updated successfully']);
        }
    }
    elseif ($method === 'DELETE') {
        if ($isCitizen) {
            json_fail(403, 'Forbidden - admin access required');
        }

        $id = $_GET['id'] ?? null;
        if (!$id) {
            json_fail(400, 'Reservation ID is required');
        }

        $stmt = $pdo->prepare("DELETE FROM Reservation_T WHERE reservationID = ?");
        $stmt->execute([$id]);
        echo json_encode(['message' => 'Reservation deleted successfully']);
    }
    else {
        json_fail(405, 'Method not allowed');
    }
} catch (PDOException $e) {
    json_db_error($e);
}
