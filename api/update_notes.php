<?php
// buzzer-raum/api/update_notes.php

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
if (!isset($_POST['room_id']) || !isset($_POST['team_id']) || !isset($_POST['content'])) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$room_id = (int)$_POST['room_id'];
$team_id = (int)$_POST['team_id'];
$content = $_POST['content'];
$user_id = $_SESSION['id'];

// Check if user is the host or a member of the team
$user_team = getUserTeamInRoom($conn, $room_id, $user_id);
$is_host = isRoomHost($conn, $room_id, $user_id);

if (!$is_host && (!$user_team || $user_team['id'] != $team_id)) {
    echo json_encode(['success' => false, 'error' => 'No permission to update notes']);
    exit;
}

// Update team notes
$result = updateTeamNotes($conn, $team_id, $content);

// Return the result
echo json_encode($result);
?>