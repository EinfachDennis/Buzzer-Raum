<?php
// buzzer-raum/api/get_state.php

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
if (!isset($_GET['room_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$room_id = (int)$_GET['room_id'];
$user_id = $_SESSION['id'];

// Get room
$room = getBuzzerRoomById($conn, $room_id);
if (!$room) {
    echo json_encode(['success' => false, 'error' => 'Room not found']);
    exit;
}

// Get user's team
$user_team = getUserTeamInRoom($conn, $room_id, $user_id);
$is_host = isRoomHost($conn, $room_id, $user_id);

// Get all teams and their points
$teams = getTeamsForRoom($conn, $room_id);
$team_data = [];

foreach ($teams as $team) {
    $team_members = getTeamMembers($conn, $team['id']);
    $team_data[] = [
        'id' => $team['id'],
        'name' => $team['name'],
        'color' => $team['color'],
        'points' => $team['points'],
        'members' => $team_members
    ];
    
    // Only include notes if this is the user's team or if user is host
    if ($is_host || ($user_team && $user_team['id'] == $team['id'])) {
        $team_data[count($team_data) - 1]['notes'] = getTeamNotes($conn, $team['id']);
    }
}

// Get buzzer state
$buzzer_state = getBuzzerState($conn, $room_id);

// Return room state
$state = [
    'success' => true,
    'room' => [
        'id' => $room['id'],
        'name' => $room['name'],
        'code' => $room['room_code'],
        'host_id' => $room['host_id'],
        'active' => $room['active'] == 1
    ],
    'user' => [
        'id' => $user_id,
        'is_host' => $is_host,
        'team' => $user_team ? $user_team['id'] : null
    ],
    'buzzer' => [
        'is_active' => $buzzer_state['is_active'] == 1,
        'pressed_by' => $buzzer_state['pressed_by'],
        'pressed_by_name' => $buzzer_state['pressed_by_name'],
        'pressed_at' => $buzzer_state['pressed_at'],
        'team_id' => $buzzer_state['team_id'],
        'team_name' => $buzzer_state['team_name'],
        'team_color' => $buzzer_state['team_color']
    ],
    'teams' => $team_data
];

// Return the state
echo json_encode($state);
?>