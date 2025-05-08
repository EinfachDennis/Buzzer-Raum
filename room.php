<?php
// buzzer-raum/room.php

// Initialize the session
session_start();

// Include configuration files
require_once "../includes/config.php";
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "includes/buzzer_functions.php";

// Redirect to login page if not logged in
requireLogin();

// Check if room ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: /buzzer-raum/");
    exit;
}

$room_id = (int)$_GET['id'];
$room = getBuzzerRoomById($conn, $room_id);

// Check if room exists
if (!$room) {
    header("Location: /buzzer-raum/");
    exit;
}

// Check if the current user is the host
$is_host = isRoomHost($conn, $room_id, $_SESSION['id']);

// Get teams for this room
$teams = getTeamsForRoom($conn, $room_id);

// Create teams if none exist and current user is host
if ($is_host && empty($teams)) {
    $team_colors = ['#3498db', '#e74c3c', '#2ecc71', '#f39c12', '#9b59b6', '#1abc9c', '#e67e22', '#34495e', '#d35400', '#16a085'];
    
    for ($i = 1; $i <= $room['team_count']; $i++) {
        $team_name = "Team " . $i;
        $team_color = $team_colors[($i - 1) % count($team_colors)];
        createTeam($conn, $room_id, $team_name, $team_color);
    }
    
    // Refresh teams list
    $teams = getTeamsForRoom($conn, $room_id);
}

// Get user's team (if any)
$user_team = getUserTeamInRoom($conn, $room_id, $_SESSION['id']);

// Process team selection
if (isset($_POST['join_team']) && isset($_POST['team_id'])) {
    $team_id = (int)$_POST['team_id'];
    
    // Make sure team exists and belongs to this room
    $valid_team = false;
    foreach ($teams as $team) {
        if ($team['id'] == $team_id) {
            $valid_team = true;
            break;
        }
    }
    
    if ($valid_team) {
        $result = joinTeam($conn, $team_id, $_SESSION['id']);
        if ($result['success']) {
            // Refresh user's team
            $user_team = getUserTeamInRoom($conn, $room_id, $_SESSION['id']);
        }
    }
}

// Process team creation (host only)
if ($is_host && isset($_POST['create_team'])) {
    $team_name = trim($_POST['team_name']);
    $team_color = $_POST['team_color'];
    
    if (!empty($team_name)) {
        createTeam($conn, $room_id, $team_name, $team_color);
        
        // Refresh teams list
        $teams = getTeamsForRoom($conn, $room_id);
    }
}

// Get buzzer state
$buzzer_state = getBuzzerState($conn, $room_id);

// Get team members for each team
$team_members = [];
foreach ($teams as $team) {
    $team_members[$team['id']] = getTeamMembers($conn, $team['id']);
}

// Get team notes
$team_notes = [];
if ($is_host) {
    // Host can see all team notes
    foreach ($teams as $team) {
        $team_notes[$team['id']] = getTeamNotes($conn, $team['id']);
    }
} elseif ($user_team) {
    // User can only see their team's notes
    $team_notes[$user_team['id']] = getTeamNotes($conn, $user_team['id']);
}

$page_title = $room['name'] . " - Buzzer Raum";
$extra_css = ["/assets/css/main.css", "/buzzer-raum/assets/css/buzzer.css", "/buzzer-raum/assets/css/buzzer-fix.css"];
$extra_js = ["/buzzer-raum/assets/js/buzzer-animation.js", "/buzzer-raum/assets/js/buzzer.js"];
if ($is_host) {
    $extra_js[] = "/buzzer-raum/assets/js/host.js";
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Interactive Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <?php foreach ($extra_css as $css): ?>
    <link rel="stylesheet" href="<?php echo $css; ?>">
    <?php endforeach; ?>
    <style>
        :root {
            --bg-image: url('/assets/img/default-bg.jpg');
        }
        
        /* Inline-Styles zur Sicherstellung der Sichtbarkeit */
        body, .container, main, .buzzer-room-header, .team-selection, .buzzer-room-content,
        .buzzer-container, .teams-scoreboard, .team-cards, .team-note-editor {
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        #buzzer-button, .buzzer-inner {
            opacity: 1 !important;
            visibility: visible !important;
        }
    </style>
</head>
<body class="buzzer-room-page" data-room-id="<?php echo $room_id; ?>" data-user-id="<?php echo $_SESSION['id']; ?>" data-is-host="<?php echo $is_host ? '1' : '0'; ?>" <?php if ($user_team): ?>data-team-id="<?php echo $user_team['id']; ?>"<?php endif; ?>>
    <audio id="buzzer-sound" src="/buzzer-raum/assets/sounds/buzzer.mp3" preload="auto"></audio>
    <div class="background-overlay"></div>
    <div class="container">
        <header>
            <nav>
                <div class="logo">
                    <h1>Interactive Portal</h1>
                </div>
                <div class="menu">
                    <a href="/dashboard.php">Dashboard</a>
                    <a href="/buzzer-raum/">Buzzer Räume</a>
                    <?php if (isAdmin()): ?>
                    <a href="/admin.php">Admin</a>
                    <?php endif; ?>
                    <a href="/logout.php">Logout</a>
                </div>
            </nav>
        </header>
        <main>
            <div class="buzzer-room-header">
                <div class="room-info">
                    <h1><?php echo htmlspecialchars($room['name']); ?></h1>
                    <p class="room-code">Raum-Code: <strong><?php echo htmlspecialchars($room['room_code']); ?></strong></p>
                </div>
                <div class="room-actions">
                    <?php if ($is_host): ?>
                    <div class="host-badge">Host</div>
                    <?php endif; ?>
                    <a href="/buzzer-raum/" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Zurück zur Übersicht
                    </a>
                </div>
            </div>

            <?php if (empty($user_team) && !$is_host): ?>
            <div class="team-selection">
                <h2>Wähle dein Team</h2>
                
                <?php if (empty($teams)): ?>
                <div class="alert alert-info">Warte, bis der Host Teams erstellt hat.</div>
                <?php else: ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $room_id); ?>" method="post" class="team-selection-form">
                    <div class="team-cards">
                        <?php foreach ($teams as $team): ?>
                        <div class="team-select-card" style="border-color: <?php echo $team['color']; ?>;">
                            <div class="team-card-header" style="background-color: <?php echo $team['color']; ?>;">
                                <h3><?php echo htmlspecialchars($team['name']); ?></h3>
                            </div>
                            <div class="team-card-body">
                                <p><strong>Punkte:</strong> <?php echo $team['points']; ?></p>
                                <p><strong>Mitglieder:</strong> <?php echo count($team_members[$team['id']]); ?></p>
                            </div>
                            <div class="team-card-footer">
                                <button type="submit" name="join_team" value="join" class="btn btn-primary btn-sm" onclick="document.querySelector('input[name=team_id]').value = <?php echo $team['id']; ?>">
                                    <i class="fas fa-user-plus"></i> Beitreten
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="team_id" value="">
                </form>
                <?php endif; ?>
            </div>
            
            <?php else: ?>
            
            <div class="buzzer-room-content">
                <div class="buzzer-container">
                    <?php if ($is_host): ?>
                    <div class="host-controls">
                        <h2>Host-Steuerung</h2>
                        <div class="buzzer-status">
                            <p>Buzzer Status: 
                                <span id="buzzer-status-text" class="<?php echo $buzzer_state['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $buzzer_state['is_active'] ? 'Aktiv' : 'Inaktiv'; ?>
                                </span>
                            </p>
                            <?php if (!$buzzer_state['is_active']): ?>
                            <p>Gedrückt von: <span id="pressed-by-name"><?php echo htmlspecialchars($buzzer_state['pressed_by_name']); ?></span> 
                               (<span id="pressed-by-team" style="color: <?php echo $buzzer_state['team_color']; ?>">
                                    <?php echo htmlspecialchars($buzzer_state['team_name']); ?>
                               </span>)
                            </p>
                            <?php endif; ?>
                        </div>
                        <div class="buzzer-controls">
                            <button id="reset-buzzer" class="btn btn-primary" <?php echo $buzzer_state['is_active'] ? 'disabled' : ''; ?>>
                                <i class="fas fa-redo"></i> Buzzer zurücksetzen
                            </button>
                            
                            <button id="toggle-buzzer" class="btn <?php echo $buzzer_state['is_active'] ? 'btn-danger' : 'btn-success'; ?>">
                                <i class="fas <?php echo $buzzer_state['is_active'] ? 'fa-pause' : 'fa-play'; ?>"></i>
                                <?php echo $buzzer_state['is_active'] ? 'Buzzer deaktivieren' : 'Buzzer aktivieren'; ?>
                            </button>
                        </div>
                        
                        <div class="team-management">
                            <h3>Teams verwalten</h3>
                            <div class="create-team-form">
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $room_id); ?>" method="post">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <input type="text" name="team_name" placeholder="Team Name" class="form-control" required>
                                        </div>
                                        <div class="form-group">
                                            <input type="color" name="team_color" value="#3498db" class="form-control">
                                        </div>
                                        <div class="form-group">
                                            <button type="submit" name="create_team" class="btn btn-primary btn-sm">
                                                <i class="fas fa-plus"></i> Team erstellen
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="buzzer-main">
                        <?php if (!$is_host && $user_team): ?>
                        <div class="user-team-info">
                            <h2>Dein Team: <span style="color: <?php echo $user_team['color']; ?>"><?php echo htmlspecialchars($user_team['name']); ?></span></h2>
                        </div>
                        <?php endif; ?>
                        
                        <div id="buzzer-button" class="<?php echo (!$buzzer_state['is_active'] || ($is_host && !$user_team)) ? 'disabled' : ''; ?>">
                            <div class="buzzer-inner">
                                <i class="fas fa-bell"></i>
                                <span>BUZZER</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="teams-scoreboard">
                    <h2>Teams und Punkte</h2>
                    
                    <div class="teams-grid">
                        <?php foreach ($teams as $team): ?>
                        <div class="team-card" style="border-color: <?php echo $team['color']; ?>;" data-team-id="<?php echo $team['id']; ?>">
                            <div class="team-header" style="background-color: <?php echo $team['color']; ?>;">
                                <h3><?php echo htmlspecialchars($team['name']); ?></h3>
                            </div>
                            <div class="team-body">
                                <div class="team-points">
                                    <span class="points-label">Punkte:</span>
                                    <span class="points-value" id="team-<?php echo $team['id']; ?>-points"><?php echo $team['points']; ?></span>
                                    
                                    <?php if ($is_host): ?>
                                    <div class="points-controls">
                                        <button class="btn btn-sm add-point" data-team-id="<?php echo $team['id']; ?>" data-points="1">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                        <button class="btn btn-sm add-point" data-team-id="<?php echo $team['id']; ?>" data-points="-1">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="team-members">
                                    <h4>Mitglieder:</h4>
                                    <ul id="team-<?php echo $team['id']; ?>-members">
                                        <?php foreach ($team_members[$team['id']] as $member): ?>
                                        <li><?php echo htmlspecialchars($member['username']); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="team-notes-section">
                        <h2>Team Notizen</h2>
                        
                        <?php if ($is_host): ?>
                        <div class="team-notes-tabs">
                            <?php foreach ($teams as $index => $team): ?>
                            <button class="team-note-tab <?php echo $index === 0 ? 'active' : ''; ?>" data-team-id="<?php echo $team['id']; ?>" style="background-color: <?php echo $team['color']; ?>;">
                                <?php echo htmlspecialchars($team['name']); ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="team-notes-content">
                            <?php foreach ($teams as $index => $team): ?>
                            <div class="team-note-editor <?php echo $index === 0 ? 'active' : ''; ?>" id="note-editor-<?php echo $team['id']; ?>" data-team-id="<?php echo $team['id']; ?>">
                                <div class="note-header">
                                    <h3><?php echo htmlspecialchars($team['name']); ?> Notizen</h3>
                                    <p class="note-info">Der Host kann diese Notizen sehen</p>
                                </div>
                                <textarea class="team-note-textarea" id="team-<?php echo $team['id']; ?>-notes" data-team-id="<?php echo $team['id']; ?>"><?php echo isset($team_notes[$team['id']]) ? htmlspecialchars($team_notes[$team['id']]) : ''; ?></textarea>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php elseif ($user_team): ?>
                        <div class="team-note-editor active">
                            <div class="note-header">
                                <h3><?php echo htmlspecialchars($user_team['name']); ?> Notizen</h3>
                                <p class="note-info">Nur dein Team und der Host können diese Notizen sehen</p>
                            </div>
                            <textarea class="team-note-textarea" id="team-<?php echo $user_team['id']; ?>-notes" data-team-id="<?php echo $user_team['id']; ?>"><?php echo isset($team_notes[$user_team['id']]) ? htmlspecialchars($team_notes[$user_team['id']]) : ''; ?></textarea>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
        <footer>
            <p>&copy; <?php echo date('Y'); ?> Interactive Portal. All rights reserved.</p>
        </footer>
    </div>
    
    <!-- GSAP mit Vorsicht behandeln -->
    <script>
        // GSAP-Initialisierungsvariable - wird später geprüft
        window.gsapInitialized = false;
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.5/gsap.min.js"></script>
    
    <!-- Stelle sicher, dass GSAP unsere Elemente nicht animiert -->
    <script>
        // Markiere GSAP als initialisiert
        window.gsapInitialized = true;
    </script>
    
    <script src="/assets/js/main.js"></script>
    <?php foreach ($extra_js as $js): ?>
    <script src="<?php echo $js; ?>"></script>
    <?php endforeach; ?>
    
    <!-- Zusätzliches Sicherheits-Script für Sichtbarkeit -->
    <script>
        // Stelle sicher, dass die Elemente nach allem anderen sichtbar sind
        window.addEventListener('load', function() {
            document.body.style.opacity = '1';
            document.querySelector('.container').style.opacity = '1';
            document.querySelector('main').style.opacity = '1';
            
            const elements = document.querySelectorAll('.buzzer-room-header, .team-selection, .buzzer-room-content, .buzzer-container, .teams-scoreboard, #buzzer-button, .buzzer-inner');
            elements.forEach(function(el) {
                if (el) {
                    el.style.opacity = '1';
                    el.style.visibility = 'visible';
                }
            });
            
            console.log('Seite vollständig geladen, Sichtbarkeit erzwungen');
        });
    </script>
</body>
</html>