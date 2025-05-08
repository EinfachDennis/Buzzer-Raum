<?php
// buzzer-raum/index.php

// Initialize the session
session_start();

// Include configuration files
require_once "../includes/config.php";
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "includes/buzzer_functions.php";

// Redirect to login page if not logged in
requireLogin();

// Get active buzzer rooms
$active_rooms = getActiveBuzzerRooms($conn);

// Get rooms hosted by the current user
$hosted_rooms = getUserHostedRooms($conn, $_SESSION["id"]);

$page_title = "Buzzer Räume";
$extra_css = ["/assets/css/main.css", "/buzzer-raum/assets/css/buzzer.css", "/buzzer-raum/assets/css/buzzer-fix.css"];
$extra_js = ["/buzzer-raum/assets/js/buzzer-animation.js", "/buzzer-raum/assets/js/buzzer.js"];
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
        body, .container, main, .buzzer-header, .buzzer-actions, .buzzer-section {
            opacity: 1 !important;
            visibility: visible !important;
        }
    </style>
</head>
<body>
    <div class="background-overlay"></div>
    <div class="container">
        <header>
            <nav>
                <div class="logo">
                    <h1>Interactive Portal</h1>
                </div>
                <div class="menu">
                    <a href="/dashboard.php">Dashboard</a>
                    <?php if (isAdmin()): ?>
                    <a href="/admin.php">Admin</a>
                    <?php endif; ?>
                    <a href="/logout.php">Logout</a>
                </div>
            </nav>
        </header>
        <main>
            <div class="buzzer-header">
                <h1>Buzzer Räume</h1>
            </div>

            <div class="buzzer-actions">
                <a href="create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Neuen Buzzer Raum erstellen
                </a>
                
                <div class="join-form">
                    <form action="join.php" method="get">
                        <div class="form-group">
                            <input type="text" name="code" placeholder="Raum-Code eingeben" class="form-control" required>
                            <button type="submit" class="btn btn-secondary">
                                <i class="fas fa-sign-in-alt"></i> Beitreten
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!empty($hosted_rooms)): ?>
            <div class="buzzer-section">
                <h2>Deine Buzzer Räume</h2>
                <div class="room-cards">
                    <?php foreach ($hosted_rooms as $room): ?>
                        <div class="room-card">
                            <div class="room-card-header">
                                <h3><?php echo htmlspecialchars($room['name']); ?></h3>
                                <span class="host-badge">Host</span>
                            </div>
                            <div class="room-card-body">
                                <p><strong>Code:</strong> <?php echo htmlspecialchars($room['room_code']); ?></p>
                                <p><strong>Teams:</strong> <?php echo htmlspecialchars($room['team_count']); ?></p>
                                <p><strong>Teilnehmer:</strong> <?php echo htmlspecialchars($room['participant_count']); ?></p>
                                <p><strong>Erstellt:</strong> <?php echo date("d.m.Y H:i", strtotime($room['created_at'])); ?></p>
                            </div>
                            <div class="room-card-footer">
                                <a href="room.php?id=<?php echo $room['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-door-open"></i> Betreten
                                </a>
                                <?php if ($room['active']): ?>
                                <a href="#" class="btn btn-danger btn-sm" onclick="deactivateRoom(<?php echo $room['id']; ?>)">
                                    <i class="fas fa-times"></i> Deaktivieren
                                </a>
                                <?php else: ?>
                                <a href="#" class="btn btn-success btn-sm" onclick="activateRoom(<?php echo $room['id']; ?>)">
                                    <i class="fas fa-check"></i> Aktivieren
                                </a>
                                <?php endif; ?>
                                <a href="#" class="btn btn-danger btn-sm" onclick="deleteRoom(<?php echo $room['id']; ?>)">
                                    <i class="fas fa-trash"></i> Löschen
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (empty($hosted_rooms)): ?>
            <div class="no-rooms">
                <p>Du hast noch keine Buzzer Räume erstellt.</p>
                <p>Du kannst einen neuen Raum erstellen oder einem bestehenden Raum beitreten, indem du den Raum-Code eingibst.</p>
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
        
        // Funktion zum Löschen eines Buzzer-Raums
        function deleteRoom(roomId) {
            if (confirm('Möchtest du diesen Buzzer-Raum wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.')) {
                // API-Aufruf zum Löschen des Raums
                fetch('/buzzer-raum/api/delete_room.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        'room_id': roomId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Seite neu laden, um die Liste zu aktualisieren
                        window.location.reload();
                    } else {
                        alert('Fehler beim Löschen des Raums: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Ein Fehler ist aufgetreten. Bitte versuche es erneut.');
                });
            }
        }
        
        // Funktion zum Deaktivieren eines Raums
        function deactivateRoom(roomId) {
            // Bestehende Funktionalität, falls vorhanden
            // Da dies in der Original-Datei referenziert wird, füge ich hier eine leere Funktion hinzu
            console.log('Deaktiviere Raum:', roomId);
            alert('Die Funktion zum Deaktivieren ist noch nicht implementiert.');
        }
        
        // Funktion zum Aktivieren eines Raums
        function activateRoom(roomId) {
            // Bestehende Funktionalität, falls vorhanden
            // Da dies in der Original-Datei referenziert wird, füge ich hier eine leere Funktion hinzu
            console.log('Aktiviere Raum:', roomId);
            alert('Die Funktion zum Aktivieren ist noch nicht implementiert.');
        }
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
            
            const elements = document.querySelectorAll('.buzzer-header, .buzzer-actions, .buzzer-section, .room-cards, .no-rooms');
            elements.forEach(function(el) {
                el.style.opacity = '1';
                el.style.visibility = 'visible';
            });
            
            console.log('Seite vollständig geladen, Sichtbarkeit erzwungen');
        });
    </script>
</body>
</html>