/**
 * Wrapped with IIFE Error Boundary - Learn AI
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

        // Token usage tracking (cumulative per session)
        tokenStats: {
            totalInputTokens: 0,
            totalOutputTokens: 0,
            totalCachedTokens: 0,
            totalTokens: 0,
            responseCount: 0,
            history: [] // Per-message token history
        },

        formatTokenCount: function(num) {
            if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
            if (num >= 1000) return (num / 1000).toFixed(0) + 'K';
            return num.toString();
        },

        updateStatsBar: function(usage) {
            if (!usage) return;

            // Accumulate tokens
            if (usage.source !== 'local' && usage.source !== 'lm_studio') {
                this.tokenStats.totalInputTokens += (usage.input_tokens || 0);
                this.tokenStats.totalOutputTokens += (usage.output_tokens || 0);
                this.tokenStats.totalCachedTokens += (usage.cached_tokens || 0);
                this.tokenStats.totalTokens += (usage.total_tokens || 0);
            }
            this.tokenStats.responseCount++;

            // Store per-message history
            this.tokenStats.history.push({
                input: usage.input_tokens || 0,
                output: usage.output_tokens || 0,
                cached: usage.cached_tokens || 0,
                cache_pct: usage.cache_hit_percent || 0,
                source: usage.source || 'unknown',
                tool: usage.tool_name || null,
                timestamp: Date.now()
            });

            // Update DOM elements
            const inputDisplay = document.getElementById('aiTokensDisplay');
            const outputDisplay = document.getElementById('aiOutputTokens');
            const cachedDisplay = document.getElementById('aiCachedTokens');
            const cachedWrap = document.getElementById('aiCachedTokensWrap');
            const cacheBadge = document.getElementById('aiCachePercBadge');
            const progressRing = document.getElementById('aiContextProgressRing');

            if (inputDisplay) {
                inputDisplay.innerText = `${this.formatTokenCount(this.tokenStats.totalInputTokens)}/1M`;
            }
            if (outputDisplay) {
                outputDisplay.innerText = this.formatTokenCount(this.tokenStats.totalOutputTokens);
            }

            // Cache badge & ring
            const totalInput = this.tokenStats.totalInputTokens || 1;
            const pct = Math.round(this.tokenStats.totalCachedTokens / totalInput * 100);

            if (cachedWrap && cachedDisplay) {
                cachedWrap.classList.remove('d-none');
                cachedDisplay.innerText = this.formatTokenCount(this.tokenStats.totalCachedTokens);
                cachedWrap.title = `Cached tokens: ${this.tokenStats.totalCachedTokens.toLocaleString()} (${pct}% cache hit - cost savings!)`;
            }

            if (cacheBadge && progressRing) {
                cacheBadge.innerText = pct + '%';
                // Calculate dash offset for a circle with r=10 (circumference approx 63)
                // 100% = offset 0, 0% = offset 63
                const offset = 63 - (pct / 100) * 63;
                progressRing.style.strokeDashoffset = offset;
            }

            // For LM Studio, show N/A style
            if (usage.source === 'lm_studio') {
                if (inputDisplay) inputDisplay.innerText = `N/A`;
                if (outputDisplay) outputDisplay.innerText = `N/A`;
                if (progressRing) progressRing.style.strokeDashoffset = 63;
                if (cacheBadge) cacheBadge.innerText = '0%';
            }

            // For local tool execution, show the ⚡ indicator
            if (usage.source === 'local' && usage.tool_name) {
                if (inputDisplay) inputDisplay.innerHTML = `<span class="text-warning"><i class="bx bx-bolt-circle me-1"></i>Local Tool</span>`;
            }
        },

        init: function () {
            if (!document.querySelector('.stable-app-view')) {
                document.body.style.removeProperty('overflow');
                document.body.style.removeProperty('height');
                const mainWrapper = document.querySelector('.wrapper');
                if (mainWrapper) {
                    mainWrapper.style.removeProperty('overflow');
                    mainWrapper.style.removeProperty('height');
                }
            }

            const appWrapper = document.querySelector('.learn-app-wrapper');
            if (!appWrapper) return;

            this.restorePaneWidths();
            this.adjustVHE(appWrapper);
            this.initAIChat();
            this.initContentGenerator();
            this.initFloatingTooltips();

            if (!this.isInitialized) {
                this.initResizers();
                window.addEventListener('resize', () => {
                    const currentWrapper = document.querySelector('.learn-app-wrapper');
                    if (currentWrapper) this.adjustVHE(currentWrapper);
                });
                this.isInitialized = true;
            }
        },

        // Top-layer floating tooltip on document.body for narrow Panel 1 icons/numbers
        initFloatingTooltips: function () {
            let floatingEl = document.getElementById('learn-first-layer-tooltip');
            if (!floatingEl) {
                floatingEl = document.createElement('div');
                floatingEl.id = 'learn-first-layer-tooltip';
                floatingEl.style.cssText = 'position: fixed; z-index: 99999999; padding: 6px 12px; background: rgba(15, 17, 23, 0.98); color: #ffffff; font-size: 11px; font-weight: 600; white-space: nowrap; border-radius: 6px; box-shadow: 0 4px 16px rgba(0, 0, 0, 0.75); border: 1px solid rgba(255, 255, 255, 0.15); pointer-events: none; opacity: 0; visibility: hidden; transition: opacity 0.15s ease, transform 0.15s ease;';
                document.body.appendChild(floatingEl);
            }

            const hideTooltip = () => {
                floatingEl.style.opacity = '0';
                floatingEl.style.visibility = 'hidden';
            };

            document.addEventListener('mouseover', (e) => {
                const target = e.target.closest('#learn-panel-1 [data-tooltip], #learn-panel-1 [title]');
                if (!target) return;

                // Remove native browser title to prevent default ugly tooltips
                if (target.hasAttribute('title')) {
                    target.setAttribute('data-tooltip', target.getAttribute('title'));
                    target.removeAttribute('title');
                }
                target.removeAttribute('data-coreui-original-title');

                const panel1 = document.getElementById('learn-panel-1');
                if (!panel1) return;

                // Only show custom floating tooltip if Panel 1 is in narrow / compact mode (<= 175px or collapsed)
                const isNarrow = panel1.offsetWidth <= 175 || panel1.classList.contains('auto-compact') || panel1.getAttribute('data-state') === 'collapsed';
                if (!isNarrow) {
                    hideTooltip();
                    return;
                }

                const text = target.getAttribute('data-tooltip');
                if (!text) return;

                const rect = target.getBoundingClientRect();
                floatingEl.textContent = text;
                floatingEl.style.left = (rect.left + rect.width / 2) + 'px';
                floatingEl.style.top = (rect.top - 8) + 'px';
                floatingEl.style.transform = 'translate(-50%, -100%)';
                floatingEl.style.opacity = '1';
                floatingEl.style.visibility = 'visible';
            });

            document.addEventListener('mouseout', (e) => {
                const target = e.target.closest('#learn-panel-1 [data-tooltip], #learn-panel-1 [title]');
                if (target) {
                    hideTooltip();
                }
            });

            window.addEventListener('scroll', hideTooltip, true);
        },

        // Save and Restore Pane Widths & Three Panel Sizes to DB / Storage
        savePreferenceToDB: function (key, value) {
            clearTimeout(this._dbSyncTimeout);
            this._pendingPrefs = this._pendingPrefs || {};
            this._pendingPrefs[key] = value;
            this._dbSyncTimeout = setTimeout(() => {
                const prefs = { ...this._pendingPrefs };
                this._pendingPrefs = {};
                Object.keys(prefs).forEach(k => {
                    const fd = new FormData();
                    fd.append('preference_id', k);
                    fd.append('value', prefs[k]);
                    fetch('/api/user/preference_save.php', { method: 'POST', body: fd }).catch(() => {});
                });
            }, 300);
        },

        savePaneWidth: function (id, width) {
            document.documentElement.style.setProperty(`--${id}-saved-width`, width + 'px');
            localStorage.removeItem(`learn-pane-${id}`);
            sessionStorage.removeItem(`learn-pane-${id}`);

            // Compute and save ONLY learnAiThreePanelSizes
            const wrapper = document.querySelector('.learn-app-wrapper');
            if (wrapper) {
                const totalW = wrapper.offsetWidth || window.innerWidth;
                const p1El = document.getElementById('learn-panel-1') || document.getElementById('outlineSidebar') || document.getElementById('courseSidebar');
                const p3El = document.getElementById('learn-panel-3') || document.getElementById('paneAI');
                if (p1El && totalW > 0) {
                    const w1 = p1El.offsetWidth;
                    let existingPct3 = 25;
                    try {
                        const prev = JSON.parse(sessionStorage.getItem('learnAiThreePanelSizes') || '[]');
                        if (Array.isArray(prev) && prev[2] > 10) existingPct3 = prev[2];
                    } catch(e) {}

                    const pct1 = (w1 / totalW) * 100;
                    const pct3 = p3El ? Math.max(15, (p3El.offsetWidth / totalW) * 100) : existingPct3;
                    const pct2 = Math.max(10, 100 - pct1 - pct3);
                    const sizesStr = JSON.stringify([pct1, pct2, pct3]);
                    localStorage.removeItem('learnAiThreePanelSizes');
                    sessionStorage.setItem('learnAiThreePanelSizes', sizesStr);
                    this.savePreferenceToDB('learnAiThreePanelSizes', sizesStr);
                }
            }
        },

        restorePaneWidths: function () {
            const prefs = (window.TOM_CONFIG && window.TOM_CONFIG.ui_preferences) || {};

            ['outlineSidebar', 'courseSidebar', 'paneAI'].forEach(id => {
                localStorage.removeItem(`learn-pane-${id}`);
                sessionStorage.removeItem(`learn-pane-${id}`);
            });

            localStorage.removeItem('learnAiThreePanelSizes');
            let threePanelSizes = sessionStorage.getItem('learnAiThreePanelSizes') || prefs['learnAiThreePanelSizes'];
            if (threePanelSizes && typeof threePanelSizes !== 'string') threePanelSizes = JSON.stringify(threePanelSizes);
            if (threePanelSizes) {
                sessionStorage.setItem('learnAiThreePanelSizes', threePanelSizes);
                try {
                    const arr = JSON.parse(threePanelSizes);
                    if (Array.isArray(arr) && arr.length === 3) {
                        const p1El = document.getElementById('learn-panel-1') || document.getElementById('outlineSidebar') || document.getElementById('courseSidebar');
                        const p2El = document.getElementById('learn-panel-2');
                        const p3El = document.getElementById('learn-panel-3') || document.getElementById('paneAI');
                        const p3Pct = (arr[2] && arr[2] > 12) ? arr[2] : 25;
                        if (p1El) {
                            p1El.style.width = arr[0] + '%';
                            p1El.style.flexBasis = arr[0] + '%';
                            document.documentElement.style.setProperty(`--${p1El.id}-saved-width`, arr[0] + '%');
                            if (p1El.id === 'outlineSidebar' || p1El.id === 'learn-panel-1') {
                                const wrapperW = p1El.parentElement ? p1El.parentElement.offsetWidth : window.innerWidth;
                                const p1Px = (arr[0] / 100) * wrapperW;
                                this.setOutlineState(p1El, p1Px > 175 ? 'expanded' : 'collapsed');
                            }
                        }
                        if (p2El) {
                            p2El.style.width = `calc(100% - ${arr[0]}% - ${p3Pct}% - 8px)`;
                            p2El.style.flexBasis = `calc(100% - ${arr[0]}% - ${p3Pct}% - 8px)`;
                        }
                        if (p3El) {
                            p3El.style.width = p3Pct + '%';
                            p3El.style.flexBasis = p3Pct + '%';
                            document.documentElement.style.setProperty('--paneAI-saved-width', p3Pct + '%');
                        }
                    }
                } catch (e) {}
            }
            const zf = document.getElementById('learn-panel-zero-flicker');
            if (zf) zf.remove();
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
                if (targetId === 'learn-panel-1' || targetId === 'outlineSidebar') {
                    newWidth = Math.max(68, Math.min(500, newWidth));
                    const isCollapsed = target.getAttribute('data-state') === 'collapsed' || target.classList.contains('auto-compact');
                    if (newWidth > 110 && isCollapsed) {
                        this.setOutlineState(target, 'expanded');
                    } else if (newWidth <= 110 && !isCollapsed) {
                        newWidth = 68;
                        this.setOutlineState(target, 'collapsed');
                    }
                } else if (targetId === 'learn-panel-2' || targetId === 'courseSidebar') {
                    newWidth = Math.max(250, Math.min(1000, newWidth));
                } else if (targetId === 'learn-panel-3' || targetId === 'paneAI') {
                    newWidth = Math.max(250, Math.min(800, newWidth));
                }

                // Double Apply for flexbox and standard layout
                target.style.width = newWidth + 'px';
                target.style.flexBasis = newWidth + 'px';
                target.style.minWidth = '68px';
                document.documentElement.style.setProperty(`--${targetId}-saved-width`, newWidth + 'px');
            }
        },

        setOutlineState: function (sidebar, state) {
            const compactView = sidebar.querySelector('.outline-compact');
            const fullView = sidebar.querySelector('.outline-full');

            if (state === 'expanded') {
                sidebar.setAttribute('data-state', 'expanded');
                sidebar.classList.remove('auto-compact');
                sidebar.style.removeProperty('max-width');
                if (compactView && fullView) {
                    compactView.classList.add('d-none');
                    compactView.classList.remove('d-flex');
                    fullView.classList.remove('d-none');
                    fullView.classList.add('d-flex');
                }
            } else {
                sidebar.setAttribute('data-state', 'collapsed');
                sidebar.classList.add('auto-compact');
                sidebar.style.width = '68px';
                sidebar.style.flexBasis = '68px';
                if (compactView && fullView) {
                    fullView.classList.add('d-none');
                    fullView.classList.remove('d-flex');
                    compactView.classList.remove('d-none');
                    compactView.classList.add('d-flex');
                }
            }
        },

        toggleOutline: function () {
            const sidebar = document.getElementById('learn-panel-1') || document.getElementById('outlineSidebar');
            if (!sidebar) return;

            const isCollapsed = sidebar.getAttribute('data-state') === 'collapsed' || sidebar.classList.contains('auto-compact');
            const newWidth = isCollapsed ? 280 : 68;

            sidebar.style.width = newWidth + 'px';
            sidebar.style.flexBasis = newWidth + 'px';
            this.setOutlineState(sidebar, isCollapsed ? 'expanded' : 'collapsed');
            this.savePaneWidth(sidebar.id, newWidth);
        },

        adjustVHE: function (appWrapper) {
            const header = document.querySelector('header.header');
            const footer = document.querySelector('footer.footer');

            const headerHeight = header ? (header.offsetHeight || 64) : 64;
            const footerHeight = footer ? (footer.offsetHeight || 38) : 38;

            const availableHeight = window.innerHeight - headerHeight - footerHeight;
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
            if (chatInput.dataset.aiChatInit === 'true') return;
            chatInput.dataset.aiChatInit = 'true';

            // Load Chat History Asynchronously
            const lessonId = document.getElementById('currentLessonId')?.value || '';
            chatHistory.innerHTML = '<div class="text-center p-3 text-secondary small"><i class="bx bx-loader-alt bx-spin"></i> Loading chat history...</div>';
            
            // 4. Load Chat History for this chapter/lesson
            fetch(`/src/api/learnAI/history.php?lesson_id=${lessonId}&chapter_id=${chapterId}`)
                .then(r => r.text())
                .then(html => {
                    chatHistory.innerHTML = html;
                    
                    chatHistory.querySelectorAll('.ai-row .msg-bubble p').forEach(p => {
                        if (p.innerText.trim()) {
                            LearnApp.renderMarkdownWithHighlighting(p.innerText, p);
                        }
                    });

                    // Restore token usage metrics from history
                    LearnApp.tokenStats.totalInputTokens = 0;
                    LearnApp.tokenStats.totalOutputTokens = 0;
                    LearnApp.tokenStats.totalCachedTokens = 0;
                    chatHistory.querySelectorAll('.ai-row[data-total-tokens]').forEach(row => {
                        LearnApp.tokenStats.totalInputTokens += parseInt(row.getAttribute('data-input-tokens') || 0, 10);
                        LearnApp.tokenStats.totalOutputTokens += parseInt(row.getAttribute('data-output-tokens') || 0, 10);
                        LearnApp.tokenStats.totalCachedTokens += parseInt(row.getAttribute('data-cached-tokens') || 0, 10);
                    });
                    LearnApp.updateStatsBar();
                    
                    setTimeout(() => { chatHistory.scrollTop = chatHistory.scrollHeight; }, 100);
                })
                .catch(err => {
                    chatHistory.innerHTML = '<div class="text-center p-3 text-danger small">Failed to load chat history.</div>';
                });

            const userSessionId = 'sess_' + Math.random().toString(36).substr(2, 9);
            if (window.aiSocket && typeof window.aiSocket.disconnect === 'function') {
                window.aiSocket.disconnect();
            }
            window.aiSocket = new TomSocketClient();
            const aiSocket = window.aiSocket;
            let idleTimer = null;

            const handleAIStream = (data) => {
                const aiMsgContainer = document.querySelector('.current-ai-stream');

                if (data.type === 'tool_execution') {
                    
                    // Inject tool badge ABOVE the current AI message
                    if (aiMsgContainer) {
                        const toolId = 'tool_' + Math.random().toString(36).substr(2, 9);
                        const toolBadgeDiv = document.createElement('div');
                        toolBadgeDiv.className = 'tool-badge-wrapper mb-1';
                        toolBadgeDiv.innerHTML = `
                            <button class="tool-popover-btn" data-target="${toolId}">
                                <i class='bx bxs-check-circle text-success me-1'></i>
                                <i class='bx bxs-terminal me-1'></i> 1 tool
                            </button>
                            <div id="${toolId}" class="tool-popover-card">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <i class='bx bxs-check-circle text-success'></i>
                                    <strong class="text-white">${data.tool_name || 'Execute'}</strong>
                                </div>
                                <div class="text-secondary small">${data.tool_output || 'Executed locally'}</div>
                            </div>
                        `;
                        // Insert before the AI message container
                        aiMsgContainer.parentNode.insertBefore(toolBadgeDiv, aiMsgContainer);
                        chatHistory.scrollTop = chatHistory.scrollHeight;
                    }
                    return;
                }

                if (!aiMsgContainer) return;
                const p = aiMsgContainer.querySelector('p');

                if (data.type === 'stream_end') {
                    const dots = aiMsgContainer.querySelector('.typing-dots');
                    if (dots) dots.remove();

                    if (p && p.dataset.rawMd) {
                        LearnApp.renderMarkdownWithHighlighting(p.dataset.rawMd, p);
                    }
                    aiMsgContainer.classList.remove('current-ai-stream');
                    chatHistory.scrollTop = chatHistory.scrollHeight;

                    // Update token stats bar with usage data
                    if (data.usage) {
                        LearnApp.updateStatsBar(data.usage);
                    }
                    return;
                }

                if (data.type === 'text_delta') {
                    if (p.querySelector('.typing-dots')) {
                        p.innerHTML = '';
                    }

                    p.dataset.rawMd = (p.dataset.rawMd || '') + data.data;
                    LearnApp.renderMarkdownWithHighlighting(p.dataset.rawMd, p);
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

            // Event delegation for tool popovers
            document.addEventListener('click', (e) => {
                const btn = e.target.closest('.tool-popover-btn');
                if (btn) {
                    const targetId = btn.getAttribute('data-target');
                    const target = document.getElementById(targetId);
                    if (target) {
                        // Close others
                        document.querySelectorAll('.tool-popover-card.show').forEach(card => {
                            if (card.id !== targetId) card.classList.remove('show');
                        });
                        target.classList.toggle('show');
                    }
                } else if (!e.target.closest('.tool-popover-card')) {
                    // Clicked outside, close all
                    document.querySelectorAll('.tool-popover-card.show').forEach(card => {
                        card.classList.remove('show');
                    });
                }
            });

            // Re-establish/refresh connection idleness on typing
            chatInput.addEventListener('input', resetIdleTimer);

            chatSend.addEventListener('click', () => {
                const query = chatInput.value.trim();
                const currentLessonId = document.getElementById('currentLessonId')?.value || '';
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
                    body: JSON.stringify({ query, lesson_id: currentLessonId, chapter_id: currentChapterId, message_id: messageId, session_id: userSessionId, ai_model: aiModel })
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
                        <img src="${aiAvatar}" style="width: 30px;" alt="AI">
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
                    <!-- <div class="msg-avatar">
                        <img src="${userAvatar}" style="width: 30px; ${userAvatarStyle}" alt="User">
                    </div> -->
                `;
            }

            chatHistory.appendChild(msgDiv);
            chatHistory.scrollTop = chatHistory.scrollHeight;
        },

        renderMarkdownWithHighlighting: function(markdownText, container) {
            if (!container) return;
            if (typeof marked !== 'undefined') {
                container.innerHTML = marked.parse(markdownText);
            } else {
                let retries = 0;
                const checkMarked = setInterval(() => {
                    if (typeof marked !== 'undefined') {
                        clearInterval(checkMarked);
                        container.innerHTML = marked.parse(markdownText);
                        if (typeof hljs !== 'undefined') {
                            container.querySelectorAll('pre code').forEach((el) => hljs.highlightElement(el));
                        }
                    }
                    if (retries++ > 20) {
                        clearInterval(checkMarked);
                        if (container.innerHTML.trim() === '') {
                             container.innerText = markdownText;
                        }
                    }
                }, 100);
            }

            if (typeof hljs !== 'undefined') {
                container.querySelectorAll('pre code').forEach((el) => {
                    hljs.highlightElement(el);
                });
            }

            // Add Copy buttons to code blocks
            container.querySelectorAll('pre').forEach((pre) => {
                if (pre.querySelector('.btn-copy-code')) return;
                pre.style.position = 'relative';

                const copyBtn = document.createElement('button');
                copyBtn.className = 'btn btn-sm btn-dark btn-copy-code border border-secondary border-opacity-25';
                copyBtn.innerHTML = '<i class="bx bx-copy"></i> Copy';
                copyBtn.style.position = 'absolute';
                copyBtn.style.top = '0.5rem';
                copyBtn.style.right = '0.5rem';
                copyBtn.style.zIndex = '5';

                copyBtn.addEventListener('click', () => {
                    const codeText = pre.querySelector('code')?.innerText || pre.innerText;
                    navigator.clipboard.writeText(codeText).then(() => {
                        copyBtn.innerHTML = '<i class="bx bx-check text-success"></i> Copied!';
                        setTimeout(() => { copyBtn.innerHTML = '<i class="bx bx-copy"></i> Copy'; }, 2000);
                    });
                });

                pre.appendChild(copyBtn);
            });
        },

        initContentGenerator: function () {
            const container = document.getElementById('chapterContentContainer');
            if (!container) return;
            if (container.dataset.contentInit === 'true') return;
            container.dataset.contentInit = 'true';

            // Check if there is raw fallback markdown to highlight immediately
            const rawFallback = container.querySelector('.raw-markdown-fallback, .raw-markdown');
            if (rawFallback) {
                const md = container.getAttribute('data-raw-md') || rawFallback.innerText;
                this.renderMarkdownWithHighlighting(md, container);
            } else {
                // Even if HTML is server-rendered, apply Highlight.js & Copy buttons to code blocks
                if (typeof hljs !== 'undefined') {
                    container.querySelectorAll('pre code').forEach((el) => hljs.highlightElement(el));
                }
                container.querySelectorAll('pre').forEach((pre) => {
                    if (pre.querySelector('.btn-copy-code')) return;
                    pre.style.position = 'relative';

                    const copyBtn = document.createElement('button');
                    copyBtn.className = 'btn btn-sm btn-dark btn-copy-code border border-secondary border-opacity-25';
                    copyBtn.innerHTML = '<i class="bx bx-copy"></i> Copy';
                    copyBtn.style.position = 'absolute';
                    copyBtn.style.top = '0.5rem';
                    copyBtn.style.right = '0.5rem';
                    copyBtn.style.zIndex = '5';

                    copyBtn.addEventListener('click', () => {
                        const codeText = pre.querySelector('code')?.innerText || pre.innerText;
                        navigator.clipboard.writeText(codeText).then(() => {
                            copyBtn.innerHTML = '<i class="bx bx-check text-success"></i> Copied!';
                            setTimeout(() => { copyBtn.innerHTML = '<i class="bx bx-copy"></i> Copy'; }, 2000);
                        });
                    });

                    pre.appendChild(copyBtn);
                });
            }

            const triggerGenerate = (chapterId) => {
                if (!chapterId) return;
                const statusDiv = document.getElementById('contentGeneratingStatus');
                if (statusDiv) statusDiv.classList.remove('d-none');

                // Clear prompt
                const emptyPrompt = document.getElementById('emptyContentPrompt');
                if (emptyPrompt) emptyPrompt.remove();

                const sessionId = 'content_sess_' + Math.random().toString(36).substr(2, 9);
                const messageId = 'content_msg_' + Math.random().toString(36).substr(2, 9);

                let accumulatedMd = "";

                // Subscribe to streaming topic
                if (typeof TomSocketClient !== 'undefined') {
                    const contentSocket = new TomSocketClient();
                    contentSocket.connect('/topic/content_stream.' + sessionId, (frame) => {
                        try {
                            let payload = frame;
                            if (typeof frame === 'string') {
                                try { payload = JSON.parse(frame); } catch(e) {}
                            } else if (frame && frame.body) {
                                try { payload = JSON.parse(frame.body); } catch(e) {}
                            }
                            if (payload && payload.type === 'text_delta') {
                                accumulatedMd += payload.data;
                                this.renderMarkdownWithHighlighting(accumulatedMd, container);
                            } else if (payload && payload.type === 'stream_end') {
                                if (statusDiv) statusDiv.classList.add('d-none');
                                this.renderMarkdownWithHighlighting(accumulatedMd, container);
                            }
                        } catch (e) {
                            console.error('Content stream error:', e);
                        }
                    });
                }

                // Call generate API
                fetch('/src/api/learnAI/content_generate.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({
                        chapter_id: chapterId,
                        session_id: sessionId,
                        message_id: messageId
                    })
                })
                    .then(res => res.json())
                    .catch(err => {
                        console.error('Content generate trigger failed:', err);
                        if (statusDiv) statusDiv.classList.add('d-none');
                    });
            };

            const btnHeader = document.getElementById('btnGenerateContent');
            if (btnHeader) {
                btnHeader.addEventListener('click', () => {
                    const chId = btnHeader.getAttribute('data-chapter-id');
                    triggerGenerate(chId);
                });
            }

            const btnEmpty = container.querySelector('.btn-trigger-generate');
            if (btnEmpty) {
                btnEmpty.addEventListener('click', () => {
                    const chId = btnHeader ? btnHeader.getAttribute('data-chapter-id') : null;
                    triggerGenerate(chId);
                });
            }
        }
    };

    // Initialize
    if (typeof jQuery !== 'undefined') {
        jQuery(document).ready(function() {
            LearnApp.init();
        });
    } else if (document.readyState === 'loading') {
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
