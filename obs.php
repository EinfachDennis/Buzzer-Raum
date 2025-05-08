<?php
// buzzer-raum/obs.php

// Initialize the session
session_start();

// Include configuration files
require_once "../includes/config.php";
require_once "../includes/db.php";
require_once "includes/buzzer_functions.php";

// Get URL parameters
$room_code = isset($_GET['code']) ? trim($_GET['code']) : '';
$refresh_rate = isset($_GET['refresh']) ? (int)$_GET['refresh'] : 100; // Default 100ms
$override_color = isset($_GET['color']) ? trim($_GET['color']) : ''; // Optional color override

// Load room data
$room = null;
$teams = [];
$error = '';

if (!empty($room_code)) {
    $room = getBuzzerRoomByCode($conn, $room_code);
    
    if ($room) {
        // Get teams for this room - make sure we get ALL teams
        $sql = "SELECT * FROM buzzer_teams WHERE room_id = ? ORDER BY id ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $room['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($team = $result->fetch_assoc()) {
            // Get team notes
            $team['notes'] = getTeamNotes($conn, $team['id']);
            $teams[] = $team;
        }
        
        // Get buzzer state
        $buzzer_state = getBuzzerState($conn, $room['id']);
        
    } else {
        $error = 'Raum nicht gefunden';
    }
} else {
    $error = 'Kein Raum-Code angegeben';
}

// Calculate how many teams we have (for layout)
$team_count = count($teams);

// Validate color override if provided
$override_color_valid = false;
if (!empty($override_color)) {
    // Check if it's a valid hex color
    if (preg_match('/^#?([a-f0-9]{6}|[a-f0-9]{3})$/i', $override_color)) {
        // Add # if missing
        if ($override_color[0] !== '#') {
            $override_color = '#' . $override_color;
        }
        $override_color_valid = true;
    }
}

// Minimal header - no external dependencies except Font Awesome
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buzzer OBS View</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Reset and base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }
        
        body {
            background-color: transparent;
            color: #fff;
            overflow: hidden;
            width: 1920px;
            height: 300px;
            position: relative;
        }
        
        /* Background overlay - semi-transparent */
        .background-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: -1;
            border-radius: 15px;
        }
        
        /* Main container */
        .obs-container {
            padding: 15px;
            height: 100%;
            display: flex;
            width: 100%;
        }
        
        /* Teams container */
        .teams-container {
            display: grid;
            grid-template-columns: repeat(<?php echo $team_count; ?>, 1fr);
            gap: 15px;
            width: 100%;
            height: 100%;
        }
        
        /* Team card */
        .team-card {
            background: rgba(44, 62, 80, 0.7);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            border: 3px solid <?php echo $override_color_valid ? $override_color : 'currentColor'; ?>;
            display: flex;
            flex-direction: column;
            transform: scale(1);
            transition: transform 0.3s ease, box-shadow 0.3s ease, background-color 0.5s ease;
        }
        
        .team-card.active-buzzer {
            background-color: rgba(46, 204, 113, 0.3); /* Green background for active buzzer */
            border-color: #2ecc71 !important;
            box-shadow: 0 10px 25px rgba(46, 204, 113, 0.4);
        }
        
        .team-card.highlight {
            transform: scale(1.05);
            z-index: 5;
        }
        
        .team-header {
            padding: 10px;
            text-align: center;
            flex-shrink: 0;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: <?php echo $override_color_valid ? $override_color : 'currentColor'; ?>;
        }
        
        .team-header h3 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }
        
        .buzzer-indicator {
            display: none;
            margin-left: 10px;
            color: #fff;
            font-size: 20px;
            animation: blink 1s infinite;
        }
        
        .active-buzzer .buzzer-indicator {
            display: inline-block;
        }
        
        .team-points {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 5px;
        }
        
        .points-label {
            font-size: 16px;
            color: rgba(255,255,255,0.8);
        }
        
        .points-value {
            font-size: 32px;
            font-weight: 700;
            text-shadow: 0 2px 5px rgba(0,0,0,0.3);
        }
        
        /* Team notes */
        .team-notes {
            flex: 1;
            padding: 10px;
            font-size: 16px;
            line-height: 1.4;
            color: #fff;
            overflow-y: auto;
            background-color: rgba(0, 0, 0, 0.3);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* Buzzer status */
        .buzzer-status {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: rgba(0,0,0,0.9);
            padding: 15px 30px;
            border-radius: 10px;
            border: 2px solid #3498db;
            box-shadow: 0 5px 20px rgba(0,0,0,0.4);
            z-index: 10;
            display: none; /* Initially hidden, shown when buzzer is pressed */
            text-align: center;
        }
        
        .buzzer-status.active {
            display: block;
            animation: fadeIn 0.5s ease, pulse 2s infinite;
        }
        
        .buzzer-status h3 {
            font-size: 28px;
            margin-bottom: 10px;
            color: #3498db;
        }
        
        .buzzer-status p {
            font-size: 22px;
            margin: 5px 0;
        }
        
        /* Error message */
        .error-message {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: rgba(231, 76, 60, 0.9);
            padding: 20px 30px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            max-width: 80%;
        }
        
        .error-message h2 {
            font-size: 28px;
            margin-bottom: 15px;
        }
        
        .error-message p {
            font-size: 18px;
            margin-bottom: 15px;
        }
        
        .usage-info {
            background-color: rgba(0,0,0,0.7);
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            font-size: 14px;
            color: rgba(255,255,255,0.8);
        }
        
        /* Scrollbar styles */
        ::-webkit-scrollbar {
            width: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.2);
            border-radius: 3px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 5px 20px rgba(0,0,0,0.4), 0 0 0 0 rgba(52, 152, 219, 0.7); }
            70% { box-shadow: 0 5px 20px rgba(0,0,0,0.4), 0 0 0 15px rgba(52, 152, 219, 0); }
            100% { box-shadow: 0 5px 20px rgba(0,0,0,0.4), 0 0 0 0 rgba(52, 152, 219, 0); }
        }
        
        @keyframes teamHighlight {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        @keyframes blink {
            0% { opacity: 1; }
            50% { opacity: 0.3; }
            100% { opacity: 1; }
        }
        
        .buzz-animation {
            animation: teamHighlight 1s ease;
        }
    </style>
</head>
<body>
    <div class="background-overlay"></div>
    <audio id="buzzer-sound" src="/buzzer-raum/assets/sounds/buzzer.mp3" preload="auto"></audio>
    
    <?php if ($error): ?>
    <div class="error-message">
        <h2><i class="fas fa-exclamation-triangle"></i> Fehler</h2>
        <p><?php echo htmlspecialchars($error); ?></p>
        
        <div class="usage-info">
            <p><strong>Verwendung:</strong> obs.php?code=RAUMCODE</p>
            <p><strong>Beispiel:</strong> obs.php?code=ABC123</p>
            <p><strong>Optional:</strong> obs.php?code=ABC123&color=3498db (um alle Team-Farben zu überschreiben)</p>
        </div>
    </div>
    <?php else: ?>
    <div class="obs-container">
        <div class="buzzer-status" id="buzzer-status">
            <h3><i class="fas fa-bell"></i> Buzzer gedrückt!</h3>
            <p>Von: <span id="pressed-by-name"></span></p>
            <p>Team: <span id="pressed-by-team"></span></p>
        </div>
        
        <div class="teams-container">
            <?php foreach ($teams as $team): ?>
            <div class="team-card" id="team-card-<?php echo $team['id']; ?>" 
                 <?php if (!$override_color_valid): ?>style="border-color: <?php echo $team['color']; ?>;"<?php endif; ?> 
                 data-team-id="<?php echo $team['id']; ?>">
                <div class="team-header" 
                     <?php if (!$override_color_valid): ?>style="background-color: <?php echo $team['color']; ?>;"<?php endif; ?>>
                    <h3><?php echo htmlspecialchars($team['name']); ?></h3>
                    <div class="buzzer-indicator">
                        <i class="fas fa-bell"></i>
                    </div>
                </div>
                <div class="team-points">
                    <span class="points-value" id="team-<?php echo $team['id']; ?>-points"><?php echo $team['points']; ?></span>
                    <span class="points-label">Punkte</span>
                </div>
                <div class="team-notes" id="team-<?php echo $team['id']; ?>-notes">
                    <?php echo nl2br(htmlspecialchars($team['notes'])); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>
        // Variables for buzzer state
        let lastBuzzerState = {
            is_active: true,
            pressed_by: null,
            team_id: null
        };
        
        // Audio element
        const buzzerSound = document.getElementById('buzzer-sound');
        
        // Map to store original team notes HTML
        const teamNotesOriginal = {};
        
        // Initialize team notes
        document.querySelectorAll('.team-notes').forEach(element => {
            const teamId = element.id.replace('team-', '').replace('-notes', '');
            teamNotesOriginal[teamId] = element.innerHTML;
        });
        
        // Start polling when page loads
        document.addEventListener('DOMContentLoaded', function() {
            startStatePolling();
        });
        
        // Function to update the room state
        function updateRoomState() {
            fetch(`/buzzer-raum/api/get_state.php?room_id=<?php echo $room['id']; ?>&t=${new Date().getTime()}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Check if buzzer state has changed from active to inactive (someone pressed it)
                    if (lastBuzzerState.is_active && !data.buzzer.is_active) {
                        // Play buzzer sound
                        buzzerSound.play();
                        
                        // Show buzzer status notification
                        showBuzzerNotification(data.buzzer);
                        
                        // Mark the team that pressed the buzzer
                        markBuzzerTeam(data.buzzer.team_id);
                    } else if (!lastBuzzerState.is_active && data.buzzer.is_active) {
                        // Buzzer was reset by host
                        clearBuzzerTeam();
                    }
                    
                    // Update teams info (points and notes)
                    data.teams.forEach(team => {
                        // Update points
                        const pointsDisplay = document.getElementById(`team-${team.id}-points`);
                        if (pointsDisplay) {
                            // Animate points change if different
                            const currentPoints = parseInt(pointsDisplay.textContent);
                            if (currentPoints !== team.points) {
                                animatePointsChange(pointsDisplay, currentPoints, team.points);
                            }
                        }
                        
                        // Update team notes
                        if (team.notes !== undefined) {
                            const notesElement = document.getElementById(`team-${team.id}-notes`);
                            if (notesElement) {
                                // Only update if content changed
                                const newNotesHTML = convertToHTML(team.notes);
                                if (notesElement.innerHTML !== newNotesHTML) {
                                    notesElement.innerHTML = newNotesHTML;
                                }
                            }
                        }
                    });
                    
                    // Save current state for next comparison
                    lastBuzzerState = {
                        is_active: data.buzzer.is_active,
                        pressed_by: data.buzzer.pressed_by,
                        team_id: data.buzzer.team_id
                    };
                } else {
                    console.error('Error in API response:', data.error);
                }
            })
            .catch(error => {
                console.error('Error fetching state:', error);
            });
        }
        
        // Function to show buzzer notification
        function showBuzzerNotification(buzzerData) {
            const buzzerStatus = document.getElementById('buzzer-status');
            if (buzzerStatus) {
                // Update notification content
                const pressedByName = document.getElementById('pressed-by-name');
                const pressedByTeam = document.getElementById('pressed-by-team');
                
                if (pressedByName) {
                    pressedByName.textContent = buzzerData.pressed_by_name;
                }
                
                if (pressedByTeam) {
                    pressedByTeam.textContent = buzzerData.team_name;
                    pressedByTeam.style.color = buzzerData.team_color;
                }
                
                // Show notification
                buzzerStatus.classList.add('active');
                
                // Hide notification after 3 seconds
                setTimeout(() => {
                    buzzerStatus.classList.remove('active');
                }, 3000);
            }
        }
        
        // Function to mark the team that pressed the buzzer
        function markBuzzerTeam(teamId) {
            // First clear any existing marked teams
            clearBuzzerTeam();
            
            // Mark the team that pressed the buzzer
            const teamCard = document.getElementById(`team-card-${teamId}`);
            if (teamCard) {
                // Add active-buzzer class
                teamCard.classList.add('active-buzzer');
                teamCard.classList.add('highlight');
                
                // Add animation class
                teamCard.classList.add('buzz-animation');
                
                // Remove animation class after animation completes
                setTimeout(() => {
                    teamCard.classList.remove('buzz-animation');
                }, 1000);
            }
        }
        
        // Function to clear buzzer team markings
        function clearBuzzerTeam() {
            document.querySelectorAll('.team-card').forEach(card => {
                card.classList.remove('active-buzzer');
                card.classList.remove('highlight');
            });
        }
        
        // Function to animate points change
        function animatePointsChange(element, fromValue, toValue) {
            // Save original style
            const originalColor = element.style.color;
            const originalSize = element.style.fontSize;
            
            // Determine color based on change
            const changeColor = toValue > fromValue ? '#2ecc71' : '#e74c3c';
            
            // Highlight change
            element.style.color = changeColor;
            element.style.fontSize = '36px';
            
            // Animate count
            const duration = 1000; // 1 second
            const start = Date.now();
            
            const timer = setInterval(() => {
                const timePassed = Date.now() - start;
                
                if (timePassed >= duration) {
                    clearInterval(timer);
                    element.textContent = toValue;
                    
                    // Restore original style
                    setTimeout(() => {
                        element.style.color = originalColor;
                        element.style.fontSize = originalSize;
                    }, 500);
                    
                    return;
                }
                
                // Easing function
                const progress = timePassed / duration;
                const easeProgress = easeOutCubic(progress);
                
                // Calculate current value
                const currentValue = Math.round(fromValue + (toValue - fromValue) * easeProgress);
                element.textContent = currentValue;
            }, 16);
        }
        
        // Easing function for smoother animation
        function easeOutCubic(x) {
            return 1 - Math.pow(1 - x, 3);
        }
        
        // Function to convert text to HTML with line breaks
        function convertToHTML(text) {
            if (!text) return '';
            return text.replace(/\n/g, '<br>').replace(/\t/g, '&nbsp;&nbsp;&nbsp;&nbsp;');
        }
        
        // Start polling for room state updates
        function startStatePolling() {
            // Update state immediately
            updateRoomState();
            
            // Set up polling interval
            setInterval(updateRoomState, <?php echo $refresh_rate; ?>);
        }
    </script>
    <?php endif; ?>
</body>
</html>