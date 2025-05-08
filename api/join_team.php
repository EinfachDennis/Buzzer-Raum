<?php
// buzzer-raum/api/join_team.php

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
if (!isset($_POST['team_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$team_id = (int)$_POST['team_id'];
$user_id = $_SESSION['id'];

// Get team info first to check if it exists
$sql = "SELECT * FROM buzzer_teams WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $team_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['success' => false, 'error' => 'Team not found']);
    exit;
}

$team = $result->fetch_assoc();
$room_id = $team['room_id'];

// Join the team
$result = joinTeam($conn, $team_id, $user_id);

// Return the result
echo json_encode($result);
?>