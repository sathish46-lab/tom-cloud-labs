// ============================================================
// Trash Bin Character — Animated Mascot Delete Experience
//
// API:
//   TrashBin.show()                          → Green bin appears, waves hi, lid open
//   TrashBin.hide()                          → Bin leaves
//   TrashBin.animateDelete(card, message)    → Card flies to bin, bin turns red, eats it
// ============================================================

(function() {
    'use strict';

    var binZone = null;
    var impatientTimer = null;
    var quoteIndex = 0;

    var impatientQuotes = [
        "Still thinking? My lid is getting tired! 😤",
        "Hey! Delete or don't, make up your mind! 🙄",
        "I'm standing here with my mouth open... 😑",
        "Don't waste my time, I have cards to eat! 😋",
        "Hello?? I'm not getting any younger here! ⏰",
        "My gloves are getting cold, hurry up! 🥶",
        "I didn't put on my best gloves for nothing... 💅",
        "Feed me that card or let me go home! 🏠"
    ];

    function buildCharacterSVG() {
        return '<svg viewBox="-10 0 160 210" xmlns="http://www.w3.org/2000/svg">' +

            // Shadow under feet
            '<ellipse cx="70" cy="205" rx="45" ry="5" fill="rgba(0,0,0,0.25)"/>' +



            // ====== BIN BODY (trapezoid with ridges) ======
            '<path d="M30 58 L110 58 L104 150 L36 150 Z" class="bin-body-main" stroke="#1a5c2e" stroke-width="2" stroke-linejoin="round"/>' +

            // Vertical ridges
            '<line x1="48" y1="62" x2="46" y2="146" class="bin-slat" stroke-width="1.2" opacity="0.5"/>' +
            '<line x1="62" y1="62" x2="61" y2="146" class="bin-slat" stroke-width="1.2" opacity="0.5"/>' +
            '<line x1="78" y1="62" x2="79" y2="146" class="bin-slat" stroke-width="1.2" opacity="0.5"/>' +
            '<line x1="92" y1="62" x2="94" y2="146" class="bin-slat" stroke-width="1.2" opacity="0.5"/>' +

            // Horizontal bands
            '<rect x="32" y="90" width="76" height="3" rx="1" fill="#1a5c2e" opacity="0.25"/>' +
            '<rect x="34" y="122" width="72" height="3" rx="1" fill="#1a5c2e" opacity="0.25"/>' +

            // Rim
            '<rect x="27" y="53" width="86" height="8" rx="3" class="bin-body-rim" stroke="#1a5c2e" stroke-width="1.5"/>' +

            // ====== LEFT ARM (Waving) ======
            '<g class="bin-arm-left-wave">' +
                '<path d="M32 95 Q5 85 0 68" stroke="#1e6b3a" stroke-width="9" fill="none" stroke-linecap="round"/>' +
                '<ellipse cx="-2" cy="62" rx="11" ry="10" fill="#f0c040" stroke="#d4a020" stroke-width="1.2"/>' +
                '<path d="M-10 64 L-18 56 L-16 52" stroke="#f0c040" stroke-width="5" fill="none" stroke-linecap="round"/>' +
                '<circle cx="-16" cy="51" r="3" fill="#f0c040" stroke="#d4a020" stroke-width="0.6"/>' +
                '<path d="M-6 53 L-10 42" stroke="#f0c040" stroke-width="4.5" fill="none" stroke-linecap="round"/>' +
                '<circle cx="-10" cy="41" r="2.8" fill="#f0c040" stroke="#d4a020" stroke-width="0.5"/>' +
                '<path d="M-1 52 L-2 40" stroke="#f0c040" stroke-width="4.5" fill="none" stroke-linecap="round"/>' +
                '<circle cx="-2" cy="39" r="2.8" fill="#f0c040" stroke="#d4a020" stroke-width="0.5"/>' +
                '<path d="M4 53 L6 42" stroke="#f0c040" stroke-width="4.5" fill="none" stroke-linecap="round"/>' +
                '<circle cx="6" cy="41" r="2.8" fill="#f0c040" stroke="#d4a020" stroke-width="0.5"/>' +
                '<path d="M8 56 L14 48" stroke="#f0c040" stroke-width="3.5" fill="none" stroke-linecap="round"/>' +
                '<circle cx="14" cy="47" r="2.3" fill="#f0c040" stroke="#d4a020" stroke-width="0.5"/>' +
                '<rect x="-10" y="70" width="18" height="5" rx="2.5" fill="#e0a820" stroke="#c89018" stroke-width="0.6"/>' +
            '</g>' +

            // ====== LEFT ARM (Angry — flat mitten resting on the stomach, matches reference) ======
            '<g class="bin-arm-left-angry">' +
                '<path d="M32 96 Q14 106 30 122" stroke="#1e6b3a" stroke-width="9" fill="none" stroke-linecap="round"/>' +
                '<ellipse cx="38" cy="126" rx="13" ry="10" fill="#f7fafc" stroke="#cbd5e0" stroke-width="1.4"/>' +
                '<path d="M27 123 Q38 116 51 123" stroke="#cbd5e0" stroke-width="1.4" fill="none"/>' +
                '<path d="M27 129 Q38 134 51 129" stroke="#cbd5e0" stroke-width="1.2" fill="none" opacity="0.6"/>' +
                '<ellipse cx="26" cy="120" rx="4.5" ry="5" fill="#f7fafc" stroke="#cbd5e0" stroke-width="1.2"/>' +
            '</g>' +

            // ====== RIGHT ARM (Resting Down) ======
            '<g class="bin-arm-right-rest">' +
                '<path d="M108 95 Q130 115 125 145" stroke="#1e6b3a" stroke-width="9" fill="none" stroke-linecap="round"/>' +
                '<ellipse cx="123" cy="147" rx="9" ry="10" fill="#f0c040" stroke="#d4a020" stroke-width="1.2"/>' +
                '<path d="M116 148 Q123 154 130 148 M116 143 Q123 149 130 143" stroke="#d4a020" stroke-width="1.5" fill="none"/>' +
                '<rect x="117" y="132" width="16" height="5" rx="2" fill="#e0a820" stroke="#c89018" stroke-width="0.6" transform="rotate(-15 117 132)"/>' +
            '</g>' +

            // ====== RIGHT ARM (Angry — flat mitten resting on the stomach, mirrors left) ======
            '<g class="bin-arm-right-angry">' +
                '<path d="M108 96 Q126 106 110 122" stroke="#1e6b3a" stroke-width="9" fill="none" stroke-linecap="round"/>' +
                '<ellipse cx="102" cy="126" rx="13" ry="10" fill="#f7fafc" stroke="#cbd5e0" stroke-width="1.4"/>' +
                '<path d="M89 123 Q102 116 113 123" stroke="#cbd5e0" stroke-width="1.4" fill="none"/>' +
                '<path d="M89 129 Q102 134 113 129" stroke="#cbd5e0" stroke-width="1.2" fill="none" opacity="0.6"/>' +
                '<ellipse cx="114" cy="120" rx="4.5" ry="5" fill="#f7fafc" stroke="#cbd5e0" stroke-width="1.2"/>' +
            '</g>' +

            '<rect x="34" y="122" width="72" height="3" rx="1" fill="#1a5c2e" opacity="0.25"/>' +

            // Rim
            '<rect x="27" y="53" width="86" height="8" rx="3" class="bin-body-rim" stroke="#1a5c2e" stroke-width="1.5"/>' +

            // ====== FACE ======

            // Eyebrows
            '<path class="bin-eyebrow bin-eyebrow-left" d="M38 72 Q48 66 56 72" fill="none" stroke="#1a5c2e" stroke-width="2.5" stroke-linecap="round"/>' +
            '<path class="bin-eyebrow bin-eyebrow-right" d="M84 72 Q92 66 100 72" fill="none" stroke="#1a5c2e" stroke-width="2.5" stroke-linecap="round"/>' +

            // Left Eye
            '<ellipse cx="48" cy="84" rx="12" ry="11" fill="white" stroke="#1a5c2e" stroke-width="1.5"/>' +
            '<g class="bin-eye-pupil">' +
                '<circle cx="50" cy="85" r="6" fill="#2d3748"/>' +
                '<circle cx="52" cy="83" r="2.2" fill="white"/>' +
            '</g>' +

            // Right Eye
            '<ellipse cx="92" cy="84" rx="12" ry="11" fill="white" stroke="#1a5c2e" stroke-width="1.5"/>' +
            '<g class="bin-eye-pupil">' +
                '<circle cx="94" cy="85" r="6" fill="#2d3748"/>' +
                '<circle cx="96" cy="83" r="2.2" fill="white"/>' +
            '</g>' +

            // Mouth: Happy (open grin with visible top teeth + tongue)
            '<g class="bin-mouth-happy">' +
                '<path d="M55 105 Q70 122 85 105 Q70 116 55 105 Z" fill="#7a2020"/>' +
                '<path d="M55 105 Q70 120 85 105" fill="none" stroke="#145228" stroke-width="1.2"/>' +
                '<rect x="61" y="105" width="8" height="6" rx="1.5" fill="white"/>' +
                '<rect x="70" y="105" width="8" height="6" rx="1.5" fill="white"/>' +
                '<ellipse cx="70" cy="115" rx="6" ry="4" fill="#e05555" opacity="0.85"/>' +
            '</g>' +

            // Mouth: Angry (gritted teeth)
            '<g class="bin-mouth-angry">' +
                '<path d="M54 107 Q70 103 86 107 L86 114 Q70 118 54 114 Z" fill="#1a3a28" stroke="#0f2e1c" stroke-width="1.2"/>' +
                '<rect x="58" y="106.5" width="8" height="6" rx="1" fill="white"/>' +
                '<rect x="66.5" y="106.5" width="7" height="6" rx="1" fill="white"/>' +
                '<rect x="74.5" y="106.5" width="8" height="6" rx="1" fill="white"/>' +
            '</g>' +

            // Mouth: Open (eating)
            '<g class="bin-mouth-open">' +
                '<ellipse cx="70" cy="108" rx="14" ry="11" fill="#1a3a28" stroke="#0f2e1c" stroke-width="1.5"/>' +
                '<ellipse cx="70" cy="114" rx="7" ry="4" fill="#e05555" opacity="0.6"/>' +
            '</g>' +

            // ====== LID ======
            '<g class="bin-lid-group">' +
                '<rect x="24" y="43" width="92" height="12" rx="5" class="bin-lid-main" stroke="#1a5c2e" stroke-width="1.5"/>' +
                '<rect x="58" y="35" width="24" height="10" rx="5" class="bin-lid-handle" stroke="#1a5c2e" stroke-width="1.2"/>' +
            '</g>' +

        '</svg>';
    }

    function getOrCreateBin() {
        if (binZone && document.body.contains(binZone)) return binZone;

        binZone = document.createElement('div');
        binZone.className = 'trash-bin-zone';

        var charDiv = document.createElement('div');
        charDiv.className = 'bin-character';
        charDiv.innerHTML = buildCharacterSVG();

        var speechBubble = document.createElement('div');
        speechBubble.className = 'bin-speech-bubble';
        speechBubble.id = 'binSpeechBubble';
        speechBubble.textContent = '';

        binZone.appendChild(speechBubble);
        binZone.appendChild(charDiv);

        document.body.appendChild(binZone);
        return binZone;
    }

    function spawnParticles(x, y, count) {
        var colors = ['#22c55e', '#ef4444', '#f0c040', '#eab308', '#a855f7', '#3b82f6'];
        for (var i = 0; i < count; i++) {
            var p = document.createElement('div');
            p.className = 'trash-particle';
            var angle = (Math.PI * 2 * i) / count;
            var dist = 30 + Math.random() * 50;
            p.style.cssText =
                'left:' + x + 'px;' +
                'top:' + y + 'px;' +
                'background:' + colors[i % colors.length] + ';' +
                '--px:' + Math.cos(angle) * dist + 'px;' +
                '--py:' + Math.sin(angle) * dist + 'px;' +
                'width:' + (4 + Math.random() * 5) + 'px;' +
                'height:' + (4 + Math.random() * 5) + 'px;';
            document.body.appendChild(p);
            setTimeout(function(el) { el.remove(); }, 650, p);
        }
    }

    function startImpatientTimer() {
        clearImpatientTimer();
        impatientTimer = setTimeout(function() {
            if (!binZone) return;
            binZone.classList.add('impatient');
            showQuote();
            impatientTimer = setInterval(function() {
                showQuote();
            }, 5000);
        }, 7000);
    }

    function showQuote() {
        var bubble = document.getElementById('binSpeechBubble');
        if (bubble) {
            bubble.textContent = impatientQuotes[quoteIndex % impatientQuotes.length];
            quoteIndex++;
        }
    }

    function clearImpatientTimer() {
        if (impatientTimer) {
            clearTimeout(impatientTimer);
            clearInterval(impatientTimer);
            impatientTimer = null;
        }
    }

    function show() {
        var zone = getOrCreateBin();
        zone.classList.remove('done', 'swallow', 'eating', 'impatient');
        zone.className = 'trash-bin-zone';
        void zone.offsetWidth;
        zone.classList.add('visible', 'lid-open');
        quoteIndex = 0;
        startImpatientTimer();
    }

    function hide() {
        clearImpatientTimer();
        var zone = getOrCreateBin();
        zone.classList.remove('impatient', 'lid-open', 'eating');
        setTimeout(function() {
            zone.classList.remove('visible');
            zone.classList.add('done');
            setTimeout(function() {
                zone.classList.remove('done');
            }, 700);
        }, 200);
    }

    function animateDelete(cardElement, successMessage) {
        if (!cardElement) return;

        clearImpatientTimer();
        var zone = getOrCreateBin();
        var bubble = document.getElementById('binSpeechBubble');
        if (bubble) bubble.textContent = '';

        zone.classList.remove('impatient');
        zone.classList.add('eating', 'lid-open');

        var cardRect = cardElement.getBoundingClientRect();

        var ghost = document.createElement('div');
        ghost.className = 'trash-card-ghost';
        ghost.style.width    = cardRect.width + 'px';
        ghost.style.height   = cardRect.height + 'px';
        ghost.style.left     = cardRect.left + 'px';
        ghost.style.top      = cardRect.top + 'px';
        ghost.style.opacity  = '1';

        var clone = cardElement.cloneNode(true);
        clone.style.width  = '100%';
        clone.style.height = '100%';
        clone.style.margin = '0';
        clone.style.position = 'static';
        ghost.appendChild(clone);
        document.body.appendChild(ghost);

        cardElement.style.opacity    = '0';
        cardElement.style.visibility = 'hidden';
        cardElement.style.transition = 'none';

        var binRect = zone.getBoundingClientRect();
        var targetX = binRect.left + binRect.width / 2 - 20;
        var targetY = binRect.top + binRect.height / 2;

        setTimeout(function() {
            ghost.style.transition = 'all 0.7s cubic-bezier(0.55, 0, 0.1, 1)';
            ghost.style.left      = targetX + 'px';
            ghost.style.top       = targetY + 'px';
            ghost.style.width     = '35px';
            ghost.style.height    = '35px';
            ghost.style.opacity   = '0.2';
            ghost.style.transform = 'rotate(-12deg) scale(0.05)';
        }, 100);

        setTimeout(function() {
            zone.classList.remove('lid-open');
            zone.classList.add('swallow');
            ghost.remove();

            var freshBin = zone.getBoundingClientRect();
            spawnParticles(
                freshBin.left + freshBin.width / 2,
                freshBin.top + freshBin.height / 2,
                14
            );
            cardElement.remove();
        }, 850);

        setTimeout(function() {
            zone.classList.remove('swallow', 'eating');
            zone.classList.remove('visible');
            zone.classList.add('done');
            if (window.TomNotify && successMessage) {
                window.TomNotify.show(successMessage, "Success", "success", 3000);
            }
        }, 1500);

        setTimeout(function() {
            zone.classList.remove('done');
        }, 2300);
    }

    window.TrashBin = {
        show: show,
        hide: hide,
        animateDelete: animateDelete
    };

})();