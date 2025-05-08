<?php
// buzzer-raum/api/add_points.php

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
if (!isset($_POST['room_id']) || !isset($_POST['team_id']) || !isset($_POST['points'])) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$room_id = (int)$_POST['room_id'];
$team_id = (int)$_POST['team_id'];
$points = (int)$_POST['points'];
$user_id = $_SESSION['id'];

// Check if user is the host
if (!isRoomHost($conn, $room_id, $user_id)) {
    echo json_encode(['success' => false, 'error' => 'Only the host can add points']);
    exit;
}

// Add points to the team
$result = addPointsToTeam($conn, $team_id, $points);

if ($result['success']) {
    // Get updated team points
    $sql = "SELECT points FROM buzzer_teams WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $result_team = $stmt->get_result();
    $team = $result_team->fetch_assoc();
    
    $result['points'] = $team['points'];
}

// Return the result
echo json_encode($result);
?>