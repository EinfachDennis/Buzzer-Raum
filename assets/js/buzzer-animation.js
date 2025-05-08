// buzzer-raum/assets/js/buzzer-animation.js

document.addEventListener('DOMContentLoaded', function() {
    console.log("Buzzer Animation JS geladen");
    
    // Setze alle Elemente auf sichtbar, um sicherzustellen, dass sie angezeigt werden
    const allImportantElements = document.querySelectorAll('.container, main, .buzzer-header, .buzzer-actions, .buzzer-section, .room-cards, .no-rooms');
    
    allImportantElements.forEach(function(element) {
        element.style.opacity = '1';
        element.style.visibility = 'visible';
        element.style.display = element.tagName === 'DIV' ? 'block' : '';
    });
    
    // Optional: Sanfte eigene Einblendanimation
    const elementsToAnimate = document.querySelectorAll('.buzzer-header, .buzzer-actions, .buzzer-section, .room-cards, .no-rooms');
    
    // Nur animieren, wenn wir Animationen wünschen - derzeit deaktiviert
    const shouldAnimate = false;
    
    if (shouldAnimate) {
        elementsToAnimate.forEach(function(element, index) {
            // Element initial verstecken
            element.style.opacity = '0';
            element.style.transition = 'opacity 0.5s ease';
            
            // Nach einer kurzen Verzögerung einblenden (gestaffelt nach Index)
            setTimeout(function() {
                element.style.opacity = '1';
            }, 100 + (index * 100));
        });
    }
    
    // GSAP-Animationen deaktivieren, falls vorhanden
    if (typeof gsap !== 'undefined') {
        console.log("GSAP gefunden und für Buzzer-Seite deaktiviert");
        // Versuche, bestehende GSAP-Animationen zu stoppen
        gsap.killAll();
    }
});