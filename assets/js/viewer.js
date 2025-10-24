(function($) {
    'use strict';

    // Navigation history stack with improved tracking
    var sceneHistory = [];
    var currentHistoryIndex = -1;
    var isNavigatingBack = false;

    // ----- Hotspot Helpers -----
    function createInfoHotspotCard(hotspotDiv, args) {
        var existingCard = hotspotDiv.querySelector('.info-hotspot-card');
        if (existingCard) existingCard.remove();

        if (args.type === 'info') {
            var card = document.createElement('div');
            card.className = 'info-hotspot-card';
            var html = '';
            if (args.pinImage) {
                html += '<div class="hotspot-card-image"><img src="'+ args.pinImage +'" alt="'+ (args.text || '') +'"></div>';
            }
            html += '<div class="hotspot-card-title">'+ (args.text || '') +'</div>';
            if (args.pinInfo) {
                html += '<div class="hotspot-card-info">'+ args.pinInfo +'</div>';
            }
            card.innerHTML = html;
            hotspotDiv.appendChild(card);
            card.style.left = '100%';
            card.style.top = '0';
            card.style.marginLeft = '15px';
            card.style.transform = 'none';
            var timeout;
            hotspotDiv.addEventListener('mouseenter', function() {
                clearTimeout(timeout);
                card.style.display = 'block';
                card.style.opacity = '0';
                setTimeout(function(){ card.style.opacity = '1'; }, 10);
            });
            hotspotDiv.addEventListener('mouseleave', function() {
                card.style.opacity = '0';
                timeout = setTimeout(function(){ card.style.display = 'none'; }, 300);
            });
            card.style.display = 'none';
            card.style.transition = 'opacity 0.3s ease';
        }
    }

    function hotspotTooltip(hotspotDiv, args) {
        hotspotDiv.classList.add('custom-tooltip');
        if (args.type === 'scene') {
            var tooltip = document.createElement('div');
            tooltip.className = 'hotspot-tooltip';
            tooltip.textContent = 'TOWARDS ' + (args.text || '').toUpperCase();
            hotspotDiv.appendChild(tooltip);
            tooltip.style.bottom = '40px';
            tooltip.style.left = '50%';
            tooltip.style.transform = 'translateX(-50%)';
        }
        if (args.type === 'info') createInfoHotspotCard(hotspotDiv, args);
    }

    function hotspotClickHandler(hotspotDiv, args) {
        if (args.type === 'scene') {
            if (window.pannellumViewer && args.sceneId) {
                // Add current scene to history before navigating
                var currentScene = window.pannellumViewer.getScene();
                addToHistory(currentScene);
                window.pannellumViewer.loadScene(args.sceneId);
            }
        } else {
            var pinInfo = args.pinInfo || '';
            alert(args.text + (pinInfo ? (": " + pinInfo) : ""));
        }
    }

    // ----- Improved Navigation History -----
    function addToHistory(sceneId) {
        // Don't add to history if we're navigating back
        if (isNavigatingBack) {
            isNavigatingBack = false;
            return;
        }

        // Remove any future history if we're navigating back and then clicking a new hotspot
        if (currentHistoryIndex < sceneHistory.length - 1) {
            sceneHistory = sceneHistory.slice(0, currentHistoryIndex + 1);
        }
        
        // Only add if it's different from current scene
        if (sceneHistory[currentHistoryIndex] !== sceneId) {
            sceneHistory.push(sceneId);
            currentHistoryIndex = sceneHistory.length - 1;
        }
        
        updateBackButton();
    }

    function goBackInHistory() {
        if (currentHistoryIndex > 0) {
            isNavigatingBack = true;
            currentHistoryIndex--;
            var previousScene = sceneHistory[currentHistoryIndex];
            if (window.pannellumViewer && previousScene) {
                window.pannellumViewer.loadScene(previousScene);
            }
            updateBackButton();
        }
    }

    function updateBackButton() {
        var backBtn = $('.webronic-back-btn');
        if (currentHistoryIndex > 0) {
            backBtn.prop('disabled', false);
        } else {
            backBtn.prop('disabled', true);
        }
    }

    function goToHomeScene() {
        var homeSceneId = typeof webronicConfig !== 'undefined' ? webronicConfig.defaultSceneId : '';
        if (window.pannellumViewer && homeSceneId) {
            // Add current scene to history before going home
            var currentScene = window.pannellumViewer.getScene();
            addToHistory(currentScene);
            window.pannellumViewer.loadScene(homeSceneId);
        }
    }

    // ----- Navigation Helpers -----
    function getScenesArray() {
        return typeof webronicConfig !== 'undefined' ? webronicConfig.scenes : [];
    }

    function getSceneNavigationInfo(sceneId) {
        var scenes = getScenesArray();
        var currentIndex = scenes.findIndex(function(s){ return s.scene_id === sceneId; });
        return {
            currentIndex: currentIndex,
            totalScenes: scenes.length,
            prevScene: currentIndex > 0 ? scenes[currentIndex - 1] : null,
            nextScene: (currentIndex >= 0 && currentIndex < scenes.length - 1) ? scenes[currentIndex + 1] : null,
            currentScene: currentIndex >= 0 ? scenes[currentIndex] : null
        };
    }

    function updateCards(sceneId) {
        var nav = getSceneNavigationInfo(sceneId);
        // scene title
        $('.webronic-scene-title').text(nav.currentScene ? nav.currentScene.scene_title : '');
        // scene index (1-based)
        $('.webronic-scene-index').text(nav.currentIndex >= 0 ? (nav.currentIndex + 1) : '-');
        $('.webronic-scene-total').text(nav.totalScenes);
        // enable/disable arrows
        $('.webronic-prev').prop('disabled', !nav.prevScene);
        $('.webronic-next').prop('disabled', !nav.nextScene);
    }

    // ----- Fullscreen Controls -----
    function setupFullscreenControls() {
        const containerEl = document.querySelector('.webronic-virtual-tour-container');
        if (!containerEl) return;

        const fullscreenBtn = $('.webronic-fullscreen-toggle');
        const fullscreenIcon = $('.webronic-fullscreen-icon');
        const fullscreenText = $('.webronic-fullscreen-toggle .webronic-icon-text');

        function enterFullscreen() {
            if (containerEl.requestFullscreen) return containerEl.requestFullscreen();
            if (containerEl.webkitRequestFullscreen) return containerEl.webkitRequestFullscreen();
            if (containerEl.msRequestFullscreen) return containerEl.msRequestFullscreen();
        }

        function exitFullscreen() {
            if (document.exitFullscreen) return document.exitFullscreen();
            if (document.webkitExitFullscreen) return document.webkitExitFullscreen();
            if (document.msExitFullscreen) return document.msExitFullscreen();
        }

        function updateFullscreenButton() {
            const isFullscreen = document.fullscreenElement || 
                                document.webkitFullscreenElement || 
                                document.msFullscreenElement;
            
            if (isFullscreen) {
                fullscreenIcon.attr('src', fullscreenIcon.attr('src').replace('expand.png', 'exit.png'));
                fullscreenText.text('Exit');
                fullscreenBtn.attr('title', 'Exit full screen').attr('aria-label', 'Exit full screen');
            } else {
                fullscreenIcon.attr('src', fullscreenIcon.attr('src').replace('exit.png', 'expand.png'));
                fullscreenText.text('Full View');
                fullscreenBtn.attr('title', 'Full screen').attr('aria-label', 'Full screen');
            }
        }

        // Toggle fullscreen on button click
        fullscreenBtn.on('click', function() {
            const isFullscreen = document.fullscreenElement || 
                                document.webkitFullscreenElement || 
                                document.msFullscreenElement;
            
            if (isFullscreen) {
                exitFullscreen();
            } else {
                enterFullscreen();
            }
        });

        // Listen for fullscreen changes
        document.addEventListener('fullscreenchange', updateFullscreenButton);
        document.addEventListener('webkitfullscreenchange', updateFullscreenButton);
        document.addEventListener('msfullscreenchange', updateFullscreenButton);

        // Initial update
        updateFullscreenButton();
    }

    // ----- Initialize Viewer -----
    function initializeViewer() {
        var container = document.getElementById('pannellum-container');
        if (!container || typeof webronicConfig === 'undefined') {
            console.error('Pannellum container or config not found');
            return;
        }

        // Initialize history with starting scene
        sceneHistory = [webronicConfig.currentSceneId];
        currentHistoryIndex = 0;
        updateBackButton();

        // Prepare scenes data with proper functions
        var scenesData = JSON.parse(JSON.stringify(webronicConfig.scenesData));
        
        // Add hotspot functions to each scene
        Object.keys(scenesData).forEach(function(sceneId) {
            if (scenesData[sceneId].hotSpots) {
                scenesData[sceneId].hotSpots.forEach(function(hotspot) {
                    hotspot.createTooltipFunc = hotspotTooltip;
                    hotspot.clickHandlerFunc = hotspotClickHandler;
                });
            }
        });

        // Initialize Pannellum viewer
        window.pannellumViewer = pannellum.viewer('pannellum-container', {
            "default": {
                "firstScene": webronicConfig.currentSceneId,
                "sceneFadeDuration": 1000,
                "autoLoad": true,
                "autoRotate": false,
                "showControls": false,
                "mouseZoom": false,
                "showZoomCtrl": false,
                "showFullscreenCtrl": false,
                "keyboardZoom": false,
                "hfov": 100,
                "showTitle": false
            },
            "scenes": scenesData
        });

        // First update after init
        setTimeout(function() {
            updateCards(webronicConfig.currentSceneId);
        }, 500);

        // On scene change, update cards and history
        window.pannellumViewer.on('scenechange', function(newSceneId) {
            updateCards(newSceneId);
            
            // Only add to history if this is a new scene (not from back navigation)
            if (!isNavigatingBack && sceneHistory[currentHistoryIndex] !== newSceneId) {
                addToHistory(newSceneId);
            }
            
            // Reset the flag after scene change is complete
            isNavigatingBack = false;
        });

        // Arrow navigation events
        $('.webronic-prev').on('click', function() {
            var nav = getSceneNavigationInfo(window.pannellumViewer.getScene());
            if (nav.prevScene && window.pannellumViewer) {
                addToHistory(window.pannellumViewer.getScene());
                window.pannellumViewer.loadScene(nav.prevScene.scene_id);
            }
        });

        $('.webronic-next').on('click', function() {
            var nav = getSceneNavigationInfo(window.pannellumViewer.getScene());
            if (nav.nextScene && window.pannellumViewer) {
                addToHistory(window.pannellumViewer.getScene());
                window.pannellumViewer.loadScene(nav.nextScene.scene_id);
            }
        });

        // Home and Back button events
        $('.webronic-home-btn').on('click', goToHomeScene);
        $('.webronic-back-btn').on('click', goBackInHistory);

        // Setup fullscreen controls
        setupFullscreenControls();
    }

    // ----- Document Ready -----
    $(document).ready(function() {
        // Initialize virtual tour only if container exists
        if ($('.webronic-virtual-tour-container').length === 0) return;

        // Wait for Pannellum to load
        if (typeof pannellum !== 'undefined') {
            initializeViewer();
        } else {
            // Fallback: check every 100ms for pannellum availability
            var checkPannellum = setInterval(function() {
                if (typeof pannellum !== 'undefined') {
                    clearInterval(checkPannellum);
                    initializeViewer();
                }
            }, 100);
            
            // Timeout after 5 seconds
            setTimeout(function() {
                clearInterval(checkPannellum);
                if (typeof pannellum === 'undefined') {
                    console.error('Pannellum library failed to load');
                }
            }, 5000);
        }
    });

})(jQuery);