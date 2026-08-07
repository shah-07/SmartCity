<?php
// logout.php - Handle logout
session_start();
session_destroy();
echo json_encode(['success' => true]);
?>