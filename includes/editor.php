<?php
function webronic_virtual_tour_editor_page() {
    global $wpdb;

    // Check if tour ID is provided
    if (!isset($_GET['tour_id'])) {
        wp_die('No tour ID provided');
    }

    $tour_id = intval($_GET['tour_id']);
    $tour    = webronic_virtual_tour_get_tour($tour_id);

    if (!$tour) {
        wp_die('Tour not found');
    }

    // Get all scenes for this tour
    $scenes = webronic_virtual_tour_get_scenes($tour_id);

    if (isset($_POST['save_scene'])) {
        check_admin_referer('webronic_virtual_tour_save_scene_' . $tour_id);

        // Check for duplicate scene name
        $scene_title = sanitize_text_field($_POST['scene_title']);
        $scene_exists = false;
        
        foreach ($scenes as $existing_scene) {
            if ($existing_scene->scene_title === $scene_title && $existing_scene->scene_id !== $_POST['scene_id']) {
                $scene_exists = true;
                break;
            }
        }
        
        if ($scene_exists) {
            echo '<div class="notice notice-error is-dismissible"><p>Scene name already exists. Please use a different name.</p></div>';
        } else {
            // HARD STOP: panorama image is required
            if (empty($_POST['panorama_image'])) {
                echo '<div class="notice notice-error is-dismissible"><p>Please select a Panorama Image before saving the scene.</p></div>';
            } else {
                $scene_data = array(
                    'scene_id'      => sanitize_text_field($_POST['scene_id']),
                    'scene_title'   => $scene_title,
                    'panorama_image'=> esc_url_raw($_POST['panorama_image']),
                    'scene_yaw'     => isset($_POST['scene_yaw'])   ? intval($_POST['scene_yaw'])   : 0,
                    'scene_pitch'   => isset($_POST['scene_pitch']) ? intval($_POST['scene_pitch']) : 0,
                    'scene_hfov'    => isset($_POST['scene_hfov'])  ? intval($_POST['scene_hfov'])  : 100,
                    'is_default'    => isset($_POST['is_default']) ? 1 : 0
                );

                // If this is the first scene being added, automatically set it as default
                if (empty($scenes)) {
                    $scene_data['is_default'] = 1;
                }

                if (webronic_virtual_tour_save_scene($tour_id, $scene_data)) {
                    wp_redirect(admin_url('admin.php?page=webronic-virtual-tour-editor&tour_id=' . $tour_id . '&message=scene_saved'));
                    exit;
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Error saving scene.</p></div>';
                }
            }
        }
    }

    if (isset($_POST['save_hotspot'])) {
        check_admin_referer('webronic_virtual_tour_save_hotspot_' . $tour_id);

        $hotspot_type = sanitize_text_field($_POST['hotspot_type']);

        // Validate direction for scene hotspots
        if ($hotspot_type === 'scene' && empty($_POST['hotspot_direction'])) {
            echo '<div class="notice notice-error is-dismissible"><p>Pin Direction is required for Navigation hotspots.</p></div>';
        } else {
            $hotspot_data = array(
                'scene_id'        => sanitize_text_field($_POST['scene_id']),
                'hotspot_id'      => isset($_POST['hotspot_id']) ? sanitize_text_field($_POST['hotspot_id']) : uniqid('hs_'),
                'hotspot_type'    => $hotspot_type,
                'pitch'           => floatval($_POST['pitch']),
                'yaw'             => floatval($_POST['yaw']),
                'text'            => sanitize_text_field($_POST['hotspot_text']),
                'pin_info'        => ($hotspot_type === 'info')  ? sanitize_textarea_field($_POST['pin_info'])        : '',
                'pin_image'       => ($hotspot_type === 'info')  ? esc_url_raw($_POST['pin_image'])                   : '',
                'target_scene_id' => ($hotspot_type === 'scene') ? sanitize_text_field($_POST['target_scene_id'])     : '',
                'css_class'       => isset($_POST['css_class'])  ? sanitize_text_field($_POST['css_class'])           : 'custom-hotspot ' . ($hotspot_type === 'scene' ? 'scene-hotspot up' : 'info-hotspot')
            );

            if (webronic_virtual_tour_save_hotspot($tour_id, $hotspot_data)) {
                // Redirect to avoid duplicate form submission
                $current_scene_id = sanitize_text_field($_POST['scene_id']);
                wp_redirect(admin_url('admin.php?page=webronic-virtual-tour-editor&tour_id=' . $tour_id . '&scene=' . $current_scene_id . '&message=hotspot_saved'));
                exit;
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Error saving hotspot.</p></div>';
            }
        }
    }

    if (isset($_GET['delete_scene'])) {
        check_admin_referer('delete_scene_' . $_GET['delete_scene']);

        if (webronic_virtual_tour_delete_scene($tour_id, $_GET['delete_scene'])) {
            // Redirect after deletion
            wp_redirect(admin_url('admin.php?page=webronic-virtual-tour-editor&tour_id=' . $tour_id . '&message=scene_deleted'));
            exit;
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Error deleting scene.</p></div>';
        }
    }

    if (isset($_GET['delete_hotspot'])) {
        check_admin_referer('delete_hotspot_' . $_GET['delete_hotspot']);

        if (webronic_virtual_tour_delete_hotspot($tour_id, $_GET['delete_hotspot'])) {
            // Redirect after deletion
            $current_scene_id = isset($_GET['current_scene']) ? sanitize_text_field($_GET['current_scene']) : '';
            wp_redirect(admin_url('admin.php?page=webronic-virtual-tour-editor&tour_id=' . $tour_id . '&scene=' . $current_scene_id . '&message=hotspot_deleted'));
            exit;
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Error deleting hotspot.</p></div>';
        }
    }

    // Show success messages based on URL parameter
    if (isset($_GET['message'])) {
        switch ($_GET['message']) {
            case 'scene_saved':
                echo '<div class="notice notice-success is-dismissible"><p>Scene saved successfully!</p></div>';
                break;
            case 'hotspot_saved':
                echo '<div class="notice notice-success is-dismissible"><p>Hotspot saved successfully!</p></div>';
                break;
            case 'scene_deleted':
                echo '<div class="notice notice-success is-dismissible"><p>Scene deleted successfully!</p></div>';
                break;
            case 'hotspot_deleted':
                echo '<div class="notice notice-success is-dismissible"><p>Hotspot deleted successfully!</p></div>';
                break;
        }
    }

    // Refresh scenes after operations
    $scenes = webronic_virtual_tour_get_scenes($tour_id);

    // Get default scene for preview
    $default_scene    = webronic_virtual_tour_get_default_scene($tour_id);
    $current_scene_id = isset($_GET['scene']) ? sanitize_text_field($_GET['scene']) : ($default_scene ? $default_scene->scene_id : '');
    $hotspots         = $current_scene_id ? webronic_virtual_tour_get_hotspots($tour_id, $current_scene_id) : array();

    // Enqueue scripts and styles
    wp_enqueue_media(); // REQUIRED for wp.media uploader

    wp_enqueue_script('pannellum', 'https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js', array(), '2.5.6', true);
    wp_enqueue_style('pannellum-css', 'https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css', array(), '2.5.6');

    wp_enqueue_script(
        'webronic-virtual-tour-editor',
        WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL . 'assets/js/editor.js',
        array('jquery', 'pannellum'),
        WEBRONIC_VIRTUAL_TOUR_VERSION,
        true // load in footer so wp.media is available
    );

    wp_enqueue_style('webronic-virtual-tour-editor', WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL . 'assets/css/editor.css', array(), WEBRONIC_VIRTUAL_TOUR_VERSION);
    wp_enqueue_style('dashicons');

    // Localize script with necessary data
    wp_localize_script('webronic-virtual-tour-editor', 'webronicVirtualTour', array(
        'ajaxurl'       => admin_url('admin-ajax.php'),
        'nonce'         => wp_create_nonce('webronic_virtual_tour_editor_nonce'),
        'tour_id'       => $tour_id,
        'current_scene' => $current_scene_id,
        'scenes'        => array_values($scenes),
        'is_first_scene' => empty($scenes) // Add flag for first scene
    ));
    ?>

    <div class="wrap webronic-virtual-tour-editor">
        <!-- FIX 3: Increased tour title size and made it bold -->
        <h1 style="margin-bottom:50px; font-size: 28px; font-weight: bold;">Edit 360 Virtual Tour: <?php echo esc_html($tour->tour_title); ?></h1>

        <div class="back-to-tours" style="display: flex; justify-content: space-between; align-items: center;">
            <a href="<?php echo admin_url('admin.php?page=webronic-virtual-tour'); ?>" title="Back to Tours List" style="display: flex; align-items: center;">
                <img src="<?php echo WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL; ?>assets/img/back_to_tour.png" alt="Back to Tours" style="width: 30px; height: 30px; margin-right: 10px;">
                <span style="font-size:20px; font-weight: bold; color: black; margin-top: -6px;"><?php echo esc_html($tour->tour_title); ?></span>
            </a>
            <div style="display: flex; align-items: center; gap: 8px;">
                <button class="button button-primary" id="add-new-scene" style="background-color: #4888E8; color: white; padding: 2px 12px; border: none; border-radius: 50px; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer;">
                    + Add New Scene
                </button>
                <img src="<?php echo WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL; ?>assets/img/setting.png" alt="Settings" style="width: 30px; height: 30px; cursor: pointer;" id="settings-icon">
            </div>
        </div>

        <!-- Modal for Scene Management -->
        <div id="scene-modal" class="webronic-modal" style="display:none;">
            <div class="modal-content" style="max-width: 500px; margin-top: 200px;">
                <div class="modal-header">
                    <h3 id="scene-modal-title">Add New Scene</h3>
                    <div class="header-actions">
                        <button type="button" class="button cancel-modal">Cancel</button>
                        <button type="submit" form="scene-form" name="save_scene" id="scene-submit-button" class="button button-primary">Add Scene</button>
                    </div>
                </div>
                <div class="modal-body">
                    <form method="post" id="scene-form">
                        <?php wp_nonce_field('webronic_virtual_tour_save_scene_' . $tour_id); ?>
                        <input type="hidden" name="scene_id" id="scene-id" value="<?php echo esc_attr(uniqid('scene_')); ?>">
                        <div class="form-group">
                            <label for="scene-title">Scene Name <span style="color: red;">*</span></label>
                            <input
                                type="text"
                                id="scene-title"
                                name="scene_title"
                                class="regular-text"
                                placeholder="Enter your scene name"
                                required
                            >
                            <div id="scene-name-error" style="color: red; font-size: 12px; display: none;">Scene name already exists.</div>
                        </div>
                        <div class="form-group">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <label for="panorama-image" style="margin: 0;">Panorama Image <span style="color: red;">*</span></label>
                                <div class="image-action-buttons" style="display: none;" id="panorama-action-buttons">
                                    <img
                                        src="<?php echo WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL; ?>assets/img/delete.png"
                                        alt="Remove Image"
                                        id="remove-panorama-image"
                                        style="width: 25px; height: 25px; cursor: pointer;"
                                        title="Remove Image"
                                    >
                                </div>
                            </div>
                            <div class="image-upload-preview" style="margin-top: 0;">
                                <!-- Image Preview Area -->
                                <div id="panorama-image-preview" class="image-preview" style="width: 90%; height: 150px; border: 2px dashed #ddd; display: flex; flex-direction: column; align-items: center; justify-content: center; background-size: cover; background-position: center; border-radius: 4px; cursor: pointer; text-align: center; padding: 20px;">
                                    <!-- Default upload state -->
                                    <div id="panorama-default-state">
                                        <img src="<?php echo WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL; ?>assets/img/upload2.png" alt="Upload Image" style="width: 50px; height: 50px; opacity: 0.6; margin-bottom: 10px;">
                                        <span style="color: #999; margin-bottom: 5px; font-size: 14px;">Drag & Drop your files here</span>
                                        <span style="color: #999; font-size: 12px; margin-bottom: 15px;">Supported Formats: .png, .jpg</span>
                                        <button type="button" class="button"
                                            style="background: #2271B1; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; width: 110px; height: 37px; display: flex; align-items: center; justify-content: center;"
                                            data-target="#panorama-image">
                                            Choose File
                                        </button>
                                    </div>
                                </div>
                                <!-- Hidden input to store the image URL -->
                                <input
                                    type="hidden"
                                    id="panorama-image"
                                    name="panorama_image"
                                    value=""
                                    required
                                >
                            </div>
                        </div>
                        <input type="hidden" id="scene-yaw"   name="scene_yaw"   value="0">
                        <input type="hidden" id="scene-pitch" name="scene-pitch" value="0">
                        <input type="hidden" id="scene-hfov"  name="scene-hfov"  value="100">

                        <div class="form-group" style="margin-top: 20px;">
                            <label>
                                <input type="checkbox" name="is_default" id="is-default-scene">
                                Set as Main scene
                            </label>
                            <?php if (empty($scenes)): ?>
                                <p style="font-size: 12px; color: #666; margin: 5px 0 0 0;">This will be automatically set as the main scene since it's the first scene.</p>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal for Hotspot Management -->
        <div id="hotspot-modal" class="webronic-modal" style="display:none;">
            <div class="modal-content" style="max-width: 580px; margin-top: 200px;">
                <div class="modal-header">
                    <h3 id="hotspot-modal-title">Add Interactive Pin</h3>
                    <div class="header-actions">
                        <button type="button" class="button cancel-modal">Cancel</button>
                        <button type="submit" form="hotspot-form" name="save_hotspot" id="hotspot-submit-button" class="button button-primary">Add</button>
                    </div>
                </div>
                <div class="modal-body">
                    <form method="post" id="hotspot-form">
                        <?php wp_nonce_field('webronic_virtual_tour_save_hotspot_' . $tour_id); ?>
                        <input type="hidden" name="scene_id"   id="hotspot-scene-id" value="<?php echo esc_attr($current_scene_id); ?>">
                        <input type="hidden" name="hotspot_id" id="hotspot-id"       value="">
                        <input type="hidden" name="pitch"      id="hotspot-pitch">
                        <input type="hidden" name="yaw"        id="hotspot-yaw">
                        <!-- ADD THIS HIDDEN FIELD FOR CSS CLASS -->
                        <input type="hidden" name="css_class"  id="hotspot-css-class" value="">

                        <!-- 1) Interactive Pin Name -->
                        <div class="form-group">
                            <label for="hotspot-text">Interactive Pin Name <span style="color: red;">*</span></label>
                            <input
                                type="text"
                                id="hotspot-text"
                                name="hotspot_text"
                                class="regular-text"
                                placeholder="Enter  Pin Name"
                                required
                            >
                        </div>

                        <!-- Navigation inline row (Pin Type + Target Scene) -->
                        <div id="nav-row" class="inline-fields">
                            <!-- Pin Type -->
                            <div class="form-group half">
                                <label for="hotspot-type">Pin Type <span style="color: red;">*</span></label>
                                <div class="select-wrap">
                                    <select id="hotspot-type" name="hotspot_type" class="custom-select" required>
                                        <option value="" disabled selected hidden>Select type</option>
                                        <option value="scene">Navigation</option>
                                        <option value="info">Information</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Target Scene -->
                            <div class="form-group half" id="target-scene-wrap" style="display:none;">
                                <label for="target-scene-id">Target Scene <span style="color: red;">*</span></label>
                                <div class="select-wrap">
                                    <select id="target-scene-id" name="target_scene_id" class="custom-select">
                                        <option value="" disabled selected hidden>Select target scene</option>
                                        <?php 
                                        // FIX 1: Only show existing scenes in target scene dropdown
                                        $existing_scenes = webronic_virtual_tour_get_scenes($tour_id);
                                        foreach ($existing_scenes as $scene): 
                                        ?>
                                            <option value="<?php echo esc_attr($scene->scene_id); ?>">
                                                <?php echo esc_html($scene->scene_title); ?>
                                            </option>
                                        <?php 
                                           
                                        endforeach; 
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Pin Direction (hidden until Pin Type = Navigation) -->
                        <div class="form-group" id="direction-wrap" style="display:none;">
                            <label>Pin Direction <span style="color: red;">*</span></label>
                            <div class="direction-options">
                                <label class="direction-option">
                                    <input type="radio" name="hotspot_direction" value="up" required>
                                    <img src="<?php echo WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL; ?>assets/hotspots/up.png" alt="Up" style="width: 50px; height: 50px; margin-left: 20px;">
                                    <span style=" margin-left: 33px;" >Up</span>
                                </label>
                                <label class="direction-option">
                                    <input type="radio" name="hotspot_direction" value="right" required>
                                    <img src="<?php echo WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL; ?>assets/hotspots/right.png" alt="Right" style="width: 50px; height: 50px; margin-left: 20px;">
                                    <span style=" margin-left: 33px;">Right</span>
                                </label>
                                <label class="direction-option">
                                    <input type="radio" name="hotspot_direction" value="left" required>
                                    <img src="<?php echo WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL; ?>assets/hotspots/left.png" alt="Left" style="width: 50px; height: 50px; margin-left: 20px;">
                                    <span style=" margin-left: 33px;">Left</span>
                                </label>
                            </div>
                            <div id="direction-error" style="color: red; font-size: 12px; display: none;">Please select a pin direction.</div>
                        </div>

                        <!-- Pin Info and Pin Image Section (ALWAYS SHOW for info hotspots in edit mode) -->
                        <div class="form-group" id="info-fields-wrap" style="display:none;">
                            <div class="horizontal-fields" style="display: flex; gap: 15px;">
                                <!-- Pin Info Section - 50% width (ALWAYS SHOW for info hotspots) -->
                                <div class="form-group pin-info-section" style="flex: 1;">
                                    <label for="pin-info">Pin Info <span style="color: red;">*</span></label>
                                    <textarea
                                        id="pin-info"
                                        name="pin_info"
                                        class="regular-text"
                                        placeholder="Enter pin information"
                                        rows="4"
                                        style="width: 100%;"
                                    ></textarea>
                                </div>

                                <!-- Pin Image Section - 50% width (ALWAYS SHOW for info hotspots) -->
                               <div class="form-group pin-image-section" style="flex: 1;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                        <label for="pin-image" style="margin: 0;">Pin Image</label>
                                        <div class="image-action-buttons" style="display: none;" id="pin-action-buttons">
                                            <img
                                                src="<?php echo WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL; ?>assets/img/delete.png"
                                                alt="Remove Image"
                                                id="remove-pin-image"
                                                style="width: 25px; height: 25px; cursor: pointer;"
                                                title="Remove Image"
                                            >
                                        </div>
                                    </div>
                                    <div class="image-upload-preview" style="margin-top: 0;">
                                        <!-- Image Preview -->
                                        <div id="pin-image-preview" class="image-preview" style="width: 100%; height: 150px; border: 2px dashed #ddd; display: flex; align-items: center; justify-content: center; background-size: cover; background-position: center; border-radius: 4px; cursor: pointer;">
                                            <!-- Default upload state -->
                                            <div id="pin-default-state">
                                                <img src="<?php echo WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL; ?>assets/img/upload.png" alt="Upload Image" style="width: 50px; height: 50px; opacity: 0.6;">
                                                <span style="color: #999; margin-left: 10px;">Click to upload image</span>
                                            </div>
                                        </div>
                                        <!-- Hidden input to store the image URL -->
                                        <input
                                            type="hidden"
                                            id="pin-image"
                                            name="pin_image"
                                            value=""
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Settings Popup Card -->
        <div id="settings-popup" class="webronic-modal" style="display:none;">
            <div class="modal-content" style="max-width: 500px; margin-top: 200px;">
                <div class="modal-header">
                    <h3>Tour Settings</h3>
                    <button type="button" class="cancel-modal" style="background: none; border: none; cursor: pointer;">
                        <img src="<?php echo WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL; ?>assets/img/cancel.png" alt="Cancel" style="width: 30px; height: 30px;">
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label><strong>Tour Embed Code</strong></label>
                        <div style="display: flex; gap: 8px; margin-top: 8px;">
                            <input type="text" class="regular-text" id="tour-shortcode" value='[webronic_virtual_tour id="<?php echo esc_attr($tour_id); ?>"]' readonly style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 6px; background: #f7f8fa;">
                            <button type="button" id="copy-shortcode" style="background: #2271b1; color: white; border: none; border-radius: 6px; cursor: pointer; padding: 10px 15px; display: flex; align-items: center; justify-content: center; transition: background 0.3s ease;">
                                <img src="<?php echo WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL; ?>assets/img/copy.png" alt="Copy Shortcode" style="width: 20px; height: 20px; filter: brightness(0) invert(1);">
                            </button>
                        </div>
                        <div id="copy-message" style="display: none; color: green; margin-top: 5px; font-size: 12px;">
                            Shortcode copied to clipboard!
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tour-editor-container <?php echo empty($scenes) ? 'no-scenes' : ''; ?>">
            <div class="editor-preview">
                <div id="pannellum-container"></div>
                <?php if ($default_scene): ?>
                <script>
                    var pannellumViewer;

                    jQuery(document).ready(function($) {
                        // =========================================================================
                        // DATA MANAGEMENT FUNCTIONS
                        // =========================================================================
                        function getSceneNavigationInfo(sceneId) {
                            var scenes = <?php echo json_encode(array_values($scenes)); ?>;
                            var currentIndex = -1;

                            for (var i = 0; i < scenes.length; i++) {
                                if (scenes[i].scene_id === sceneId) {
                                    currentIndex = i;
                                    break;
                                }
                            }

                            return {
                                currentIndex: currentIndex,
                                totalScenes: scenes.length,
                                prevScene: currentIndex > 0 ? scenes[currentIndex - 1] : null,
                                nextScene: currentIndex < scenes.length - 1 ? scenes[currentIndex + 1] : null,
                                currentScene: currentIndex >= 0 ? scenes[currentIndex] : null
                            };
                        }

                        // =========================================================================
                        // SCENE TABLE HIGHLIGHTING FUNCTION - FIXED
                        // =========================================================================
                        function highlightCurrentSceneInTable(sceneId) {
                            // Remove current scene highlight from all rows
                            $('.scene-title-link').removeClass('current-scene');
                            
                            // Add highlight to current scene
                            $('.scene-title-link[data-scene-id="' + sceneId + '"]').addClass('current-scene');
                            
                            // Update scene select dropdown
                            $('#scene-select').val(sceneId);
                        }

                        // =========================================================================
                        // CUSTOM CONTROLS CREATION
                        // =========================================================================
                        function createCustomControls() {
                            var container = document.getElementById('pannellum-container');

                            // Remove existing controls if they exist
                            var existingControls = container.querySelector('.webronic-custom-controls');
                            if (existingControls) {
                                existingControls.remove();
                            }

                            // Create custom controls container
                            var customControls = document.createElement('div');
                            customControls.className = 'webronic-custom-controls';
                            customControls.style.cssText = `
                                position: absolute;
                                bottom: 20px;
                                left: 50%;
                                transform: translateX(-50%);
                                display: flex;
                                align-items: center;
                                gap: 15px;
                                background: transparent;
                                padding: 10px 20px;
                                border-radius: 25px;
                                z-index: 1000;
                                backdrop-filter: none;
                                border: none;
                            `;

                            // Previous button
                            var prevButton = document.createElement('button');
                            prevButton.className = 'webronic-prev-button';
                            prevButton.innerHTML = `<img src="<?php echo WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL; ?>assets/img/prev2.png" alt="Previous" style="width: 35px; height: 35px;">`;
                            prevButton.style.cssText = `
                                background: none;
                                border: none;
                                cursor: pointer;
                                padding: 5px;
                                border-radius: 50%;
                                transition: background 0.3s;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                            `;

                            // Scene title - CHANGED TO BLACK COLOR
                            var sceneTitle = document.createElement('div');
                            sceneTitle.className = 'webronic-scene-title';
                            sceneTitle.style.cssText = `
                                color: #000000 !important;
                                font-weight: bold;
                                font-size: 16px;
                                min-width: 150px;
                                text-align: center;
                                padding: 0 10px;
                                text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.8);
                                background: rgba(255, 255, 255, 0.7);
                                border-radius: 20px;
                                padding: 8px 15px;
                            `;

                            // Next button
                            var nextButton = document.createElement('button');
                            nextButton.className = 'webronic-next-button';
                            nextButton.innerHTML = `<img src="<?php echo WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL; ?>assets/img/next2.png" alt="Next" style="width: 35px; height: 35px;">`;
                            nextButton.style.cssText = `
                                background: none;
                                border: none;
                                cursor: pointer;
                                padding: 5px;
                                border-radius: 50%;
                                transition: background 0.3s;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                            `;

                            // Assemble controls
                            customControls.appendChild(prevButton);
                            customControls.appendChild(sceneTitle);
                            customControls.appendChild(nextButton);
                            container.appendChild(customControls);

                            // Add hover effects for buttons
                            [prevButton, nextButton].forEach(button => {
                                button.addEventListener('mouseenter', function() {
                                    this.style.background = 'rgba(0, 0, 0, 0.1)';
                                });
                                button.addEventListener('mouseleave', function() {
                                    this.style.background = 'none';
                                });
                            });

                            // Navigation event handlers - FIXED TO UPDATE TABLE HIGHLIGHT
                            prevButton.addEventListener('click', function() {
                                var currentScene = pannellumViewer.getScene();
                                var navInfo = getSceneNavigationInfo(currentScene);
                                if (navInfo.prevScene) {
                                    pannellumViewer.loadScene(navInfo.prevScene.scene_id);
                                    // scenechange event will update controls and table highlight
                                }
                            });

                            nextButton.addEventListener('click', function() {
                                var currentScene = pannellumViewer.getScene();
                                var navInfo = getSceneNavigationInfo(currentScene);
                                if (navInfo.nextScene) {
                                    pannellumViewer.loadScene(navInfo.nextScene.scene_id);
                                    // scenechange event will update controls and table highlight
                                }
                            });

                            return customControls;
                        }

                        // =========================================================================
                        // UI CONTROL FUNCTIONS - FIXED TO UPDATE TABLE
                        // =========================================================================
                        function updateCustomControls(sceneId) {
                            var navInfo = getSceneNavigationInfo(sceneId);
                            var title   = navInfo.currentScene ? navInfo.currentScene.scene_title : '';

                            // Update scene title in navigation bar
                            var titleElement = document.querySelector('.webronic-scene-title');
                            if (titleElement) {
                                titleElement.textContent = title;
                            }

                            // Update navigation buttons state
                            var prevButton = document.querySelector('.webronic-prev-button');
                            var nextButton = document.querySelector('.webronic-next-button');

                            if (prevButton) {
                                prevButton.disabled         = !navInfo.prevScene;
                                prevButton.style.opacity    = navInfo.prevScene ? '1' : '0.3';
                                prevButton.style.cursor     = navInfo.prevScene ? 'pointer' : 'not-allowed';
                                prevButton.style.pointerEvents = navInfo.prevScene ? 'auto' : 'none';
                            }

                            if (nextButton) {
                                nextButton.disabled         = !navInfo.nextScene;
                                nextButton.style.opacity    = navInfo.nextScene ? '1' : '0.3';
                                nextButton.style.cursor     = navInfo.nextScene ? 'pointer' : 'not-allowed';
                                nextButton.style.pointerEvents = navInfo.nextScene ? 'auto' : 'none';
                            }

                            // HIGHLIGHT CURRENT SCENE IN TABLE - FIXED
                            highlightCurrentSceneInTable(sceneId);
                        }

                        // =========================================================================
                        // HOTSPOT FUNCTIONS - FIXED CARD STYLING (NO TRANSPARENCY)
                        // =========================================================================
                        function createInfoHotspotCard(hotspotDiv, args) {
                            // Remove any existing card
                            var existingCard = hotspotDiv.querySelector('.info-hotspot-card');
                            if (existingCard) {
                                existingCard.remove();
                            }

                            // Only create card for info hotspots
                            if (args.type === 'info') {
                                var card = document.createElement('div');
                                card.className = 'info-hotspot-card';
                                card.style.cssText = `
                                    position: absolute;
                                    background: #ffffff !important;
                                    border-radius: 8px;
                                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                                    padding: 15px;
                                    min-width: 200px;
                                    max-width: 200px;
                                    border: 1px solid #e0e0e0;
                                    font-family: Arial, sans-serif;
                                    
                                    left: 100%;
                                    top: 0;
                                    margin-left: 15px;
                                    transform: none;
                                    display: none;
                                    opacity: 0;
                                    transition: opacity 0.3s ease;
                                `;

                                var cardContent = '';

                                // Add pin image if available
                                if (args.pinImage) {
                                    cardContent += `<div class="hotspot-card-image"><img src="${args.pinImage}" alt="${args.text}" style="width: 100%; height: 120px; object-fit: cover; border-radius: 6px; margin-bottom: 12px;"></div>`;
                                }

                                // Add hotspot name
                                cardContent += `<div class="hotspot-card-title">${args.text}</div>`;

                                // Add pin info if available
                                if (args.pinInfo) {
                                    cardContent += `<div class="hotspot-card-info">${args.pinInfo}</div>`;
                                }

                                card.innerHTML = cardContent;
                                hotspotDiv.appendChild(card);

                                // Show/hide on hover
                                var timeout;

                                hotspotDiv.addEventListener('mouseenter', function() {
                                    clearTimeout(timeout);
                                    card.style.display = 'block';
                                    card.style.opacity = '0';

                                    setTimeout(function() {
                                        card.style.opacity = '1';
                                    }, 10);
                                });

                                hotspotDiv.addEventListener('mouseleave', function() {
                                    card.style.opacity = '0';
                                    timeout = setTimeout(function() {
                                        card.style.display = 'none';
                                    }, 300);
                                });

                                // Initially hide the card
                                card.style.display = 'none';
                            }
                        }

                        function hotspotTooltip(hotspotDiv, args) {
                            hotspotDiv.classList.add('custom-tooltip');

                            // Only create tooltip for navigation (scene) hotspots
                            if (args.type === 'scene') {
                                var tooltip = document.createElement('div');
                                tooltip.className = 'hotspot-tooltip';
                                tooltip.textContent = 'TOWARDS ' + args.text.toUpperCase();
                                hotspotDiv.appendChild(tooltip);

                                // Simple positioning
                                tooltip.style.bottom = '40px';
                                tooltip.style.left   = '50%';
                                tooltip.style.transform = 'translateX(-50%)';
                            }

                            // For info hotspots, create the card
                            if (args.type === 'info') {
                                createInfoHotspotCard(hotspotDiv, args);
                            }
                        }

                        // =========================================================================
                        // PANNELUM VIEWER INITIALIZATION
                        // =========================================================================
                        pannellumViewer = pannellum.viewer('pannellum-container', {
                            "default": {
                                "firstScene": "<?php echo esc_js($current_scene_id); ?>",
                                "sceneFadeDuration": 1000,
                                "autoLoad": true,
                                "autoRotate": false,
                                "showControls": false,
                                "mouseZoom": true,
                                "hfov": 100
                            },
                            "scenes": {
                                <?php foreach ($scenes as $index => $scene): ?>
                                "<?php echo esc_js($scene->scene_id); ?>": {
                                    "type": "equirectangular",
                                    "panorama": "<?php echo esc_js($scene->panorama_image); ?>",
                                    "yaw": <?php echo esc_js($scene->scene_yaw); ?>,
                                    "pitch": <?php echo esc_js($scene->scene_pitch); ?>,
                                    "hfov": 100,
                                    "hotSpots": [
                                        <?php
                                        $scene_hotspots = webronic_virtual_tour_get_hotspots($tour_id, $scene->scene_id);
                                        foreach ($scene_hotspots as $h_index => $hotspot):
                                            $hotspot_type = $hotspot->hotspot_type === 'scene' ? 'scene' : 'info';
                                            $direction = '';
                                            if ($hotspot_type === 'scene') {
                                                $css_classes = explode(' ', $hotspot->css_class);
                                                foreach ($css_classes as $class) {
                                                    if (in_array($class, ['up', 'right', 'left'])) {
                                                        $direction = $class;
                                                        break;
                                                    }
                                                }
                                            }
                                            
                                            // FIX 1: Skip hotspots that point to non-existent scenes
                                            if ($hotspot_type === 'scene') {
                                                $target_scene_exists = false;
                                                foreach ($scenes as $existing_scene) {
                                                    if ($existing_scene->scene_id === $hotspot->target_scene_id) {
                                                        $target_scene_exists = true;
                                                        break;
                                                    }
                                                }
                                                if (!$target_scene_exists) {
                                                    continue; // Skip this hotspot
                                                }
                                            }
                                        ?>
                                        {
                                            "pitch": <?php echo esc_js($hotspot->pitch); ?>,
                                            "yaw": <?php echo esc_js($hotspot->yaw); ?>,
                                            "type": "<?php echo esc_js($hotspot_type); ?>",
                                            "text": "<?php echo esc_js($hotspot_type === 'scene' ? webronic_virtual_tour_get_scene_title($tour_id, $hotspot->target_scene_id) : $hotspot->text); ?>",
                                            <?php if ($hotspot_type === 'scene'): ?>
                                            "sceneId": "<?php echo esc_js($hotspot->target_scene_id); ?>",
                                            <?php endif; ?>
                                            "cssClass": "custom-hotspot <?php echo esc_js($hotspot_type); ?>-hotspot <?php echo esc_js($direction); ?>",
                                            "id": "<?php echo esc_js($hotspot->hotspot_id); ?>",
                                            "createTooltipFunc": hotspotTooltip,
                                            "createTooltipArgs": {
                                                "text": "<?php echo esc_js($hotspot_type === 'scene' ? webronic_virtual_tour_get_scene_title($tour_id, $hotspot->target_scene_id) : $hotspot->text); ?>",
                                                "type": "<?php echo esc_js($hotspot_type); ?>",
                                                "pinImage": "<?php echo esc_js($hotspot->pin_image); ?>",
                                                "pinInfo": "<?php echo esc_js($hotspot->pin_info); ?>"
                                            },
                                            "clickHandlerFunc": function(hotspotDiv, args) {
                                                if (args.type === 'scene') {
                                                    pannellumViewer.loadScene(args.sceneId);
                                                    // The scenechange event will handle updating controls
                                                } else {
                                                    // Show info popup for info hotspots
                                                    var infoHtml = '<div class="info-hotspot-popup">';
                                                    if (args.pinImage) {
                                                        infoHtml += '<img src="' + args.pinImage + '" alt="Info Image" style="max-width: 100%; margin-bottom: 10px;">';
                                                    }
                                                    if (args.pinInfo) {
                                                        infoHtml += '<p>' + args.pinInfo + '</p>';
                                                    }
                                                    infoHtml += '</div>';
                                                    alert(args.text + ": " + (args.pinInfo || ''));
                                                }
                                            }
                                        }<?php if ($h_index < count($scene_hotspots) - 1) echo ','; ?>
                                        <?php endforeach; ?>
                                    ]
                                }<?php if ($index < count($scenes) - 1) echo ','; ?>
                                <?php endforeach; ?>
                            }
                        });

                        // =========================================================================
                        // POST-INITIALIZATION SETUP
                        // =========================================================================
                        pannellumViewer.on('load', function() {
                            setTimeout(function() {
                                createCustomControls();
                                // Get the ACTUAL current scene from the viewer, not from PHP variable
                                var currentSceneId = pannellumViewer.getScene();
                                updateCustomControls(currentSceneId);
                            }, 500);
                        });

                        // Scene change event handler - THIS IS THE KEY FIX FOR BOTH ISSUES
                        pannellumViewer.on('scenechange', function(sceneId) {
                            var currentSceneId = sceneId;

                            jQuery('#hotspot-scene-id').val(currentSceneId);

                            var newUrl = updateQueryStringParameter(window.location.href, 'scene', currentSceneId);
                            history.pushState(null, '', newUrl);

                            // Update custom controls with the correct scene ID - INCLUDES TABLE HIGHLIGHT
                            updateCustomControls(currentSceneId);

                            // Update scene selects
                            jQuery('#scene-select').val(currentSceneId);

                            // AJAX call to get hotspots for new scene
                            jQuery.post(webronicVirtualTour.ajaxurl, {
                                action: 'webronic_get_hotspots',
                                tour_id: <?php echo $tour_id; ?>,
                                scene_id: currentSceneId,
                                security: webronicVirtualTour.nonce
                            }, function(response) {
                                if (response.success) {
                                    jQuery('#hotspots-list').html(response.data);
                                }
                            });
                        });

                        // Add instructions element
                        var instructions = document.createElement('div');
                        instructions.className = 'hotspot-instructions';
                        instructions.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg> Right-click on the image to add Interactive Pin';
                        document.getElementById('pannellum-container').appendChild(instructions);

                        // =========================================================================
                        // EVENT HANDLERS
                        // =========================================================================
                        // Right-click handler
                        document.getElementById('pannellum-container').addEventListener('contextmenu', function(event) {
                            event.preventDefault();

                            var coords = pannellumViewer.mouseEventToCoords(event);
                            var pitch = coords[0];
                            var yaw   = coords[1];

                            // Set position values
                            $('#hotspot-pitch').val(pitch.toFixed(2));
                            $('#hotspot-yaw').val(yaw.toFixed(2));
                            $('#hotspot-scene-id').val(pannellumViewer.getScene());

                            // Reset form to show only basic fields
                            $('#hotspot-text').val('');
                            $('#hotspot-type').val('').trigger('change');
                            $('#hotspot-id').val('');
                            $('#pin-info').val('');
                            $('#pin-image').val('');

                            // Hide all conditional fields initially
                            $('#target-scene-wrap').hide();
                            $('#direction-wrap').hide();
                            $('#info-fields-wrap').hide();

                            $('#hotspot-modal-title').text('Add Interactive Pin');
                            $('#hotspot-modal').show();
                        });

                        // =========================================================================
                        // UTILITY FUNCTIONS
                        // =========================================================================
                        window.updateQueryStringParameter = function(uri, key, value) {
                            var re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
                            var separator = uri.indexOf('?') !== -1 ? "&" : "?";
                            if (uri.match(re)) {
                                return uri.replace(re, '$1' + key + "=" + value + '$2');
                            } else {
                                return uri + separator + key + "=" + value;
                            }
                        };
                    });
                </script>
                <?php else: ?>
                <?php endif; ?>
            </div>

            <div class="editor-controls">
                <?php if (!empty($scenes)): ?>
                    <div class="editor-tabs">
                        <button class="tab-button active" data-tab="scenes-tab">Scenes</button>
                        <button class="tab-button" data-tab="hotspots-tab">Hotspots</button>
                    </div>

                    <div class="tab-content active" id="scenes-tab">
                        <div class="form-group">
                            <label for="scene-select">Current Scene</label>
                            <select id="scene-select" class="regular-text">
                                <?php foreach ($scenes as $scene): ?>
                                    <option value="<?php echo esc_attr($scene->scene_id); ?>" <?php selected($current_scene_id, $scene->scene_id); ?>>
                                        <?php echo esc_html($scene->scene_title); ?>
                                        <?php if ($scene->is_default) echo ' (Default)'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <table class="wp-list-table widefat fixed striped scenes-table">
                            <thead>
                                <tr>
                                    <th>Scene Name</th>
                                    <th>Main Scene</th>
                                    <th>Interactive Pins</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($scenes as $scene): ?>
                                    <tr>
                                        <td>
                                            <strong>
                                                <a href="#" class="scene-title-link"
                                                   data-scene-id="<?php echo esc_attr($scene->scene_id); ?>"
                                                   title="Switch to <?php echo esc_attr($scene->scene_title); ?>">
                                                    <?php echo esc_html($scene->scene_title); ?>
                                                </a>
                                            </strong>
                                        </td>
                                        <td>
                                            <?php if ($scene->is_default): ?>
                                                <img src="<?php echo WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL; ?>assets/img/tick.png" alt="Default" class="status-icon">
                                            <?php else: ?>
                                                <img src="<?php echo WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL; ?>assets/img/white.png" alt="Normal" class="status-icon">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $hotspot_count = count(webronic_virtual_tour_get_hotspots($tour_id, $scene->scene_id));
                                            echo esc_html($hotspot_count) . ' hotspot' . ($hotspot_count !== 1 ? 's' : '');
                                            ?>
                                        </td>
                                        <td class="actions">
                                            <div class="action-buttons">
                                                <a href="#" class="edit-scene action-button"
                                                   data-scene-id="<?php echo esc_attr($scene->scene_id); ?>"
                                                   data-scene-title="<?php echo esc_attr($scene->scene_title); ?>"
                                                   data-panorama-image="<?php echo esc_attr($scene->panorama_image); ?>"
                                                   data-scene-yaw="<?php echo esc_attr($scene->scene_yaw); ?>"
                                                   data-scene-pitch="<?php echo esc_attr($scene->scene_pitch); ?>"
                                                   data-scene-hfov="<?php echo esc_attr($scene->scene_hfov); ?>"
                                                   data-is-default="<?php echo esc_attr($scene->is_default); ?>"
                                                   title="Edit Scene">
                                                    <img src="<?php echo WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL; ?>assets/img/edit.png" alt="Edit" class="action-icon">
                                                </a>
                                                <!-- FIX 2: Added custom delete confirmation with JS -->
                                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=webronic-virtual-tour-editor&tour_id=' . $tour_id . '&delete_scene=' . $scene->scene_id), 'delete_scene_' . $scene->scene_id); ?>"
                                                   class="action-button delete-button delete-scene-button"
                                                   data-scene-title="<?php echo esc_attr($scene->scene_title); ?>"
                                                   title="Delete Scene">
                                                    <img src="<?php echo WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL; ?>assets/img/delete.png" alt="Delete" class="action-icon">
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <script>
                            // Scene title click handler
                            jQuery(document).on('click', '.scene-title-link', function(e) {
                                e.preventDefault();

                                var sceneId    = jQuery(this).data('scene-id');
                                var sceneTitle = jQuery(this).text().trim();

                                // Update both scene selects
                                jQuery('#scene-select').val(sceneId).trigger('change');

                                // Load the scene in the viewer
                                if (typeof pannellumViewer !== 'undefined') {
                                    pannellumViewer.loadScene(sceneId);
                                }

                                // Update URL
                                var newUrl = updateQueryStringParameter(window.location.href, 'scene', sceneId);
                                history.pushState(null, '', newUrl);

                                // Update current scene highlight
                                jQuery('.scene-title-link').removeClass('current-scene');
                                jQuery(this).addClass('current-scene');

                                // If on hotspots tab, refresh hotspots list
                                if (jQuery('#hotspots-tab').hasClass('active')) {
                                    jQuery.post(webronicVirtualTour.ajaxurl, {
                                        action: 'webronic_get_hotspots',
                                        tour_id: <?php echo $tour_id; ?>,
                                        scene_id: sceneId,
                                        security: webronicVirtualTour.nonce
                                    }, function(response) {
                                        if (response.success) {
                                            jQuery('#hotspots-list').html(response.data);
                                        }
                                    });
                                }
                            });

                            // Function to highlight current scene in the table
                            function highlightCurrentScene(sceneId) {
                                jQuery('.scene-title-link').removeClass('current-scene');
                                jQuery('.scene-title-link[data-scene-id="' + sceneId + '"]').addClass('current-scene');
                            }

                            // Update highlight when scene changes in viewer
                            if (typeof pannellumViewer !== 'undefined') {
                                pannellumViewer.on('scenechange', function() {
                                    var sceneId = pannellumViewer.getScene();
                                    highlightCurrentScene(sceneId);
                                });
                            }

                            // Update highlight when scene select changes
                            jQuery('#scene-select').on('change', function() {
                                var sceneId = jQuery(this).val();
                                highlightCurrentScene(sceneId);
                            });

                            // Initial highlight on page load
                            jQuery(document).ready(function() {
                                var currentSceneId = '<?php echo esc_js($current_scene_id); ?>';
                                if (currentSceneId) {
                                    highlightCurrentScene(currentSceneId);
                                }
                            });

                            // FIX 2: Custom delete confirmation for scenes
                            jQuery(document).on('click', '.delete-scene-button', function(e) {
                                e.preventDefault();
                                
                                var sceneTitle = jQuery(this).data('scene-title');
                                var deleteUrl = jQuery(this).attr('href');
                                
                                if (confirm('WARNING: Deleting the scene "' + sceneTitle + '" will also delete ALL hotspots connected to this scene. This action cannot be undone.\n\nAre you sure you want to delete this scene?')) {
                                    window.location.href = deleteUrl;
                                }
                            });
                        </script>
                    </div>

                    <div class="tab-content" id="hotspots-tab">
                        <div id="hotspots-list">
                            <?php 
                            // FIX: Only show hotspots for current scene and filter out hotspots pointing to deleted scenes
                            $current_hotspots = $current_scene_id ? webronic_virtual_tour_get_hotspots($tour_id, $current_scene_id) : array();
                            $filtered_hotspots = array();
                            
                            foreach ($current_hotspots as $hotspot) {
                                // For scene hotspots, check if target scene exists
                                if ($hotspot->hotspot_type === 'scene' && $hotspot->target_scene_id) {
                                    $target_scene_exists = false;
                                    foreach ($scenes as $scene) {
                                        if ($scene->scene_id === $hotspot->target_scene_id) {
                                            $target_scene_exists = true;
                                            break;
                                        }
                                    }
                                    if (!$target_scene_exists) {
                                        continue; // Skip this hotspot
                                    }
                                }
                                $filtered_hotspots[] = $hotspot;
                            }
                            ?>
                            <?php if (!empty($filtered_hotspots)): ?>
                                <table class="wp-list-table widefat fixed striped hotspots-table" >
                                    <thead>
                                        <tr>
                                            <th>Hotspot Name</th>
                                            <th>Target Scene</th>
                                            <th>Position</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($filtered_hotspots as $hotspot): ?>
                                            <tr>
                                                <td><?php echo esc_html($hotspot->text); ?></td>
                                                <td>
                                                    <?php if ($hotspot->hotspot_type === 'scene' && $hotspot->target_scene_id): ?>
                                                        <?php echo esc_html(webronic_virtual_tour_get_scene_title($tour_id, $hotspot->target_scene_id)); ?>
                                                    <?php else: ?>
                                                        Information
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    Pitch: <?php echo esc_html($hotspot->pitch); ?>°<br>
                                                    Yaw: <?php echo esc_html($hotspot->yaw); ?>°
                                                </td>
                                                <td class="actions">
                                                    <div class="action-buttons">
                                                        <a href="#" class="edit-hotspot action-button"
                                                           data-hotspot-id="<?php echo esc_attr($hotspot->hotspot_id); ?>"
                                                           data-hotspot-text="<?php echo esc_attr($hotspot->text); ?>"
                                                           data-hotspot-type="<?php echo esc_attr($hotspot->hotspot_type); ?>"
                                                           data-target-scene-id="<?php echo esc_attr($hotspot->target_scene_id); ?>"
                                                           data-pin-info="<?php echo esc_attr($hotspot->pin_info); ?>"
                                                           data-pin-image="<?php echo esc_attr($hotspot->pin_image); ?>"
                                                           data-pitch="<?php echo esc_attr($hotspot->pitch); ?>"
                                                           data-yaw="<?php echo esc_attr($hotspot->yaw); ?>"
                                                           data-css-class="<?php echo esc_attr($hotspot->css_class); ?>"
                                                           title="Edit Interactive Pin">
                                                            <img src="<?php echo WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL; ?>assets/img/edit.png" alt="Edit" class="action-icon">
                                                        </a>
                                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=webronic-virtual-tour-editor&tour_id=' . $tour_id . '&delete_hotspot=' . $hotspot->hotspot_id . '&current_scene=' . $current_scene_id), 'delete_hotspot_' . $hotspot->hotspot_id); ?>"
                                                           class="action-button delete-button"
                                                           onclick="return confirm('Are you sure you want to delete this hotspot?');"
                                                           title="Delete Hotspot">
                                                            <img src="<?php echo WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL; ?>assets/img/delete.png" alt="Delete" class="action-icon">
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="no-hotspots">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="#ccc"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                                    <p>No hotspots in current scene.</p>
                                    <p>Right-click in the viewer to add a hotspot.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- No Scenes State - Full Editor Controls -->
                    <div class="no-scenes-empty-state">
                        <div class="no-scenes-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="#ccc">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
                            </svg>
                        </div>
                        <h3>No Scenes Added Yet</h3>
                        <button class="button button-primary" id="add-first-scene" style="background-color: #4888E8; color: white; padding: 1px 24px; border: none; border-radius: 50px; font-size: 16px; display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; margin: 20px auto;">
                            + Add Your First Scene
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Also update the scene select in hotspots tab when scene changes from viewer or scenes tab
        jQuery('#scene-select').on('change', function() {
            var sceneId = jQuery(this).val();

            // Also update hotspots list if we're on hotspots tab
            if (jQuery('#hotspots-tab').hasClass('active')) {
                jQuery.post(webronicVirtualTour.ajaxurl, {
                    action: 'webronic_get_hotspots',
                    tour_id: <?php echo $tour_id; ?>,
                    scene_id: sceneId,
                    security: webronicVirtualTour.nonce
                }, function(response) {
                    if (response.success) {
                        jQuery('#hotspots-list').html(response.data);
                    }
                });
            }
        });

        // Tab switching - load hotspots when switching to hotspots tab
        jQuery('.tab-button').on('click', function() {
            var tab = jQuery(this).data('tab');

            jQuery('.tab-button').removeClass('active');
            jQuery(this).addClass('active');
            jQuery('.tab-content').removeClass('active');
            jQuery('#' + tab).addClass('active');

            if (tab === 'hotspots-tab') {
                var sceneId = jQuery('#scene-select').val();

                jQuery.post(webronicVirtualTour.ajaxurl, {
                    action: 'webronic_get_hotspots',
                    tour_id: <?php echo $tour_id; ?>,
                    scene_id: sceneId,
                    security: webronicVirtualTour.nonce
                }, function(response) {
                    if (response.success) {
                        jQuery('#hotspots-list').html(response.data);
                    }
                });
            }
        });

        (function() {
            // Scene name validation
            const sceneTitleInput = document.getElementById('scene-title');
            const sceneNameError = document.getElementById('scene-name-error');
            const scenes = <?php echo json_encode(array_values($scenes)); ?>;

            function checkSceneNameExists(sceneName, currentSceneId) {
                return scenes.some(scene => 
                    scene.scene_title === sceneName && scene.scene_id !== currentSceneId
                );
            }

            sceneTitleInput.addEventListener('blur', function() {
                const sceneName = this.value.trim();
                const currentSceneId = document.getElementById('scene-id').value;
                
                if (sceneName && checkSceneNameExists(sceneName, currentSceneId)) {
                    sceneNameError.style.display = 'block';
                } else {
                    sceneNameError.style.display = 'none';
                }
            });

            sceneTitleInput.addEventListener('input', function() {
                sceneNameError.style.display = 'none';
            });

            // Panorama image upload and preview functionality
            const panoramaImageInput   = document.getElementById('panorama-image');
            const panoramaImagePreview = document.getElementById('panorama-image-preview');
            const removePanoramaImageBtn = document.getElementById('remove-panorama-image');
            const panoramaActionButtons  = document.getElementById('panorama-action-buttons');

            // Update panorama preview when image URL changes
            function updatePanoramaImagePreview() {
                const imageUrl = panoramaImageInput.value;
                if (imageUrl) {
                    // Show uploaded image
                    panoramaImagePreview.innerHTML = '';
                    panoramaImagePreview.style.backgroundImage    = `url(${imageUrl})`;
                    panoramaImagePreview.style.backgroundSize     = 'cover';
                    panoramaImagePreview.style.backgroundPosition = 'center';
                    panoramaImagePreview.style.backgroundRepeat   = 'no-repeat';

                    // Show action buttons
                    panoramaActionButtons.style.display = 'flex';
                } else {
                    // Show default upload state
                    panoramaImagePreview.style.backgroundImage = 'none';
                    panoramaImagePreview.innerHTML = `
                        <img src="<?php echo WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL; ?>assets/img/upload2.png" alt="Upload Image" style="width: 50px; height: 50px; opacity: 0.6; margin-bottom: 10px;" id="panorama-default-upload-icon">
                        <span style="color: #999; margin-bottom: 5px; font-size: 14px;">Drag & Drop your files here</span>
                        <span style="color: #999; font-size: 12px; margin-bottom: 15px;">Supported Formats: .png, .jpg</span>
                        <button type="button" class="button"
                                style="background: #2271B1; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; width: 110px; height: 37px; display: flex; align-items: center; justify-content: center;"
                                data-target="#panorama-image">
                            Choose File
                        </button>
                    `;

                    // Hide action buttons
                    panoramaActionButtons.style.display = 'none';

                    // Re-attach click event to the new upload button
                    const newUploadButton = panoramaImagePreview.querySelector('.media-upload-button');
                    if (newUploadButton) {
                        newUploadButton.addEventListener('click', openPanoramaMediaUploader);
                    }
                }
            }

            // Open panorama media uploader
            function openPanoramaMediaUploader(e) {
                if (e) e.preventDefault();

                const mediaUploader = wp.media({
                    title: 'Select Panorama Image',
                    button: { text: 'Use this image' },
                    multiple: false
                });

                mediaUploader.on('select', function() {
                    const attachment = mediaUploader.state().get('selection').first().toJSON();
                    panoramaImageInput.value = attachment.url;
                    updatePanoramaImagePreview();
                });

                mediaUploader.open();
            }

            // Remove panorama image
            removePanoramaImageBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                panoramaImageInput.value = '';
                updatePanoramaImagePreview();
            });

            // Click on preview area to upload/reupload
            panoramaImagePreview.addEventListener('click', function(e) {
                openPanoramaMediaUploader(e);
            });

            // Initialize panorama preview
            updatePanoramaImagePreview();

            // Pin image upload and preview functionality
            const typeSelect      = document.getElementById('hotspot-type');
            const targetWrap      = document.getElementById('target-scene-wrap');
            const directionWrap   = document.getElementById('direction-wrap');
            const infoFieldsWrap  = document.getElementById('info-fields-wrap');
            const targetSelect    = document.getElementById('target-scene-id');
            const directionError  = document.getElementById('direction-error');

            const pinImageInput   = document.getElementById('pin-image');
            const pinImagePreview = document.getElementById('pin-image-preview');
            const removePinImageBtn = document.getElementById('remove-pin-image');
            const pinActionButtons = document.getElementById('pin-action-buttons');

            // Update pin image preview when image URL changes
            function updatePinImagePreview() {
                const imageUrl = pinImageInput.value;
                if (imageUrl) {
                    pinImagePreview.innerHTML = '';
                    pinImagePreview.style.backgroundImage    = `url(${imageUrl})`;
                    pinImagePreview.style.backgroundSize     = 'cover';
                    pinImagePreview.style.backgroundPosition = 'center';
                    pinImagePreview.style.backgroundRepeat   = 'no-repeat';
                    
                    // Show delete button when image is present
                    pinActionButtons.style.display = 'flex';
                } else {
                    pinImagePreview.style.backgroundImage = 'none';
                    pinImagePreview.innerHTML = `
                        <img src="<?php echo WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL; ?>assets/img/upload.png" alt="Upload Image" style="width: 50px; height: 50px; opacity: 0.6;" id="default-upload-icon">
                        <span style="color: #999; margin-left: 10px;">Click to upload image</span>
                    `;
                    
                    // Hide delete button when no image
                    pinActionButtons.style.display = 'none';
                }
            }

            // Open pin media uploader
            function openPinMediaUploader(e) {
                if (e) e.preventDefault();

                const mediaUploader = wp.media({
                    title: 'Select Pin Image',
                    button: { text: 'Use this image' },
                    multiple: false
                });

                mediaUploader.on('select', function() {
                    const attachment = mediaUploader.state().get('selection').first().toJSON();
                    pinImageInput.value = attachment.url;
                    updatePinImagePreview();
                });

                mediaUploader.open();
            }

            // Remove pin image
            removePinImageBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                pinImageInput.value = '';
                updatePinImagePreview();
            });

            // Click on preview area to upload/reupload
            pinImagePreview.addEventListener('click', function(e) {
                openPinMediaUploader(e);
            });

            // Initialize pin image preview
            updatePinImagePreview();

            // Show/Hide fields based on Pin Type
            function syncFieldVisibility() {
                const isNav  = typeSelect.value === 'scene';
                const isInfo = typeSelect.value === 'info';

                // Toggle visibility of main sections
                targetWrap.style.display     = isNav  ? 'block' : 'none';
                directionWrap.style.display  = isNav  ? 'block' : 'none';
                infoFieldsWrap.style.display = isInfo ? 'block' : 'none';

                // For info hotspots, ALWAYS show both pin info and pin image sections
                if (isInfo) {
                    document.querySelector('.pin-info-section').style.display  = 'block';
                    document.querySelector('.pin-image-section').style.display = 'block';
                }

                // Toggle required
                if (isNav) {
                    targetSelect.setAttribute('required', 'required');
                } else {
                    targetSelect.removeAttribute('required');
                    targetSelect.value = '';
                    clearDirection();
                }
            }

            // Helper: clear direction radios
            function clearDirection() {
                document.querySelectorAll('input[name="hotspot_direction"]').forEach(r => {
                    r.checked = false;
                    r.removeAttribute('required');
                });
                directionError.style.display = 'none';
            }

            // Helper: set direction required
            function setDirectionRequired() {
                document.querySelectorAll('input[name="hotspot_direction"]').forEach(r => {
                    r.setAttribute('required', 'required');
                });
            }

            typeSelect.addEventListener('change', syncFieldVisibility);

            // Direction validation
            document.querySelectorAll('input[name="hotspot_direction"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    directionError.style.display = 'none';
                });
            });

            // init on load
            syncFieldVisibility();

            // Settings functionality
            const settingsIcon  = document.getElementById('settings-icon');
            const settingsPopup = document.getElementById('settings-popup');

            // Settings icon click handler
            settingsIcon?.addEventListener('click', function() {
                settingsPopup.style.display = 'block';
            });

            // Clear scene form function
            function clearSceneForm() {
                document.getElementById('scene-id').value      = '<?php echo esc_js(uniqid('scene_')); ?>';
                document.getElementById('scene-title').value   = '';
                document.getElementById('panorama-image').value= '';
                
                // Auto-check main scene for first scene
                const isFirstScene = <?php echo empty($scenes) ? 'true' : 'false'; ?>;
                document.getElementById('is-default-scene').checked = isFirstScene;
                
                updatePanoramaImagePreview();
                document.getElementById('scene-modal-title').textContent = 'Add New Scene';
                document.querySelector('#scene-form button[type="submit"]').textContent = 'Add Scene';
                sceneNameError.style.display = 'none';
            }

            // Clear hotspot form function
            function clearHotspotForm() {
                document.getElementById('hotspot-id').value    = '';
                document.getElementById('hotspot-text').value  = '';
                document.getElementById('hotspot-type').value  = '';
                document.getElementById('target-scene-id').value = '';
                document.getElementById('pin-info').value      = '';
                document.getElementById('pin-image').value     = '';
                document.getElementById('hotspot-pitch').value = '';
                document.getElementById('hotspot-yaw').value   = '';
                clearDirection();
                updatePinImagePreview();

                // ALWAYS show pin info and image sections for info hotspots
                document.querySelector('.pin-info-section').style.display  = 'block';
                document.querySelector('.pin-image-section').style.display = 'block';

                syncFieldVisibility();
                document.getElementById('hotspot-modal-title').textContent = 'Add Interactive Pin';
                document.querySelector('#hotspot-form button[type="submit"]').textContent = 'Add';
            }

            // Close modal handlers
            document.querySelectorAll('.cancel-modal').forEach(button => {
                button.addEventListener('click', function() {
                    document.querySelectorAll('.webronic-modal').forEach(modal => {
                        modal.style.display = 'none';
                    });
                    // Clear forms when modal is closed
                    clearSceneForm();
                    clearHotspotForm();
                });
            });

            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                document.querySelectorAll('.webronic-modal').forEach(modal => {
                    if (event.target === modal) {
                        modal.style.display = 'none';
                        // Clear forms when modal is closed
                        clearSceneForm();
                        clearHotspotForm();
                    }
                });
            });

            document.addEventListener('DOMContentLoaded', function() {
                const copyButton    = document.getElementById('copy-shortcode');
                const shortcodeInput= document.getElementById('tour-shortcode');
                const copyMessage   = document.getElementById('copy-message');

                copyButton.addEventListener('click', function() {
                    // Select the text
                    shortcodeInput.select();
                    shortcodeInput.setSelectionRange(0, 99999); // For mobile devices

                    // Copy to clipboard
                    try {
                        document.execCommand('copy');

                        // Show success message
                        copyMessage.style.display = 'block';

                        // Hide message after 2 seconds
                        setTimeout(function() {
                            copyMessage.style.display = 'none';
                        }, 2000);
                    } catch (err) {
                        console.error('Failed to copy text: ', err);
                    }
                });
            });

            // Add new scene button
            document.getElementById('add-new-scene')?.addEventListener('click', function() {
                clearSceneForm();
                document.getElementById('scene-modal').style.display = 'block';
            });

            // Add first scene button
            document.getElementById('add-first-scene')?.addEventListener('click', function() {
                clearSceneForm();
                document.getElementById('scene-modal').style.display = 'block';
            });

            // Edit scene functionality
            document.querySelectorAll('.edit-scene').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();

                    const sceneId       = this.getAttribute('data-scene-id');
                    const sceneTitle    = this.getAttribute('data-scene-title');
                    const panoramaImage = this.getAttribute('data-panorama-image');
                    const isDefault     = this.getAttribute('data-is-default') === '1';

                    // Set form values
                    document.getElementById('scene-id').value    = sceneId;
                    document.getElementById('scene-title').value = sceneTitle;
                    document.getElementById('panorama-image').value = panoramaImage;
                    document.getElementById('is-default-scene').checked = isDefault;

                    // Update image preview
                    updatePanoramaImagePreview();

                    document.getElementById('scene-modal-title').textContent = 'Edit Scene';
                    document.querySelector('#scene-form button[type="submit"]').textContent = 'Update Scene';
                    document.getElementById('scene-modal').style.display = 'block';
                });
            });

            // Edit hotspot functionality
            document.querySelectorAll('.edit-hotspot').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();

                    const hotspotId     = this.getAttribute('data-hotspot-id');
                    const hotspotText   = this.getAttribute('data-hotspot-text');
                    const hotspotType   = this.getAttribute('data-hotspot-type');
                    const targetSceneId = this.getAttribute('data-target-scene-id');
                    const pinInfo       = this.getAttribute('data-pin-info');
                    const pinImage      = this.getAttribute('data-pin-image');
                    const pitch         = this.getAttribute('data-pitch');
                    const yaw           = this.getAttribute('data-yaw');

                    // Set form values
                    document.getElementById('hotspot-id').value     = hotspotId;
                    document.getElementById('hotspot-text').value   = hotspotText;
                    document.getElementById('hotspot-type').value   = hotspotType;
                    document.getElementById('target-scene-id').value= targetSceneId;
                    document.getElementById('pin-info').value       = pinInfo || '';
                    document.getElementById('pin-image').value      = pinImage || '';
                    document.getElementById('hotspot-pitch').value  = pitch;
                    document.getElementById('hotspot-yaw').value    = yaw;

                    // Update image preview
                    updatePinImagePreview();

                    // ALWAYS show pin info and pin image sections for info hotspots in edit mode
                    const pinInfoSection  = document.querySelector('.pin-info-section');
                    const pinImageSection = document.querySelector('.pin-image-section');

                    if (hotspotType === 'info') {
                        pinInfoSection.style.display  = 'block';
                        pinImageSection.style.display = 'block';
                    } else {
                        pinInfoSection.style.display  = 'none';
                        pinImageSection.style.display = 'none';
                    }

                    // Trigger visibility update for main sections
                    syncFieldVisibility();

                    // Set direction if it's a scene hotspot
                    if (hotspotType === 'scene') {
                        const cssClass  = this.getAttribute('data-css-class');
                        const direction = cssClass.includes('up') ? 'up' :
                                          cssClass.includes('right') ? 'right' :
                                          cssClass.includes('left') ? 'left' : 'up';

                        const radio = document.querySelector(`input[name="hotspot_direction"][value="${direction}"]`);
                        if (radio) radio.checked = true;
                        setDirectionRequired();
                    }

                    document.getElementById('hotspot-modal-title').textContent = 'Edit Interactive Pin';
                    document.querySelector('#hotspot-form button[type="submit"]').textContent = 'Update';
                    document.getElementById('hotspot-modal').style.display = 'block';
                });
            });

            // Hotspot form validation
            document.getElementById('hotspot-form').addEventListener('submit', function(e) {
                const hotspotType = typeSelect.value;
                
                // Validate direction for scene hotspots
                if (hotspotType === 'scene') {
                    const directionSelected = document.querySelector('input[name="hotspot_direction"]:checked');
                    if (!directionSelected) {
                        e.preventDefault();
                        directionError.style.display = 'block';
                        return false;
                    }
                }
                
                return true;
            });

            // Auto-check main scene for first scene
            const isFirstScene = <?php echo empty($scenes) ? 'true' : 'false'; ?>;
            if (isFirstScene) {
                document.addEventListener('DOMContentLoaded', function() {
                    document.getElementById('is-default-scene').checked = true;
                    // Optionally disable the checkbox to make it clear it's automatic
                    // document.getElementById('is-default-scene').disabled = true;
                });
            }

        })();
    </script>

    <?php
}