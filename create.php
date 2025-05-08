<?php
// buzzer-raum/create.php

// Initialize the session
session_start();

// Include configuration files
require_once "../includes/config.php";
require_once "../includes/auth.php";
require_once "../includes/db.php";
require_once "includes/buzzer_functions.php";

// Redirect to login page if not logged in
requireLogin();

// Process form submission
$error = $success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $team_count = (int)$_POST["team_count"];
    
    // Validate input
    if (empty($name)) {
        $error = "Bitte gib einen Namen für den Buzzer Raum ein.";
    } elseif ($team_count < 2 || $team_count > 10) {
        $error = "Die Anzahl der Teams muss zwischen 2 und 10 liegen.";
    } else {
        // Create buzzer room
        $result = createBuzzerRoom($conn, $name, $_SESSION["id"], $team_count);
        
        if ($result['success']) {
            // Redirect to the new room
            header("Location: room.php?id=" . $result['room_id']);
            exit;
        } else {
            $error = $result['error'];
        }
    }
}

$page_title = "Buzzer Raum erstellen";
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
        body, .container, main, .buzzer-header, .buzzer-section, .create-form-container {
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
                <h1>Buzzer Raum erstellen</h1>
                <a href="/buzzer-raum/" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Zurück zur Übersicht
                </a>
            </div>

            <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="create-form-container">
                <div class="create-form-card">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="buzzer-form">
                        <div class="form-group">
                            <label for="name">Name des Buzzer Raums</label>
                            <input type="text" name="name" id="name" class="form-control" required>
                            <small class="form-text">Gib einen Namen für deinen Buzzer Raum ein, z.B. "Quiz Night" oder "Team Challenge".</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="team_count">Anzahl der Teams</label>
                            <input type="number" name="team_count" id="team_count" min="2" max="10" value="2" class="form-control" required>
                            <small class="form-text">Wähle, wie viele Teams am Buzzer Raum teilnehmen können (2-10).</small>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> Buzzer Raum erstellen
                            </button>
                        </div>
                    </form>
                </div>
            </div>
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
            
            const elements = document.querySelectorAll('.buzzer-header, .create-form-container');
            elements.forEach(function(el) {
                el.style.opacity = '1';
                el.style.visibility = 'visible';
            });
            
            console.log('Seite vollständig geladen, Sichtbarkeit erzwungen');
        });
    </script>
</body>
</html>