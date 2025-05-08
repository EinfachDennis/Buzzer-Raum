// buzzer-raum/assets/js/host.js

document.addEventListener('DOMContentLoaded', function() {
    // Get DOM elements
    const roomId = document.body.getAttribute('data-room-id');
    const resetBuzzerButton = document.getElementById('reset-buzzer');
    const toggleBuzzerButton = document.getElementById('toggle-buzzer');
    
    // Add event listener for reset buzzer button
    if (resetBuzzerButton) {
        resetBuzzerButton.addEventListener('click', function() {
            resetBuzzer();
        });
    }
    
    // Add event listener for toggle buzzer button
    if (toggleBuzzerButton) {
        toggleBuzzerButton.addEventListener('click', function() {
            const isActive = toggleBuzzerButton.classList.contains('btn-danger');
            toggleBuzzer(!isActive); // If it's active (red button), make it inactive, and vice versa
        });
    }
    
    // Function to reset the buzzer
    function resetBuzzer() {
        fetch('/buzzer-raum/api/reset_buzzer.php', {
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
            if (!data.success) {
                console.error('Error resetting buzzer:', data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }
    
    // Function to toggle buzzer state (active/inactive)
    function toggleBuzzer(active) {
        if (active) {
            // Activate buzzer (reset it)
            resetBuzzer();
        } else {
            // Deactivate buzzer (simulate press by host)
            const teamId = document.body.getAttribute('data-team-id');
            
            fetch('/buzzer-raum/api/press_buzzer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'room_id': roomId,
                    'team_id': teamId || '0'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    console.error('Error toggling buzzer:', data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
    }
});