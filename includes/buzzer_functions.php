<?php
// buzzer-raum/includes/buzzer_functions.php

// Function to create a new buzzer room
function createBuzzerRoom($conn, $name, $host_id, $team_count) {
    // Generate a unique room code
    $room_code = generateRoomCode($conn);
    
    // Create buzzer room
    $sql = "INSERT INTO buzzer_rooms (name, room_code, host_id, team_count) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $name, $room_code, $host_id, $team_count);
    
    if ($stmt->execute()) {
        $room_id = $stmt->insert_id;
        
        // Initialize buzzer state
        $sql = "INSERT INTO buzzer_states (room_id, is_active) VALUES (?, TRUE)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        
        return ['success' => true, 'room_id' => $room_id, 'room_code' => $room_code];
    } else {
        return ['success' => false, 'error' => 'Failed to create buzzer room'];
    }
}

// Generate a unique room code
function generateRoomCode($conn) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Excluding similar looking characters
    $code_exists = true;
    
    while ($code_exists) {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[rand(0, strlen($chars) - 1)];
        }
        
        // Check if code exists
        $sql = "SELECT id FROM buzzer_rooms WHERE room_code = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows == 0) {
            $code_exists = false;
        }
    }
    
    return $code;
}

// Get room by code
function getBuzzerRoomByCode($conn, $code) {
    $sql = "SELECT * FROM buzzer_rooms WHERE room_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        return $result->fetch_assoc();
    } else {
        return false;
    }
}

// Get room by ID
function getBuzzerRoomById($conn, $room_id) {
    $sql = "SELECT * FROM buzzer_rooms WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        return $result->fetch_assoc();
    } else {
        return false;
    }
}

// Create a team for a room
function createTeam($conn, $room_id, $name, $color = '#3498db') {
    $sql = "INSERT INTO buzzer_teams (room_id, name, color) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $room_id, $name, $color);
    
    if ($stmt->execute()) {
        $team_id = $stmt->insert_id;
        
        // Initialize team notes
        $sql = "INSERT INTO buzzer_team_notes (team_id, content) VALUES (?, '')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $team_id);
        $stmt->execute();
        
        return ['success' => true, 'team_id' => $team_id];
    } else {
        return ['success' => false, 'error' => 'Failed to create team'];
    }
}

// Get teams for a room
function getTeamsForRoom($conn, $room_id) {
    $sql = "SELECT * FROM buzzer_teams WHERE room_id = ? ORDER BY name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $teams = [];
    while ($row = $result->fetch_assoc()) {
        $teams[] = $row;
    }
    
    return $teams;
}

// Join a team
function joinTeam($conn, $team_id, $user_id) {
    // Check if user is already in this team
    $sql = "SELECT id FROM buzzer_team_members WHERE team_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $team_id, $user_id);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        return ['success' => true, 'message' => 'Already in team'];
    }
    
    // Remove user from any other teams in the same room
    $sql = "DELETE btm FROM buzzer_team_members btm 
            JOIN buzzer_teams bt ON btm.team_id = bt.id
            JOIN buzzer_teams bt2 ON bt.room_id = bt2.room_id
            WHERE btm.user_id = ? AND bt2.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $team_id);
    $stmt->execute();
    
    // Add user to team
    $sql = "INSERT INTO buzzer_team_members (team_id, user_id) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $team_id, $user_id);
    
    if ($stmt->execute()) {
        return ['success' => true];
    } else {
        return ['success' => false, 'error' => 'Failed to join team'];
    }
}

// Get user's team in a room
function getUserTeamInRoom($conn, $room_id, $user_id) {
    $sql = "SELECT bt.* FROM buzzer_teams bt
            JOIN buzzer_team_members btm ON bt.id = btm.team_id
            WHERE bt.room_id = ? AND btm.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $room_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        return $result->fetch_assoc();
    } else {
        return false;
    }
}

// Get team members
function getTeamMembers($conn, $team_id) {
    $sql = "SELECT u.id, u.username FROM users u
            JOIN buzzer_team_members btm ON u.id = btm.user_id
            WHERE btm.team_id = ? ORDER BY u.username ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
    
    return $members;
}

// Press buzzer
function pressBuzzer($conn, $room_id, $user_id, $team_id) {
    // Get buzzer state
    $sql = "SELECT * FROM buzzer_states WHERE room_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $buzzer = $result->fetch_assoc();
    
    // If buzzer is already pressed, return false
    if (!$buzzer['is_active'] || $buzzer['pressed_by'] !== null) {
        return ['success' => false, 'error' => 'Buzzer already pressed'];
    }
    
    // Update buzzer state
    $sql = "UPDATE buzzer_states SET is_active = FALSE, pressed_by = ?, pressed_at = NOW(), team_id = ? WHERE room_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $user_id, $team_id, $room_id);
    
    if ($stmt->execute()) {
        return ['success' => true];
    } else {
        return ['success' => false, 'error' => 'Failed to press buzzer'];
    }
}

// Reset buzzer
function resetBuzzer($conn, $room_id) {
    $sql = "UPDATE buzzer_states SET is_active = TRUE, pressed_by = NULL, pressed_at = NULL, team_id = NULL WHERE room_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $room_id);
    
    if ($stmt->execute()) {
        return ['success' => true];
    } else {
        return ['success' => false, 'error' => 'Failed to reset buzzer'];
    }
}

// Get buzzer state
function getBuzzerState($conn, $room_id) {
    $sql = "SELECT bs.*, u.username as pressed_by_name, bt.name as team_name, bt.color as team_color
            FROM buzzer_states bs
            LEFT JOIN users u ON bs.pressed_by = u.id
            LEFT JOIN buzzer_teams bt ON bs.team_id = bt.id
            WHERE bs.room_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        return $result->fetch_assoc();
    } else {
        return false;
    }
}

// Add points to a team
function addPointsToTeam($conn, $team_id, $points) {
    $sql = "UPDATE buzzer_teams SET points = points + ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $points, $team_id);
    
    if ($stmt->execute()) {
        return ['success' => true];
    } else {
        return ['success' => false, 'error' => 'Failed to add points'];
    }
}

// Update team notes
function updateTeamNotes($conn, $team_id, $content) {
    $sql = "UPDATE buzzer_team_notes SET content = ? WHERE team_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $content, $team_id);
    
    if ($stmt->execute()) {
        return ['success' => true];
    } else {
        return ['success' => false, 'error' => 'Failed to update notes'];
    }
}

// Get team notes
function getTeamNotes($conn, $team_id) {
    $sql = "SELECT content FROM buzzer_team_notes WHERE team_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $team_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        return $row['content'];
    } else {
        return '';
    }
}

// Check if user is host of a room
function isRoomHost($conn, $room_id, $user_id) {
    $sql = "SELECT id FROM buzzer_rooms WHERE id = ? AND host_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $room_id, $user_id);
    $stmt->execute();
    $stmt->store_result();
    
    return $stmt->num_rows > 0;
}

// Get all active buzzer rooms
function getActiveBuzzerRooms($conn) {
    $sql = "SELECT br.*, u.username as host_name, COUNT(DISTINCT bt.id) as team_count, COUNT(DISTINCT btm.id) as participant_count
            FROM buzzer_rooms br
            JOIN users u ON br.host_id = u.id
            LEFT JOIN buzzer_teams bt ON br.id = bt.room_id
            LEFT JOIN buzzer_team_members btm ON bt.id = btm.team_id
            WHERE br.active = TRUE
            GROUP BY br.id
            ORDER BY br.created_at DESC";
    $result = $conn->query($sql);
    
    $rooms = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $rooms[] = $row;
        }
    }
    
    return $rooms;
}

// Get all rooms hosted by a user
function getUserHostedRooms($conn, $user_id) {
    $sql = "SELECT br.*, COUNT(DISTINCT bt.id) as team_count, COUNT(DISTINCT btm.id) as participant_count
            FROM buzzer_rooms br
            LEFT JOIN buzzer_teams bt ON br.id = bt.room_id
            LEFT JOIN buzzer_team_members btm ON bt.id = btm.team_id
            WHERE br.host_id = ?
            GROUP BY br.id
            ORDER BY br.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $rooms = [];
    while ($row = $result->fetch_assoc()) {
        $rooms[] = $row;
    }
    
    return $rooms;
}

// Delete a buzzer room and all associated data
function deleteRoom($conn, $room_id) {
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete team notes
        $sql = "DELETE btn FROM buzzer_team_notes btn 
                JOIN buzzer_teams bt ON btn.team_id = bt.id 
                WHERE bt.room_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        
        // Delete team members
        $sql = "DELETE btm FROM buzzer_team_members btm 
                JOIN buzzer_teams bt ON btm.team_id = bt.id 
                WHERE bt.room_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        
        // Delete teams
        $sql = "DELETE FROM buzzer_teams WHERE room_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        
        // Delete buzzer state
        $sql = "DELETE FROM buzzer_states WHERE room_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        
        // Delete room
        $sql = "DELETE FROM buzzer_rooms WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        return ['success' => true];
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        return ['success' => false, 'error' => 'Failed to delete room: ' . $e->getMessage()];
    }
}
?>