<?php
// buzzer-raum/api/reset_buzzer.php

// Initialize the session
session_start();

// Include configuration files
require_once "../../includes/config.php";
require_once "../../includes/auth.php";
require_once "../../includes/db.php";
require_once "../includes/buzzer_functions.php";

// Return JSON response
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

// Check if required parameters are provided
if (!isset($_POST['room_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$room_id = (int)$_POST['room_id'];
$user_id = $_SESSION['id'];

// Check if user is the host
if (!isRoomHost($conn, $room_id, $user_id)) {
    echo json_encode(['success' => false, 'error' => 'Only the host can reset the buzzer']);
    exit;
}

// Reset the buzzer
$result = resetBuzzer($conn, $room_id);

// Return the result
echo json_encode($result);
?>