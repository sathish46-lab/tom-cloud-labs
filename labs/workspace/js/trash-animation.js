;(function() {
    'use strict';

    // Make sure THREE is available
    if (typeof THREE === 'undefined') {
        console.error('Three.js is not loaded.');
        return;
    }

    var binZone = null;
    var canvasContainer = null;
    
    // Three.js instances
    var scene, camera, renderer;
    var binGroup, bodyGroup, lidGroup, armL, armR, armLWave, armLThumb, legL, legR, eyeL, eyeR, pupilL, pupilR, eyebrowL, eyebrowR, mouthHappy, mouthAngry, mouthOpen;
    var animationId;
    
    // Animation state
    var state = 'hidden'; // hidden, walking_left, waving, walking_right, idle, angry, eating
    var animTime = 0;
    var idleTimer = 0;
    var startX = 15;
    var targetX = 0;
    
    var impatientTimerId = null;
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

    function initThreeJS() {
        if (scene) return; // Already initialized
        
        scene = new THREE.Scene();
        
        // Orthographic camera for isometric-ish 2.5D feel, or Perspective
        var aspect = 200 / 250; // Size of the container
        camera = new THREE.PerspectiveCamera(45, aspect, 0.1, 100);
        camera.position.set(0, 5, 25);
        
        renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
        renderer.setSize(200, 250);
        renderer.setPixelRatio(window.devicePixelRatio);
        
        // Lights
        var ambientLight = new THREE.AmbientLight(0xffffff, 0.6);
        scene.add(ambientLight);
        var dirLight = new THREE.DirectionalLight(0xffffff, 0.8);
        dirLight.position.set(5, 10, 7);
        scene.add(dirLight);

        buildBinModel();
    }

    function buildBinModel() {
        binGroup = new THREE.Group();
        scene.add(binGroup);

        var greenMat = new THREE.MeshLambertMaterial({ color: 0x1e6b3a });
        var greyMat = new THREE.MeshLambertMaterial({ color: 0xa0aec0 });
        var whiteMat = new THREE.MeshLambertMaterial({ color: 0xffffff });
        var darkMat = new THREE.MeshLambertMaterial({ color: 0x2d3748 });
        var yellowMat = new THREE.MeshLambertMaterial({ color: 0xf0c040 });
        var redMat = new THREE.MeshLambertMaterial({ color: 0xe05555 });

        // Body (Bulging Cylinder)
        bodyGroup = new THREE.Group();
        bodyGroup.position.y = 5;
        binGroup.add(bodyGroup);

        var bodyGeo = new THREE.CylinderGeometry(3.5, 2.5, 7, 16);
        var bodyMesh = new THREE.Mesh(bodyGeo, greenMat);
        bodyGroup.add(bodyMesh);

        // Rim
        var rimGeo = new THREE.CylinderGeometry(3.7, 3.7, 0.5, 16);
        var rimMesh = new THREE.Mesh(rimGeo, greenMat);
        rimMesh.position.y = 3.5;
        bodyGroup.add(rimMesh);

        // Lid
        lidGroup = new THREE.Group();
        lidGroup.position.set(0, 3.75, -3.5); // Hinge at the back
        bodyGroup.add(lidGroup);
        
        var lidGeo = new THREE.CylinderGeometry(3.8, 3.8, 0.5, 16);
        var lidMesh = new THREE.Mesh(lidGeo, greenMat);
        lidMesh.position.set(0, 0, 3.5); // Offset from hinge
        lidGroup.add(lidMesh);
        
        var handleGeo = new THREE.BoxGeometry(2, 0.5, 1);
        var handleMesh = new THREE.Mesh(handleGeo, greenMat);
        handleMesh.position.set(0, 0.5, 3.5);
        lidGroup.add(handleMesh);

        // Face container
        var faceGroup = new THREE.Group();
        faceGroup.position.set(0, 1, 3.2); // Front of the body
        bodyGroup.add(faceGroup);

        // Eyes
        var eyeGeo = new THREE.SphereGeometry(0.8, 16, 16);
        eyeL = new THREE.Mesh(eyeGeo, whiteMat);
        eyeL.position.set(-1.2, 0, 0);
        eyeL.scale.z = 0.5; // Flatten slightly
        faceGroup.add(eyeL);

        eyeR = new THREE.Mesh(eyeGeo, whiteMat);
        eyeR.position.set(1.2, 0, 0);
        eyeR.scale.z = 0.5;
        faceGroup.add(eyeR);

        // Pupils - moved further out so they don't clip into the scaled eye!
        var pupilGeo = new THREE.SphereGeometry(0.25, 16, 16);
        pupilL = new THREE.Mesh(pupilGeo, darkMat);
        pupilL.position.set(0, 0, 0.9); 
        // Reverse the Z scale so the pupil stays perfectly round despite the eye's Z scale
        pupilL.scale.z = 2.0; 
        eyeL.add(pupilL);

        pupilR = new THREE.Mesh(pupilGeo, darkMat);
        pupilR.position.set(0, 0, 0.9);
        pupilR.scale.z = 2.0;
        eyeR.add(pupilR);

        // Eyebrows
        var eyebrowGeo = new THREE.BoxGeometry(1.5, 0.3, 0.2);
        eyebrowL = new THREE.Mesh(eyebrowGeo, darkMat);
        eyebrowL.position.set(-1.2, 1, 0.3);
        faceGroup.add(eyebrowL);

        eyebrowR = new THREE.Mesh(eyebrowGeo, darkMat);
        eyebrowR.position.set(1.2, 1, 0.3);
        faceGroup.add(eyebrowR);

        // Mouth (Happy) - Use a Torus for a curved 3D smile
        var mouthHappyGeo = new THREE.TorusGeometry(0.7, 0.15, 8, 16, Math.PI);
        mouthHappy = new THREE.Mesh(mouthHappyGeo, darkMat);
        mouthHappy.rotation.z = Math.PI; // Flip upside down for a smile
        mouthHappy.position.set(0, -0.8, 0); // Flush with faceGroup
        faceGroup.add(mouthHappy);

        // Mouth (Angry)
        var mouthAngryGeo = new THREE.BoxGeometry(1.5, 0.3, 0.2);
        mouthAngry = new THREE.Mesh(mouthAngryGeo, darkMat);
        mouthAngry.position.set(0, -1, 0.2);
        mouthAngry.visible = false;
        faceGroup.add(mouthAngry);

        // Arms using TubeGeometry for curved look
        var curveL = new THREE.QuadraticBezierCurve3(
            new THREE.Vector3(1.0, 0, 0), // Extend inward to body
            new THREE.Vector3(-1.0, -1.5, 0),
            new THREE.Vector3(-1.5, -3.5, 0) // Moved outwards slightly
        );
        var armGeoL = new THREE.TubeGeometry(curveL, 10, 0.3, 8, false);
        
        var curveR = new THREE.QuadraticBezierCurve3(
            new THREE.Vector3(-1.0, 0, 0), // Extend inward to body
            new THREE.Vector3(1.0, -1.5, 0),
            new THREE.Vector3(1.5, -3.5, 0) // Moved outwards slightly
        );
        var armGeoR = new THREE.TubeGeometry(curveR, 10, 0.3, 8, false);

        var gloveGeo = new THREE.SphereGeometry(0.7, 16, 16);
        
        armL = new THREE.Group();
        armL.position.set(-3.5, 1.5, 0); // Reverted anchor to avoid hand clipping
        bodyGroup.add(armL);
        var armLMesh = new THREE.Mesh(armGeoL, greenMat);
        armL.add(armLMesh);
        var gloveL = new THREE.Mesh(gloveGeo, yellowMat);
        gloveL.position.set(-1.5, -3.5, 0); // Aligned with new curve end
        armL.add(gloveL);

        armR = new THREE.Group();
        armR.position.set(3.5, 1.5, 0);
        bodyGroup.add(armR);
        var armRMesh = new THREE.Mesh(armGeoR, greenMat);
        armR.add(armRMesh);
        var gloveR = new THREE.Mesh(gloveGeo, yellowMat);
        gloveR.position.set(1.5, -3.5, 0);
        armR.add(gloveR);

        // Special Wave Arm (shown only during waving, has fingers)
        var curveLWave = new THREE.QuadraticBezierCurve3(
            new THREE.Vector3(1.0, 0, 0),
            new THREE.Vector3(-1.0, 1, 0),
            new THREE.Vector3(-1.5, 2, 0)
        );
        var armGeoLWave = new THREE.TubeGeometry(curveLWave, 10, 0.3, 8, false);
        armLWave = new THREE.Group();
        armLWave.position.set(-3.5, 1.5, 0); // Match normal arm position
        bodyGroup.add(armLWave);
        
        var armLWaveMesh = new THREE.Mesh(armGeoLWave, greenMat);
        armLWave.add(armLWaveMesh);
        
        var gloveWave = new THREE.Mesh(gloveGeo, yellowMat);
        gloveWave.position.set(-1.5, 2, 0);
        armLWave.add(gloveWave);
        
        // Add 5 realistic fingers to the wave glove
        var fingerGeo = new THREE.CylinderGeometry(0.15, 0.15, 1, 8);
        
        // Thumb (Short, pointing right)
        var thumb = new THREE.Mesh(fingerGeo, yellowMat);
        thumb.scale.y = 0.7;
        thumb.position.set(-0.7, 2.2, 0);
        thumb.rotation.z = -1.0;
        armLWave.add(thumb);

        // Index (Normal, pointing slightly right)
        var indexF = new THREE.Mesh(fingerGeo, yellowMat);
        indexF.scale.y = 1.0;
        indexF.position.set(-1.0, 2.7, 0);
        indexF.rotation.z = -0.3;
        armLWave.add(indexF);

        // Middle (Longest, pointing straight up)
        var middleF = new THREE.Mesh(fingerGeo, yellowMat);
        middleF.scale.y = 1.2;
        middleF.position.set(-1.5, 2.9, 0);
        middleF.rotation.z = 0;
        armLWave.add(middleF);

        // Ring (Normal, pointing slightly left)
        var ringF = new THREE.Mesh(fingerGeo, yellowMat);
        ringF.scale.y = 1.0;
        ringF.position.set(-2.0, 2.7, 0);
        ringF.rotation.z = 0.3;
        armLWave.add(ringF);

        // Pinky (Shortest, pointing left)
        var pinky = new THREE.Mesh(fingerGeo, yellowMat);
        pinky.scale.y = 0.75;
        pinky.position.set(-2.3, 2.3, 0);
        pinky.rotation.z = 0.8;
        armLWave.add(pinky);
        
        armLWave.visible = false;

        // Special Thumbs Up Arm (shown only during eating)
        armLThumb = new THREE.Group();
        armLThumb.position.set(-3.5, 1.5, 0); // Match normal arm position
        bodyGroup.add(armLThumb);
        
        var armLThumbMesh = new THREE.Mesh(armGeoL, greenMat);
        armLThumb.add(armLThumbMesh);
        
        var gloveThumb = new THREE.Mesh(gloveGeo, yellowMat);
        gloveThumb.position.set(-1.5, -3.5, 0);
        armLThumb.add(gloveThumb);
        
        // Thumb (pointing local +Z -> World UP)
        var thumbUpGeo = new THREE.CylinderGeometry(0.15, 0.15, 0.8, 8);
        var thumbUp = new THREE.Mesh(thumbUpGeo, yellowMat);
        thumbUp.position.set(-1.5, -3.5, 0.6); // Top of the fist
        thumbUp.rotation.x = Math.PI / 2; // align cylinder with Z
        armLThumb.add(thumbUp);
        
        // 4 Curled fingers (aligned along Y -> World FORWARD)
        var curlGeo = new THREE.CylinderGeometry(0.12, 0.12, 0.7, 8);
        for(var i=0; i<4; i++) {
            var curl = new THREE.Mesh(curlGeo, yellowMat);
            // Stacked along Z (World UP), positioned slightly inward (X)
            curl.position.set(-1.1, -3.5, 0.3 - (i * 0.2)); 
            armLThumb.add(curl);
        }
        armLThumb.visible = false;

        // Legs and proper Shoes
        var legGeo = new THREE.CylinderGeometry(0.4, 0.3, 3, 8);
        
        function createShoe() {
            var group = new THREE.Group();
            
            // Main body of shoe (heel)
            var baseGeo = new THREE.BoxGeometry(1.2, 0.6, 1.4);
            var baseMesh = new THREE.Mesh(baseGeo, darkMat);
            baseMesh.position.set(0, 0.1, -0.2);
            group.add(baseMesh);
            
            // Toe of shoe (rounded)
            var toeGeo = new THREE.CylinderGeometry(0.6, 0.6, 1.2, 16);
            var toeMesh = new THREE.Mesh(toeGeo, darkMat);
            toeMesh.rotation.z = Math.PI / 2;
            toeMesh.position.set(0, 0.1, 0.5);
            group.add(toeMesh);
            
            // White sole
            var soleGeo = new THREE.BoxGeometry(1.3, 0.2, 2.2);
            var soleMesh = new THREE.Mesh(soleGeo, whiteMat);
            soleMesh.position.set(0, -0.3, 0.1);
            group.add(soleMesh);
            
            return group;
        }
        
        legL = new THREE.Group();
        legL.position.set(-1.5, 1.5, 0);
        binGroup.add(legL);
        var legLMesh = new THREE.Mesh(legGeo, greenMat);
        legLMesh.position.y = -1.5;
        legL.add(legLMesh);
        var shoeL = createShoe();
        shoeL.position.set(0, -3.2, 0.5);
        legL.add(shoeL);

        legR = new THREE.Group();
        legR.position.set(1.5, 1.5, 0);
        binGroup.add(legR);
        var legRMesh = new THREE.Mesh(legGeo, greenMat);
        legRMesh.position.y = -1.5;
        legR.add(legRMesh);
        var shoeR = createShoe();
        shoeR.position.set(0, -3.2, 0.5);
        legR.add(shoeR);
        
        // Initial setup
        binGroup.position.x = 0;
        camera.lookAt(new THREE.Vector3(0, 5, 0));
    }

    function renderLoop() {
        if (!binZone) return;
        animationId = requestAnimationFrame(renderLoop);
        
        var dt = 0.016; // Approx 60fps
        animTime += dt;
        
        // Base hover/bounce
        if (state !== 'hidden') {
            bodyGroup.position.y = 5 + Math.sin(animTime * 2) * 0.1;
        }

        if (state === 'walking_left') {
            // Reset arm visibility
            armL.visible = true;
            armLThumb.visible = false;
            armLWave.visible = false;
            armLThumb.rotation.x = 0;
            
            // Give body a 3D rotation and bounce while walking
            binGroup.rotation.y = THREE.MathUtils.lerp(binGroup.rotation.y, -0.4, 0.1);
            binGroup.rotation.z = Math.sin(animTime * 8) * 0.05;
            
            // Walk cycle left (4 steps) - moving the DOM element so it visibly walks across the screen
            var walkDistance = animTime * 60; // speed in pixels
            binZone.style.transform = 'translateY(0) scale(1) translateX(-' + walkDistance + 'px)';
            
            // Swing arms and legs
            var walkCycle = animTime * 8;
            legL.rotation.x = Math.sin(walkCycle) * 0.6;
            legR.rotation.x = -Math.sin(walkCycle) * 0.6;
            armL.rotation.x = -Math.sin(walkCycle) * 0.4;
            armR.rotation.x = Math.sin(walkCycle) * 0.4;
            armL.rotation.z = THREE.MathUtils.lerp(armL.rotation.z, 0, 0.1);
            armR.rotation.z = THREE.MathUtils.lerp(armR.rotation.z, 0, 0.1);
            
            // Lid slowly opens and closes while walking
            lidGroup.rotation.x = -(Math.sin(animTime * 4) + 1) * 0.15;
            
            if (walkDistance >= 120) { // Approx 4 steps
                state = 'waving';
                animTime = 0;
            }
        } else if (state === 'waving') {
            // Stop walking, face front
            binGroup.rotation.y = THREE.MathUtils.lerp(binGroup.rotation.y, 0, 0.1);
            binGroup.rotation.z = THREE.MathUtils.lerp(binGroup.rotation.z, 0, 0.1);
            legL.rotation.x = THREE.MathUtils.lerp(legL.rotation.x, 0, 0.1);
            legR.rotation.x = THREE.MathUtils.lerp(legR.rotation.x, 0, 0.1);
            armL.rotation.z = THREE.MathUtils.lerp(armL.rotation.z, 0, 0.1);
            armR.rotation.z = THREE.MathUtils.lerp(armR.rotation.z, 0, 0.1);
            armR.rotation.x = THREE.MathUtils.lerp(armR.rotation.x, 0, 0.1);
            lidGroup.rotation.x = THREE.MathUtils.lerp(lidGroup.rotation.x, 0, 0.1);
            
            // Swap to waving arm
            armL.visible = false;
            armLThumb.visible = false;
            armLWave.visible = true;
            
            // Wave the special arm
            armLWave.rotation.z = Math.sin(animTime * 15) * 0.2;
            
            if (animTime > 2) {
                state = 'walking_right';
                armL.visible = true;
                armLWave.visible = false;
                armLThumb.visible = false;
                armLWave.rotation.z = 0;
                animTime = 0;
                
                var bubble = document.getElementById('binSpeechBubble');
                if (bubble) bubble.textContent = '';
            }
        } else if (state === 'walking_right') {
            // Reset arm visibility
            armL.visible = true;
            armLThumb.visible = false;
            armLWave.visible = false;
            armLThumb.rotation.x = 0;
            
            // Body faces right while walking
            binGroup.rotation.y = THREE.MathUtils.lerp(binGroup.rotation.y, 0.4, 0.1);
            binGroup.rotation.z = Math.sin(animTime * 8) * 0.05;
            
            // Lid slowly opens and closes while walking
            lidGroup.rotation.x = -(Math.sin(animTime * 4) + 1) * 0.15;
            
            var walkDistance = 120 - (animTime * 60);
            binZone.style.transform = 'translateY(0) scale(1) translateX(-' + Math.max(0, walkDistance) + 'px)';
            
            var walkCycle = animTime * 8;
            legL.rotation.x = Math.sin(walkCycle) * 0.6;
            legR.rotation.x = -Math.sin(walkCycle) * 0.6;
            armL.rotation.x = -Math.sin(walkCycle) * 0.4;
            armR.rotation.x = Math.sin(walkCycle) * 0.4;
            armL.rotation.z = THREE.MathUtils.lerp(armL.rotation.z, 0, 0.1);
            armR.rotation.z = THREE.MathUtils.lerp(armR.rotation.z, 0, 0.1);
            
            if (walkDistance <= 0) {
                state = 'idle';
                binZone.style.transform = 'translateY(0) scale(1) translateX(0px)';
                binGroup.rotation.y = 0;
                binGroup.rotation.z = 0;
                animTime = 0;
                idleTimer = 0;
                setFaceHappy();
            }
        } else if (state === 'idle') {
            legL.rotation.x = THREE.MathUtils.lerp(legL.rotation.x, 0, 0.1);
            legR.rotation.x = THREE.MathUtils.lerp(legR.rotation.x, 0, 0.1);
            armL.rotation.x = THREE.MathUtils.lerp(armL.rotation.x, 0, 0.1);
            armR.rotation.x = THREE.MathUtils.lerp(armR.rotation.x, 0, 0.1);
            armL.rotation.z = THREE.MathUtils.lerp(armL.rotation.z, 0, 0.1);
            armR.rotation.z = THREE.MathUtils.lerp(armR.rotation.z, 0, 0.1);
            lidGroup.rotation.x = THREE.MathUtils.lerp(lidGroup.rotation.x, 0, 0.1);
            
            // Reset visibility
            armL.visible = true;
            armLWave.visible = false;
            armLThumb.visible = false;
            armLThumb.rotation.x = 0;
            
            idleTimer += dt;
            
            // Random blinking
            if (Math.random() < 0.01) {
                eyeL.scale.y = 0.1;
                eyeR.scale.y = 0.1;
                setTimeout(function() {
                    eyeL.scale.y = 1;
                    eyeR.scale.y = 1;
                }, 100);
            }
            
            if (idleTimer > 7) {
                state = 'angry';
                setFaceAngry();
                showQuote();
            }
        } else if (state === 'angry') {
            // Angry trembling - shake the DOM element slightly
            binZone.style.transform = 'translateY(0) scale(1) translateX(' + (Math.sin(animTime * 50) * 2) + 'px)';
            armL.rotation.z = 0.3; // Hands on hips posture (reduced to prevent clipping)
            armR.rotation.z = -0.3;
            
            // Lid flaps while angry (slower and less extreme)
            lidGroup.rotation.x = -(Math.sin(animTime * 15) + 1) * 0.15;
            
            // Quote rotation handled by interval
        } else if (state === 'eating') {
            // Swap to thumbs up arm
            armL.visible = false;
            armLWave.visible = false;
            armLThumb.visible = true;
            
            // Point the arm slightly forward so the thumb points UP
            armLThumb.rotation.x = -Math.PI / 2.5; // -72 degrees

            // Lid opens
            lidGroup.rotation.x = THREE.MathUtils.lerp(lidGroup.rotation.x, -Math.PI / 1.5, 0.1);
            
            if (animTime > 1.5) { // Swallow
                lidGroup.rotation.x = THREE.MathUtils.lerp(lidGroup.rotation.x, 0, 0.3);
                armLThumb.rotation.x = THREE.MathUtils.lerp(armLThumb.rotation.x, 0, 0.2);
            }
            
            // Keep right arm down
            armR.rotation.z = THREE.MathUtils.lerp(armR.rotation.z, 0, 0.15);
        }

        renderer.render(scene, camera);
    }

    function setFaceHappy() {
        eyebrowL.rotation.z = 0.2;
        eyebrowL.position.y = 1.2;
        eyebrowR.rotation.z = -0.2;
        eyebrowR.position.y = 1.2;
        mouthHappy.visible = true;
        mouthAngry.visible = false;
        pupilL.position.set(0, 0, 0.9);
        pupilR.position.set(0, 0, 0.9);
    }

    function setFaceAngry() {
        eyebrowL.rotation.z = -0.4;
        eyebrowL.position.y = 0.9;
        eyebrowR.rotation.z = 0.4;
        eyebrowR.position.y = 0.9;
        mouthHappy.visible = false;
        mouthAngry.visible = true;
        
        // Pupils stare intensely
        pupilL.position.set(0.3, 0, 0.9);
        pupilR.position.set(-0.3, 0, 0.9);
    }

    function getOrCreateBin() {
        if (binZone && document.body.contains(binZone)) return binZone;

        binZone = document.createElement('div');
        binZone.className = 'trash-bin-zone';
        
        var speechBubble = document.createElement('div');
        speechBubble.className = 'bin-speech-bubble';
        speechBubble.id = 'binSpeechBubble';
        speechBubble.textContent = '';
        
        canvasContainer = document.createElement('div');
        canvasContainer.className = 'bin-canvas-container';

        binZone.appendChild(speechBubble);
        binZone.appendChild(canvasContainer);
        document.body.appendChild(binZone);
        
        initThreeJS();
        canvasContainer.appendChild(renderer.domElement);
        
        renderLoop();
        return binZone;
    }

    function showQuote() {
        var bubble = document.getElementById('binSpeechBubble');
        if (bubble) {
            bubble.textContent = impatientQuotes[quoteIndex % impatientQuotes.length];
            quoteIndex++;
        }
    }

    function show() {
        var zone = getOrCreateBin();
        zone.classList.remove('done');
        zone.classList.add('visible');
        
        // Reset state for new sequence
        state = 'walking_left';
        animTime = 0;
        binZone.style.transform = 'translateY(0) scale(1) translateX(0px)';
        setFaceHappy();
        lidGroup.rotation.x = 0;
        
        var bubble = document.getElementById('binSpeechBubble');
        if (bubble) bubble.textContent = 'Hi there! 👋';
    }

    function hide() {
        var zone = getOrCreateBin();
        setTimeout(function() {
            zone.classList.remove('visible');
            zone.classList.add('done');
            setTimeout(function() {
                zone.classList.remove('done');
                state = 'hidden';
            }, 700);
        }, 200);
    }

    function animateDelete(cardElement, successMessage) {
        if (!cardElement) return;

        var zone = getOrCreateBin();
        var bubble = document.getElementById('binSpeechBubble');
        if (bubble) bubble.textContent = '';

        state = 'eating';
        animTime = 0;
        setFaceHappy();
        
        // Reset translation if it was trembling
        zone.style.transform = 'translateY(0) scale(1) translateX(0px)';

        var cardRect = cardElement.getBoundingClientRect();
        var ghost = document.createElement('div');
        ghost.className = 'trash-card-ghost d-flex align-items-center justify-content-center text-danger';
        ghost.innerHTML = '<i class="bx bxs-file" style="font-size: 3rem; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));"></i>';
        ghost.style.position = 'fixed';
        ghost.style.zIndex   = '9999';
        ghost.style.width    = '50px';
        ghost.style.height   = '50px';
        // Start exactly at the center of the card
        ghost.style.left     = (cardRect.left + cardRect.width / 2 - 25) + 'px';
        ghost.style.top      = (cardRect.top + cardRect.height / 2 - 25) + 'px';
        ghost.style.opacity  = '0';
        ghost.style.transform = 'scale(0.5)';
        document.body.appendChild(ghost);

        // Fade out the original card quickly
        cardElement.style.transition = 'all 0.3s ease-out';
        cardElement.style.opacity    = '0';
        cardElement.style.transform  = 'scale(0.8)';
        setTimeout(function() { cardElement.style.visibility = 'hidden'; }, 300);

        var canvasRect = renderer.domElement.getBoundingClientRect();
        
        var startX = cardRect.left + cardRect.width / 2 - 25;
        var startY = cardRect.top + cardRect.height / 2 - 25;
        // Exact center of the bin's open mouth (canvas is 250px tall)
        var targetX = canvasRect.left + (canvasRect.width / 2) - 25;
        var targetY = canvasRect.top + 80;
        
        var dx = targetX - startX;
        var dy = targetY - startY;
        
        // Calculate the apex for a perfect arc (toss)
        var apexY = Math.min(0, dy) - 150; 
        
        var animName = 'toss_' + Date.now();
        var style = document.createElement('style');
        style.innerHTML = `
        @keyframes ${animName} {
            0% { transform: translate(0px, 0px) scale(0.5) rotate(0deg); opacity: 1; }
            50% { transform: translate(${dx * 0.5}px, ${apexY}px) scale(1.2) rotate(180deg); opacity: 1; }
            100% { transform: translate(${dx}px, ${dy}px) scale(0) rotate(540deg); opacity: 0; }
        }`;
        document.head.appendChild(style);

        setTimeout(function() {
            ghost.style.opacity = '1';
            ghost.style.animation = `${animName} 0.7s cubic-bezier(0.25, 0.1, 0.25, 1) forwards`;
        }, 50);

        setTimeout(function() {
            ghost.remove();
            style.remove();
            cardElement.remove();
            
            // Create some particle DOM elements or Three.js particles
            spawnParticles(targetX + 25, targetY + 25, 14);

            
        }, 850);

        setTimeout(function() {
            hide();
            if (window.TomNotify && successMessage) {
                window.TomNotify.show(successMessage, "Success", "success", 3000);
            }
        }, 1500);
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

    window.TrashBin = {
        show: show,
        hide: hide,
        animateDelete: animateDelete
    };

})();