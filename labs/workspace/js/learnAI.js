/**
 * Wrapped with IIFE Error Boundary
 */
try {
  (function() {
    "use strict";


/**
 * Learn AI Application UI Logic
 */
(function () {
    'use strict';

    const LearnApp = {
        isInitialized: false,
        isDragging: false,
        currentResizer: null,
        animationFrame: null,
        initialAnchor: 0,
        initialWidth: 0,

        init: function () {
            if (this.isInitialized) return;
            const appWrapper = document.querySelector('.learn-app-wrapper');
            if (!appWrapper) return;

            this.restorePaneWidths();
            this.adjustVHE(appWrapper);
            this.initResizers();
            this.initAIChat();

            window.addEventListener('resize', () => {
                this.adjustVHE(appWrapper);
            });
            this.isInitialized = true;
        },

        // Save and Restore Pane Widths
        savePaneWidth: function (id, width) {
            localStorage.setItem(`learn-pane-${id}`, width);
            // Also sync to document root for immediate CSS consumption
            document.documentElement.style.setProperty(`--${id}-saved-width`, width + 'px');
        },

        restorePaneWidths: function () {
            const panes = ['outlineSidebar', 'courseSidebar', 'paneAI'];
            panes.forEach(id => {
                const target = document.getElementById(id);
                const savedWidth = localStorage.getItem(`learn-pane-${id}`);
                if (target && savedWidth) {
                    target.style.width = savedWidth + 'px';
                    target.style.flexBasis = savedWidth + 'px';
                    // Update CSS variable as well
                    document.documentElement.style.setProperty(`--${id}-saved-width`, savedWidth + 'px');

                    // Update outline state if applicable
                    if (id === 'outlineSidebar') {
                        const widthNum = parseInt(savedWidth);
                        this.setOutlineState(target, widthNum > 180 ? 'expanded' : 'collapsed');
                    }
                }
            });
        },

        // Draggable Resizers Logic (Hardened with Anchors & Delegation)
        initResizers: function () {
            // Use delegation on document for mousedown to handle dynamic or late-rendered resizers
            document.addEventListener('mousedown', (e) => {
                const resizer = e.target.closest('.pane-resizer');
                if (!resizer) return;

                const targetId = resizer.getAttribute('data-target');
                const target = document.getElementById(targetId);
                if (!target) return;

                this.isDragging = true;
                this.currentResizer = resizer;
                resizer.classList.add('is-dragging');
                document.body.classList.add('resizing-active');
                document.body.style.cursor = 'col-resize';
                document.body.style.userSelect = 'none';

                // Prevent iframes from stealing mouse events
                document.querySelectorAll('iframe').forEach(ifrm => {
                    ifrm.style.pointerEvents = 'none';
                });

                // Disable transitions immediately
                target.style.setProperty('transition', 'none', 'important');

                // Cache initial state for stable calculation
                const rect = target.getBoundingClientRect();
                const direction = resizer.getAttribute('data-direction') || 'left';
                this.initialAnchor = (direction === 'left') ? rect.left : rect.right;
                this.initialWidth = rect.width;
            });

            document.addEventListener('mousemove', (e) => {
                if (!this.isDragging || !this.currentResizer) return;

                // Simple throttling via RAF
                if (this.animationFrame) cancelAnimationFrame(this.animationFrame);

                this.animationFrame = requestAnimationFrame(() => {
                    this.handleResize(e);
                });
            });

            const stopDragging = () => {
                if (this.isDragging) {
                    this.isDragging = false;
                    document.body.classList.remove('resizing-active');
                    document.querySelectorAll('iframe').forEach(ifrm => ifrm.style.pointerEvents = 'all');

                    if (this.currentResizer) {
                        this.currentResizer.classList.remove('is-dragging');
                        const targetId = this.currentResizer.getAttribute('data-target');
                        const target = document.getElementById(targetId);
                        if (target) {
                            target.style.removeProperty('transition');
                            this.savePaneWidth(targetId, target.offsetWidth);
                        }
                    }
                    document.body.style.cursor = 'default';
                    document.body.style.userSelect = 'auto';
                    this.currentResizer = null;
                }
            };

            document.addEventListener('mouseup', stopDragging);
            window.addEventListener('blur', stopDragging);
        },

        handleResize: function (e) {
            if (!this.currentResizer) return;

            const targetId = this.currentResizer.getAttribute('data-target');
            const direction = this.currentResizer.getAttribute('data-direction') || 'left';
            const target = document.getElementById(targetId);

            if (target) {
                let newWidth;

                if (direction === 'left') {
                    // Distance from fixed left edge (anchor) to mouse
                    newWidth = e.clientX - this.initialAnchor;
                } else {
                    // Distance from fixed right edge (anchor) to mouse
                    newWidth = this.initialAnchor - e.clientX;
                }

                // Standardized constraints
                if (targetId === 'outlineSidebar') {
                    newWidth = Math.max(70, Math.min(500, newWidth));
                    const isCollapsed = target.getAttribute('data-state') === 'collapsed';
                    if (newWidth > 180 && isCollapsed) {
                        this.setOutlineState(target, 'expanded');
                    } else if (newWidth <= 180 && !isCollapsed) {
                        this.setOutlineState(target, 'collapsed');
                    }
                } else if (targetId === 'courseSidebar') {
                    newWidth = Math.max(250, Math.min(600, newWidth));
                } else if (targetId === 'paneAI') {
                    newWidth = Math.max(250, Math.min(800, newWidth));
                }

                // Double Apply for flexbox and standard layout
                target.style.width = newWidth + 'px';
                target.style.flexBasis = newWidth + 'px';
                target.style.minWidth = '0px'; // Prevent min-width blocking
                document.documentElement.style.setProperty(`--${targetId}-saved-width`, newWidth + 'px');
            }
        },

        setOutlineState: function (sidebar, state) {
            const compactView = sidebar.querySelector('.outline-compact');
            const fullView = sidebar.querySelector('.outline-full');
            if (!compactView || !fullView) return;

            if (state === 'expanded') {
                sidebar.setAttribute('data-state', 'expanded');
                compactView.classList.add('d-none');
                fullView.classList.remove('d-none');
                fullView.classList.add('d-flex');
            } else {
                sidebar.setAttribute('data-state', 'collapsed');
                fullView.classList.add('d-none');
                fullView.classList.remove('d-flex');
                compactView.classList.remove('d-none');
                compactView.classList.add('d-flex');
            }
        },

        toggleOutline: function () {
            const sidebar = document.getElementById('outlineSidebar');
            if (!sidebar) return;

            const isCollapsed = sidebar.getAttribute('data-state') === 'collapsed';
            const newWidth = isCollapsed ? 300 : 70;

            sidebar.style.width = newWidth + 'px';
            sidebar.style.flexBasis = newWidth + 'px';
            this.setOutlineState(sidebar, isCollapsed ? 'expanded' : 'collapsed');
            this.savePaneWidth('outlineSidebar', newWidth);
        },

        adjustVHE: function (appWrapper) {
            const header = document.querySelector('header.header');
            const footer = document.querySelector('footer.footer');

            if (header && footer) {
                const headerHeight = header.offsetHeight;
                const footerHeight = footer.offsetHeight;
                const headerStyle = window.getComputedStyle(header);
                const headerMB = parseFloat(headerStyle.marginBottom) || 0;

                const availableHeight = window.innerHeight - headerHeight - footerHeight - headerMB;
                document.documentElement.style.setProperty('--app-height', `${availableHeight}px`);

                if (appWrapper.classList.contains('stable-app-view')) {
                    document.body.style.height = '100vh';
                    document.body.style.overflow = 'hidden';
                    const mainWrapper = document.querySelector('.wrapper');
                    if (mainWrapper) {
                        mainWrapper.style.height = '100vh';
                        mainWrapper.style.overflow = 'hidden';
                    }
                } else {
                    document.body.style.height = 'auto';
                    document.body.style.overflow = 'auto';
                    const mainWrapper = document.querySelector('.wrapper');
                    if (mainWrapper) {
                        mainWrapper.style.height = 'auto';
                        mainWrapper.style.overflow = 'visible';
                    }
                }
            }

            let vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        },

        // AI Chat Functionality
        initAIChat: function () {
            const chatInput = document.getElementById('aiChatInput');
            const chatSend = document.getElementById('aiChatSend');
            const chatHistory = document.getElementById('aiChatHistory');
            const chapterId = document.getElementById('currentChapterId')?.value;

            if (!chatInput || !chatSend || !chatHistory) return;

            // Auto-scroll to bottom to show latest server-rendered messages
            setTimeout(() => { chatHistory.scrollTop = chatHistory.scrollHeight; }, 100);

            const userSessionId = 'sess_' + Math.random().toString(36).substr(2, 9);
            const aiSocket = new TomSocketClient();
            let idleTimer = null;

            const handleAIStream = (data) => {
                const aiMsgContainer = document.querySelector('.current-ai-stream');
                if (!aiMsgContainer) return;
                const p = aiMsgContainer.querySelector('p');

                if (data.type === 'stream_end') {
                    // Explicitly remove typing dots if they are still there
                    const dots = aiMsgContainer.querySelector('.typing-dots');
                    if (dots) dots.remove();

                    aiMsgContainer.classList.remove('current-ai-stream');
                    return;
                }

                if (data.type === 'text_delta') {
                    // Remove typing dots if present
                    if (p.querySelector('.typing-dots')) {
                        p.innerHTML = '';
                    }

                    p.innerHTML += data.data;
                    chatHistory.scrollTop = chatHistory.scrollHeight;
                }
            };

            const ensureSocketConnection = () => {
                if (!aiSocket.isActive()) {
                    aiSocket.connect(`ai_stream.${userSessionId}`, handleAIStream);
                }
            };

            const resetIdleTimer = () => {
                ensureSocketConnection();
                clearTimeout(idleTimer);
                idleTimer = setTimeout(() => {
                    console.log('AI Chat idle for 60s... dropping persistent socket to save resources.');
                    aiSocket.disconnect();
                }, 60000); // 60 seconds
            };

            // Connect instantly on load
            resetIdleTimer();

            // Re-establish/refresh connection idleness on typing
            chatInput.addEventListener('input', resetIdleTimer);

            chatSend.addEventListener('click', () => {
                const query = chatInput.value.trim();
                const currentChapterId = document.getElementById('currentChapterId')?.value || '';
                if (!query) return;

                resetIdleTimer(); // ensure socket gets bumped or reconnected on send

                const modelSelect = document.getElementById('aiModelSelect');
                const aiModel = modelSelect ? modelSelect.value : 'gemini';

                const messageId = 'msg_' + Math.random().toString(36).substr(2, 9);
                chatInput.value = '';

                // Add user message
                this.appendChatMessage('User', query, 'user-row ms-auto');

                // Prepare AI message placeholder
                const aiMsgId = 'ai_msg_' + messageId;
                this.appendChatMessage('SathishBot', '<div class="typing-dots"><span></span><span></span><span></span></div>', 'ai-row current-ai-stream', aiMsgId);

                // 2. Trigger AI Generation
                fetch('/src/api/learnAI/ask.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ query, chapter_id: currentChapterId, message_id: messageId, session_id: userSessionId, ai_model: aiModel })
                })
                    .then(res => res.json())
                    .catch(err => {
                        console.error('AI Request Failed:', err);
                        const aiMsgContainer = document.getElementById(aiMsgId);
                        if (aiMsgContainer) aiMsgContainer.querySelector('p').innerText = 'Sorry, something went wrong.';
                    });
            });

            chatInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') chatSend.click();
            });
        },

        appendChatMessage: function (sender, text, type, id = null) {
            const chatHistory = document.getElementById('aiChatHistory');
            if (!chatHistory) return;

            const userAvatar = document.getElementById('userAvatarUrl')?.value || '';
            const userAvatarStyle = document.getElementById('userAvatarStyle')?.value || '';
            const aiAvatar = document.getElementById('aiAvatarUrl')?.value || '/assets/logo/logo.png';

            const msgDiv = document.createElement('div');
            msgDiv.className = `message-row ${type}`;
            if (id) msgDiv.id = id;

            if (type.includes('ai-row')) {
                msgDiv.innerHTML = `
                    <div class="msg-avatar">
                        <img src="${aiAvatar}" alt="AI">
                    </div>
                    <div class="msg-bubble">
                        <p class="m-0">${text}</p>
                    </div>
                `;
            } else {
                msgDiv.innerHTML = `
                    <div class="msg-bubble">
                        <p class="m-0">${text}</p>
                    </div>
                    <div class="msg-avatar shadow-sm border border-secondary border-opacity-25">
                        <img src="${userAvatar}" style="${userAvatarStyle}" alt="User">
                    </div>
                `;
            }

            chatHistory.appendChild(msgDiv);
            chatHistory.scrollTop = chatHistory.scrollHeight;
        }
    };

    // Initialize
    if (document.readyState === 'loading') {
        window.onPageLoad( () => LearnApp.init());
    } else {
        LearnApp.init();
    }

    // Export toggle for button clicks
    window.toggleOutline = () => LearnApp.toggleOutline();

    // Re-check for resizers after a small delay in case of late rendering
    setTimeout(() => { if (!LearnApp.isInitialized) LearnApp.init(); }, 1000);
})();


    

    // --- Explicit Window Exports for Inline HTML ---

  })();
} catch (e) {
  console.error("[Fatal Error in learnAI.js]", e);
}
