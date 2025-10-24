jQuery(document).ready(function($) {
    
    // Tab switching
    $('.tab-button').click(function() {
        $('.tab-button').removeClass('active');
        $(this).addClass('active');
        
        $('.tab-content').removeClass('active');
        $('#' + $(this).data('tab')).addClass('active');
    });
    
    // Load scene when clicked
    $('.load-scene').click(function(e) {
        e.preventDefault();
        var sceneId = $(this).data('scene-id');
        if (pannellumViewer) {
            pannellumViewer.loadScene(sceneId);
        }
    });
    
    // Change scene via dropdown
    $('#scene-select').change(function() {
        var sceneId = $(this).val();
        if (pannellumViewer) {
            pannellumViewer.loadScene(sceneId);
        }
    });
    
    // Media uploader
    let mediaUploaderInstance = null;
    
    // Function to initialize media upload buttons
    function initMediaUploadButtons() {
        $('.media-upload-button').off('click').on('click', function(e) {
            e.preventDefault();
            
            // Close existing uploader if open
            if (mediaUploaderInstance) {
                mediaUploaderInstance.close();
            }
            
            var target = $(this).data('target');
            mediaUploaderInstance = wp.media({
                title: 'Choose Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false
            });
            
            mediaUploaderInstance.on('select', function() {
                var attachment = mediaUploaderInstance.state().get('selection').first().toJSON();
                $(target).val(attachment.url);
                
                // Update preview based on which input was updated
                if (target === '#panorama-image') {
                    updatePanoramaPreview(attachment.url);
                } else if (target === '#pin-image') {
                    updatePinImagePreview(attachment.url);
                }
                
                mediaUploaderInstance = null;
            });
            
            mediaUploaderInstance.on('close', function() {
                mediaUploaderInstance = null;
            });
            
            mediaUploaderInstance.open();
        });
    }
    
    // Update panorama preview - FIXED VERSION
    function updatePanoramaPreview(imageUrl) {
        const preview = $('#panorama-image-preview');
        const actionButtons = $('#panorama-action-buttons');
        
        if (imageUrl) {
            // Show uploaded image - hide default state, show background image
            preview.find('#panorama-default-state').hide();
            preview.css({
                'background-image': 'url(' + imageUrl + ')',
                'background-size': 'cover',
                'background-position': 'center',
                'background-repeat': 'no-repeat'
            });
            actionButtons.show();
        } else {
            // Reset to default state - show default state, remove background
            preview.css({
                'background-image': 'none',
                'background-size': '',
                'background-position': '',
                'background-repeat': ''
            });
            preview.find('#panorama-default-state').show();
            actionButtons.hide();
            
            // Re-initialize the button
            initMediaUploadButtons();
        }
    }
    
    // Update pin image preview - FIXED VERSION
    function updatePinImagePreview(imageUrl) {
        const preview = $('#pin-image-preview');
        const actionButtons = $('#pin-action-buttons');
        
        if (imageUrl) {
            // Show uploaded image - hide default state, show background image
            preview.find('#pin-default-state').hide();
            preview.css({
                'background-image': 'url(' + imageUrl + ')',
                'background-size': 'cover',
                'background-position': 'center',
                'background-repeat': 'no-repeat'
            });
            actionButtons.show();
        } else {
            // Reset to default state - show default state, remove background
            preview.css({
                'background-image': 'none',
                'background-size': '',
                'background-position': '',
                'background-repeat': ''
            });
            preview.find('#pin-default-state').show();
            actionButtons.hide();
        }
    }
    
    // Initialize media upload buttons on page load
    initMediaUploadButtons();
    
    // Re-initialize media upload buttons when modals are opened
    function reinitMediaUploadButtons() {
        setTimeout(initMediaUploadButtons, 100);
    }
    
    // Update displayed position values
    function updatePositionDisplay() {
        $('#display-pitch').text($('#hotspot-pitch').val());
        $('#display-yaw').text($('#hotspot-yaw').val());
    }
    
    // Initial position display
    updatePositionDisplay();
    
    // Modal functionality
    $('.close-modal, .cancel-modal').click(function() {
        $('.webronic-modal').hide();
        // Reset forms when modal is closed
        resetSceneForm();
        resetHotspotForm();
    });
    
    // Reset scene form to "Add" state
    function resetSceneForm() {
        $('#scene-modal-title').text('Add New Scene');
        $('#scene-submit-button').text('Add Scene');
        $('#scene-id').val('scene_' + Math.random().toString(36).substr(2, 9));
        $('#scene-title').val('');
        $('#panorama-image').val('');
        $('#scene-yaw').val('0');
        $('#scene-pitch').val('0');
        $('#scene-hfov').val('100');
        
        // Auto-check main scene for first scene
        const isFirstScene = webronicVirtualTour.is_first_scene || false;
        $('#is-default-scene').prop('checked', isFirstScene);
        
        // Reset panorama image preview
        updatePanoramaPreview('');
        
        // Clear scene name error
        $('#scene-name-error').hide();
    }
    
    // Reset hotspot form to "Add" state
    function resetHotspotForm() {
        $('#hotspot-modal-title').text('Add Interactive Pin');
        $('#hotspot-submit-button').text('Add');
        $('#hotspot-id').val('');
        $('#hotspot-text').val('');
        $('#hotspot-type').val('');
        $('#hotspot-pitch').val('');
        $('#hotspot-yaw').val('');
        $('#target-scene-id').val('');
        $('#pin-info').val('');
        $('#pin-image').val('');
        $('#hotspot-css-class').val('');
        
        // Reset pin image preview
        updatePinImagePreview('');
        
        // Reset direction
        $('input[name="hotspot_direction"]').prop('checked', false);
        $('#direction-error').hide();
        
        // Hide conditional fields
        $('#target-scene-wrap').hide();
        $('#direction-wrap').hide();
        $('#info-fields-wrap').hide();
        
        // ALWAYS show pin info and image sections for info hotspots
        $('.pin-info-section').show();
        $('.pin-image-section').show();
    }
    
    // Add new scene
    $('#add-new-scene, #add-first-scene').click(function() {
        resetSceneForm();
        $('#scene-modal').show();
        reinitMediaUploadButtons();
    });
    
    // Edit scene
    $(document).on('click', '.edit-scene', function(e) {
        e.preventDefault();
        
        $('#scene-modal-title').text('Edit Scene');
        $('#scene-submit-button').text('Update Scene');
        $('#scene-id').val($(this).data('scene-id'));
        $('#scene-title').val($(this).data('scene-title'));
        $('#panorama-image').val($(this).data('panorama-image'));
        $('#scene-yaw').val($(this).data('scene-yaw'));
        $('#scene-pitch').val($(this).data('scene-pitch'));
        $('#scene-hfov').val($(this).data('scene-hfov'));
        $('#is-default-scene').prop('checked', $(this).data('is-default') == '1');
        
        // Update panorama image preview if image exists
        updatePanoramaPreview($(this).data('panorama-image'));
        
        $('#scene-modal').show();
        reinitMediaUploadButtons();
    });
    
    // Edit hotspot
    $(document).on('click', '.edit-hotspot', function(e) {
        e.preventDefault();
        
        $('#hotspot-modal-title').text('Edit Interactive Pin');
        $('#hotspot-submit-button').text('Update Pin');
        $('#hotspot-id').val($(this).data('hotspot-id'));
        $('#hotspot-text').val($(this).data('hotspot-text'));
        $('#hotspot-type').val($(this).data('hotspot-type'));
        $('#hotspot-pitch').val($(this).data('pitch'));
        $('#hotspot-yaw').val($(this).data('yaw'));
        $('#target-scene-id').val($(this).data('target-scene-id'));
        $('#pin-info').val($(this).data('pin-info') || '');
        $('#pin-image').val($(this).data('pin-image') || '');
        
        // Update pin image preview
        updatePinImagePreview($(this).data('pin-image'));
        
        // Set direction if it's a scene hotspot
        if ($(this).data('hotspot-type') === 'scene') {
            var cssClass = $(this).data('css-class') || '';
            if (cssClass.includes('up')) {
                $('input[name="hotspot_direction"][value="up"]').prop('checked', true);
            } else if (cssClass.includes('right')) {
                $('input[name="hotspot_direction"][value="right"]').prop('checked', true);
            } else if (cssClass.includes('left')) {
                $('input[name="hotspot_direction"][value="left"]').prop('checked', true);
            }
        }
        
        // Update position display
        updatePositionDisplay();
        
        // Trigger change event to update visibility
        $('#hotspot-type').trigger('change');
        
        $('#hotspot-modal').show();
        reinitMediaUploadButtons();
    });
    
    // Handle hotspot type change
    $(document).on('change', '#hotspot-type', function() {
        var hotspotType = $(this).val();
        
        if (hotspotType === 'scene') {
            $('#target-scene-wrap').show();
            $('#direction-wrap').show();
            $('#info-fields-wrap').hide();
            // Remove required from pin info for scene hotspots
            $('#pin-info').removeAttr('required');
        } else if (hotspotType === 'info') {
            $('#target-scene-wrap').hide();
            $('#direction-wrap').hide();
            $('#info-fields-wrap').show();
            // Add required to pin info for info hotspots
            $('#pin-info').attr('required', 'required');
            
            // ALWAYS show pin info and image sections for info hotspots
            $('.pin-info-section').show();
            $('.pin-image-section').show();
        } else {
            $('#target-scene-wrap').hide();
            $('#direction-wrap').hide();
            $('#info-fields-wrap').hide();
            $('#pin-info').removeAttr('required');
        }
    });
    
    // Handle hotspot form submission
    $('#hotspot-form').on('submit', function(e) {
        var hotspotType = $('#hotspot-type').val();
        var hotspotText = $('#hotspot-text').val().trim();
        
        // Basic validation for hotspot name
        if (!hotspotText) {
            e.preventDefault();
            alert('Interactive Pin Name is required.');
            $('#hotspot-text').focus();
            return false;
        }
        
        // Validation for navigation hotspots
        if (hotspotType === 'scene') {
            var targetSceneId = $('#target-scene-id').val();
            if (!targetSceneId) {
                e.preventDefault();
                alert('Target Scene is required for Navigation hotspots.');
                $('#target-scene-id').focus();
                return false;
            }
            
            // PIN DIRECTION VALIDATION WITH ALERT MESSAGE
            var direction = $('input[name="hotspot_direction"]:checked').val();
            if (!direction) {
                e.preventDefault();
                alert('Pin Direction is required for Navigation hotspots. Please select a direction (Up, Right, or Left).');
                return false;
            }
            
            var cssClass = 'custom-hotspot scene-hotspot ' + direction;
            $('#hotspot-css-class').val(cssClass);
        }
        
        // Validation for info hotspots
        if (hotspotType === 'info') {
            var pinInfo = $('#pin-info').val().trim();
            if (!pinInfo) {
                e.preventDefault();
                alert('Pin Info is required for Information hotspots.');
                $('#pin-info').focus();
                return false;
            }
            $('#hotspot-css-class').val('custom-hotspot info-hotspot');
        }
        
        return true;
    });
    
    // Handle pin image removal
    $(document).on('click', '#remove-pin-image', function(e) {
        e.preventDefault();
        $('#pin-image').val('');
        updatePinImagePreview('');
    });
    
    // Handle panorama image removal
    $(document).on('click', '#remove-panorama-image', function(e) {
        e.preventDefault();
        $('#panorama-image').val('');
        updatePanoramaPreview('');
    });
    
    // Scene name validation
    $(document).on('blur', '#scene-title', function() {
        var sceneName = $(this).val().trim();
        var currentSceneId = $('#scene-id').val();
        var scenes = webronicVirtualTour.scenes || [];
        
        if (sceneName) {
            var sceneExists = scenes.some(function(scene) {
                return scene.scene_title === sceneName && scene.scene_id !== currentSceneId;
            });
            
            if (sceneExists) {
                $('#scene-name-error').show();
            } else {
                $('#scene-name-error').hide();
            }
        }
    });
    
    $(document).on('input', '#scene-title', function() {
        $('#scene-name-error').hide();
    });
    
    // Handle scene form submission with duplicate name check
    $('#scene-form').on('submit', function(e) {
        var sceneName = $('#scene-title').val().trim();
        var currentSceneId = $('#scene-id').val();
        var scenes = webronicVirtualTour.scenes || [];
        
        if (sceneName) {
            var sceneExists = scenes.some(function(scene) {
                return scene.scene_title === sceneName && scene.scene_id !== currentSceneId;
            });
            
            if (sceneExists) {
                e.preventDefault();
                alert('Scene name already exists. Please use a different name.');
                $('#scene-title').focus();
                return false;
            }
        }
        
        return true;
    });
    
    // Close modal when clicking outside
    $(window).click(function(e) {
        if ($(e.target).hasClass('webronic-modal')) {
            $('.webronic-modal').hide();
            resetSceneForm();
            resetHotspotForm();
        }
    });
    
    // Settings functionality
    $('#settings-icon').click(function() {
        $('#settings-popup').show();
    });
    
    // Copy shortcode functionality
    $('#copy-shortcode').click(function() {
        var shortcodeInput = $('#tour-shortcode');
        shortcodeInput.select();
        document.execCommand('copy');
        
        // Show success message
        $('#copy-message').show();
        setTimeout(function() {
            $('#copy-message').hide();
        }, 2000);
    });
    
    
    
    // Initialize on page load
    $(document).ready(function() {
        initMediaUploadButtons();
        
        // Auto-check main scene for first scene
        const isFirstScene = webronicVirtualTour.is_first_scene || false;
        if (isFirstScene) {
            $('#is-default-scene').prop('checked', true);
            // Optionally disable the checkbox to make it clear it's automatic
            // $('#is-default-scene').prop('disabled', true);
        }
    });
});

const sceneForm = document.getElementById('scene-form');
const panoramaImageInput = document.getElementById('panorama-image');
const panoramaImagePreview = document.getElementById('panorama-image-preview');

sceneForm.addEventListener('submit', function(e) {
    if (!panoramaImageInput.value) {
        e.preventDefault();
        // visual nudge
        alert('Panorama Image is required.');
       
    }
});