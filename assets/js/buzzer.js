// buzzer-raum/assets/js/buzzer.js

document.addEventListener('DOMContentLoaded', function() {
    // Get DOM elements
    const buzzerButton = document.getElementById('buzzer-button');
    const roomId = document.body.getAttribute('data-room-id');
    const userId = document.body.getAttribute('data-user-id');
    const isHost = document.body.getAttribute('data-is-host') === '1';
    const teamId = document.body.getAttribute('data-team-id');
    const buzzerSound = document.getElementById('buzzer-sound');
    
    // Initialize buzzer state polling
    let statePollingInterval;
    let lastBuzzerState = { is_active: true, pressed_by: null };
    
    // Initialize team notes
    const teamNoteTextareas = document.querySelectorAll('.team-note-textarea');
    let noteUpdateTimeout;
    
    // Start state polling when page loads
    startStatePolling();
    
    // Stop polling when page is hidden
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') {
            clearInterval(statePollingInterval);
        } else {
            // Resume polling when page is visible again
            startStatePolling();
        }
    });
    
    // Handle buzzer button click
    if (buzzerButton) {
        buzzerButton.addEventListener('click', function() {
            if (buzzerButton.classList.contains('disabled')) {
                return;
            }
            
            // Disable buzzer immediately to prevent double clicks
            buzzerButton.classList.add('disabled');
            
            // Play buzzer sound
            buzzerSound.play();
            
            // Send buzzer press to server
            fetch('/buzzer-raum/api/press_buzzer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'room_id': roomId,
                    'team_id': teamId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Error pressing buzzer:', data.error);
                    // Re-enable buzzer if there was an error
                    buzzerButton.classList.remove('disabled');
                }
                // Update UI immediately
                updateRoomState();
            })
            .catch(error => {
                console.error('Error:', error);
                // Re-enable buzzer if there was an error
                buzzerButton.classList.remove('disabled');
            });
        });
    }
    
    // Handle team note updates
    if (teamNoteTextareas.length > 0) {
        teamNoteTextareas.forEach(textarea => {
            textarea.addEventListener('input', function() {
                const teamId = this.getAttribute('data-team-id');
                const content = this.value;
                
                // Clear any existing timeout
                clearTimeout(noteUpdateTimeout);
                
                // Set a new timeout to update after user stops typing
                noteUpdateTimeout = setTimeout(() => {
                    updateTeamNotes(teamId, content);
                }, 1000);
            });
        });
    }
    
    // Handle team note tab switching (host only)
    const teamNoteTabs = document.querySelectorAll('.team-note-tab');
    if (teamNoteTabs.length > 0) {
        teamNoteTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const teamId = this.getAttribute('data-team-id');
                
                // Update active tab
                teamNoteTabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Update active editor
                const editors = document.querySelectorAll('.team-note-editor');
                editors.forEach(editor => {
                    editor.classList.remove('active');
                    if (editor.getAttribute('data-team-id') === teamId) {
                        editor.classList.add('active');
                    }
                });
            });
        });
    }
    
    // Add event listeners for point controls (host only)
    if (isHost) {
        const pointButtons = document.querySelectorAll('.add-point');
        pointButtons.forEach(button => {
            button.addEventListener('click', function() {
                const teamId = this.getAttribute('data-team-id');
                const points = this.getAttribute('data-points');
                
                addPoints(teamId, points);
            });
        });
    }
    
    // Function to update team notes
    function updateTeamNotes(teamId, content) {
        fetch('/buzzer-raum/api/update_notes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                'room_id': roomId,
                'team_id': teamId,
                'content': content
            })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error('Error updating team notes:', data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }
    
    // Function to add points to a team
    function addPoints(teamId, points) {
        fetch('/buzzer-raum/api/add_points.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                'room_id': roomId,
                'team_id': teamId,
                'points': points
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the points display
                const pointsDisplay = document.getElementById(`team-${teamId}-points`);
                if (pointsDisplay) {
                    pointsDisplay.textContent = data.points;
                }
            } else {
                console.error('Error adding points:', data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }
    
    // Function to update the room state
    function updateRoomState() {
        fetch(`/buzzer-raum/api/get_state.php?room_id=${roomId}&t=${new Date().getTime()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Check if buzzer state has changed from active to inactive (someone pressed it)
                if (lastBuzzerState.is_active && !data.buzzer.is_active) {
                    console.log("Buzzer changed from active to inactive - playing sound");
                    // Play buzzer sound for everyone
                    buzzerSound.play();
                    
                    // Show visual alert
                    showBuzzerAlert(data.buzzer.pressed_by_name, data.buzzer.team_name, data.buzzer.team_color);
                }
                
                // Check if pressed_by user has changed
                if (data.buzzer.pressed_by !== lastBuzzerState.pressed_by) {
                    console.log("Pressed by user changed:", data.buzzer.pressed_by_name);
                    
                    // Update host interface immediately if we're the host
                    if (isHost) {
                        updateHostInterface(data.buzzer);
                    }
                }
                
                // Save current state for next comparison
                lastBuzzerState = {
                    is_active: data.buzzer.is_active,
                    pressed_by: data.buzzer.pressed_by
                };
                
                // Update buzzer button state
                if (buzzerButton) {
                    if (data.buzzer.is_active && (isHost || teamId)) {
                        buzzerButton.classList.remove('disabled');
                    } else {
                        buzzerButton.classList.add('disabled');
                    }
                }
                
                // Update teams info
                data.teams.forEach(team => {
                    // Update points
                    const pointsDisplay = document.getElementById(`team-${team.id}-points`);
                    if (pointsDisplay) {
                        pointsDisplay.textContent = team.points;
                    }
                    
                    // Update team members
                    const teamMembersList = document.getElementById(`team-${team.id}-members`);
                    if (teamMembersList) {
                        teamMembersList.innerHTML = '';
                        team.members.forEach(member => {
                            const li = document.createElement('li');
                            li.textContent = member.username;
                            teamMembersList.appendChild(li);
                        });
                    }
                    
                    // Update notes if this team's notes are provided
                    if (team.notes !== undefined) {
                        const notesTextarea = document.getElementById(`team-${team.id}-notes`);
                        if (notesTextarea && notesTextarea.value !== team.notes) {
                            // Only update if the content is different and the textarea is not focused
                            if (document.activeElement !== notesTextarea) {
                                notesTextarea.value = team.notes;
                            }
                        }
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }
    
    // Function to update the host interface
    function updateHostInterface(buzzerData) {
        // Update buzzer status text
        const buzzerStatusText = document.getElementById('buzzer-status-text');
        if (buzzerStatusText) {
            buzzerStatusText.textContent = buzzerData.is_active ? 'Aktiv' : 'Inaktiv';
            buzzerStatusText.className = buzzerData.is_active ? 'active' : 'inactive';
        }
        
        // Update pressed by info
        const pressedByName = document.getElementById('pressed-by-name');
        const pressedByTeam = document.getElementById('pressed-by-team');
        
        if (pressedByName && !buzzerData.is_active) {
            pressedByName.textContent = buzzerData.pressed_by_name;
            
            if (pressedByTeam) {
                pressedByTeam.textContent = buzzerData.team_name;
                pressedByTeam.style.color = buzzerData.team_color;
            }
            
            // Make the text flash to draw attention
            flashElement(pressedByName.parentElement);
        }
        
        // Update reset button
        const resetButton = document.getElementById('reset-buzzer');
        if (resetButton) {
            resetButton.disabled = buzzerData.is_active;
        }
        
        // Update toggle button
        const toggleButton = document.getElementById('toggle-buzzer');
        if (toggleButton) {
            if (buzzerData.is_active) {
                toggleButton.className = 'btn btn-danger';
                toggleButton.innerHTML = '<i class="fas fa-pause"></i> Buzzer deaktivieren';
            } else {
                toggleButton.className = 'btn btn-success';
                toggleButton.innerHTML = '<i class="fas fa-play"></i> Buzzer aktivieren';
            }
        }
    }
    
    // Function to flash an element to draw attention
    function flashElement(element) {
        if (!element) return;
        
        // Add flash class
        element.classList.add('flash-highlight');
        
        // Remove class after animation
        setTimeout(() => {
            element.classList.remove('flash-highlight');
        }, 1500);
    }
    
    // Function to show buzzer alert
    function showBuzzerAlert(userName, teamName, teamColor) {
        // Check if alert container exists
        let alertContainer = document.getElementById('buzzer-alert-container');
        
        // Create alert container if it doesn't exist
        if (!alertContainer) {
            alertContainer = document.createElement('div');
            alertContainer.id = 'buzzer-alert-container';
            alertContainer.style.position = 'fixed';
            alertContainer.style.top = '20px';
            alertContainer.style.left = '50%';
            alertContainer.style.transform = 'translateX(-50%)';
            alertContainer.style.zIndex = '9999';
            alertContainer.style.textAlign = 'center';
            document.body.appendChild(alertContainer);
        }
        
        // Create alert element
        const alert = document.createElement('div');
        alert.className = 'buzzer-alert';
        alert.style.padding = '15px 25px';
        alert.style.margin = '10px 0';
        alert.style.backgroundColor = teamColor || '#e74c3c';
        alert.style.color = '#fff';
        alert.style.borderRadius = '10px';
        alert.style.boxShadow = '0 5px 15px rgba(0, 0, 0, 0.3)';
        alert.style.animation = 'buzzerAlertAnimation 0.5s ease-in-out';
        alert.style.fontWeight = 'bold';
        alert.style.fontSize = '1.2rem';
        
        // Create alert content
        alert.innerHTML = `<i class="fas fa-bell" style="margin-right: 10px;"></i> <strong>${userName || 'Jemand'}</strong> (${teamName || 'Team'}) hat den Buzzer gedrückt!`;
        
        // Add alert to container
        alertContainer.appendChild(alert);
        
        // Remove alert after 3 seconds
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s ease';
            setTimeout(() => {
                if (alertContainer.contains(alert)) {
                    alertContainer.removeChild(alert);
                }
            }, 500);
        }, 3000);
    }
    
    // Start polling for room state updates
    function startStatePolling() {
        // Update state immediately
        updateRoomState();
        
        // Set up polling interval (every 500ms - very frequent checking)
        statePollingInterval = setInterval(updateRoomState, 500);
    }
    
    // Add CSS for animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes buzzerAlertAnimation {
            0% { transform: scale(0.7); opacity: 0; }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }
        
        @keyframes flashAnimation {
            0% { background-color: transparent; }
            50% { background-color: rgba(231, 76, 60, 0.3); }
            100% { background-color: transparent; }
        }
        
        .flash-highlight {
            animation: flashAnimation 1.5s ease;
            border-radius: 5px;
        }
        
        .buzzer-status p {
            padding: 5px;
            transition: background-color 0.3s ease;
        }
    `;
    document.head.appendChild(style);
});