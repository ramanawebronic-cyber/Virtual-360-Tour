<?php
/**
 * WEBRONIC 360 Virtual Tour - Shortcode Handler (External JS Version)
 */

if (!defined('ABSPATH')) exit;

add_shortcode('webronic_virtual_tour', 'webronic_virtual_tour_shortcode');

function webronic_virtual_tour_shortcode($atts) {
    $atts = shortcode_atts(array('id' => ''), $atts, 'webronic_virtual_tour');

    if (empty($atts['id'])) return '';

    // Check if we're in Elementor editor mode
    if (defined('ELEMENTOR_VERSION') && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
        return '<div class="webronic-elementor-preview" style="padding: 40px; text-align: center; background: #f8f9fa; border: 2px dashed #ccc; border-radius: 8px;">
                <h3>🌐 WEBRONIC 360 Virtual Tour</h3>
                <p><strong>Tour ID:</strong> ' . esc_html($atts['id']) . '</p>
                <p style="font-size: 14px; color: #666;">The virtual tour will appear here on the live website.</p>
                </div>';
    }

    $tour_id       = intval($atts['id']);
    $tour          = webronic_virtual_tour_get_tour($tour_id);
    $default_scene = webronic_virtual_tour_get_default_scene($tour_id);
    $scenes        = webronic_virtual_tour_get_scenes($tour_id);

    // If tour not found
    if (!$tour) {
        return '<p>Tour not found.</p>';
    }

    // If no scenes found
    if (empty($scenes)) {
        return '<p>No scenes found in this tour.</p>';
    }

    // Determine current/first scene safely
    $current_scene_id = isset($_GET['scene'])
        ? sanitize_text_field($_GET['scene'])
        : ($default_scene ? $default_scene->scene_id : (isset($scenes[0]->scene_id) ? $scenes[0]->scene_id : ''));

    // Prepare scenes data for JavaScript
    $scenes_data = array();
    $scenes_array = array();
    
    foreach ($scenes as $scene) {
        $scene_hotspots = webronic_virtual_tour_get_hotspots($tour_id, $scene->scene_id);
        $hotspots_data = array();
        
        foreach ($scene_hotspots as $hotspot) {
            $hotspot_type = $hotspot->hotspot_type === 'scene' ? 'scene' : 'info';
            $target_scene_name = ($hotspot_type === 'scene' && $hotspot->target_scene_id)
                ? webronic_virtual_tour_get_scene_title($tour_id, $hotspot->target_scene_id)
                : '';
            $direction = '';
            
            if ($hotspot_type === 'scene' && !empty($hotspot->css_class)) {
                $css_classes = explode(' ', $hotspot->css_class);
                foreach ($css_classes as $class) {
                    if (in_array($class, array('up', 'right', 'left'), true)) { 
                        $direction = $class; 
                        break; 
                    }
                }
            }
            
            $hotspot_data = array(
                "pitch" => (float) $hotspot->pitch,
                "yaw" => (float) $hotspot->yaw,
                "type" => $hotspot_type,
                "text" => $hotspot_type === 'scene' ? $target_scene_name : $hotspot->text,
                "cssClass" => "custom-hotspot {$hotspot_type}-hotspot {$direction}",
                "id" => $hotspot->hotspot_id,
                "createTooltipArgs" => array(
                    "text" => $hotspot_type === 'scene' ? $target_scene_name : $hotspot->text,
                    "type" => $hotspot_type,
                    "pinImage" => $hotspot->pin_image,
                    "pinInfo" => $hotspot->pin_info
                )
            );
            
            if ($hotspot_type === 'scene') {
                $hotspot_data["sceneId"] = $hotspot->target_scene_id;
            }
            
            $hotspots_data[] = $hotspot_data;
        }
        
        $scenes_data[$scene->scene_id] = array(
            "type" => "equirectangular",
            "panorama" => $scene->panorama_image,
            "yaw" => (int) $scene->scene_yaw,
            "pitch" => (int) $scene->scene_pitch,
            "hfov" => 100,
            "hotSpots" => $hotspots_data
        );
        
        // Store scene info for navigation
        $scenes_array[] = array(
            'scene_id' => $scene->scene_id,
            'scene_title' => $scene->scene_title
        );
    }

    // Only enqueue assets on frontend, not in Elementor editor
    if (!is_admin() && !(defined('ELEMENTOR_VERSION') && \Elementor\Plugin::$instance->preview->is_preview_mode())) {
        wp_enqueue_script('jquery');
        wp_enqueue_script('pannellum', 'https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.js', array(), '2.5.6', true);
        wp_enqueue_style('pannellum-css', 'https://cdn.jsdelivr.net/npm/pannellum@2.5.6/build/pannellum.css', array(), '2.5.6');
        
        // Enqueue your external viewer.js
        wp_enqueue_script('webronic-virtual-tour-viewer', 
            WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL . 'assets/js/viewer.js', 
            array('jquery', 'pannellum'), 
            WEBRONIC_VIRTUAL_TOUR_VERSION, 
            true
        );
        
        wp_enqueue_style('webronic-virtual-tour-viewer', 
            WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL . 'assets/css/viewer.css', 
            array(), 
            WEBRONIC_VIRTUAL_TOUR_VERSION
        );

        // Localize script data
        wp_localize_script('webronic-virtual-tour-viewer', 'webronicConfig', array(
            'currentSceneId' => $current_scene_id,
            'scenesData' => $scenes_data,
            'scenes' => $scenes_array,
            'defaultSceneId' => $default_scene ? $default_scene->scene_id : (isset($scenes[0]->scene_id) ? $scenes[0]->scene_id : '')
        ));
    }

    $img_base = trailingslashit(WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL . 'assets/img');

    ob_start();
    ?>
    <div class="webronic-virtual-tour-container alignfull" oncontextmenu="return false;">
        <!-- Top controls - Toggle full screen button -->
        <button class="webronic-top-icon webronic-fullscreen-toggle" title="Toggle full screen" aria-label="Toggle full screen">
            <div class="webronic-icon-content">
                <img src="<?php echo esc_url($img_base . 'expand.png'); ?>" alt="Full screen" class="webronic-fullscreen-icon">
                <span class="webronic-icon-text">Full View</span>
            </div>
        </button>

        <!-- Back and Home buttons as separate cards -->
        <div class="webronic-tl-cluster">
            <!-- Back Card -->
            <div class="webronic-card webronic-card--navigation webronic-card--back">
                <button class="webronic-nav-btn webronic-back-btn" title="Go back to previous scene" aria-label="Go back to previous scene" disabled>
                    <div class="webronic-nav-btn-content">
                        <img src="<?php echo esc_url($img_base . 'back.png'); ?>" alt="Back">
                        <span>Back</span>
                    </div>
                </button>
            </div>

            <!-- Home Card with blue background -->
            <div class="webronic-card webronic-card--navigation webronic-card--home">
                <button class="webronic-nav-btn webronic-home-btn" title="Go to home scene" aria-label="Go to home scene">
                    <div class="webronic-nav-btn-content">
                        <img src="<?php echo esc_url($img_base . 'home.png'); ?>" alt="Home">
                        <span>Home</span>
                    </div>
                </button>
            </div>
        </div>

        <!-- Viewer -->
        <div id="pannellum-container" oncontextmenu="return false;"></div>

        <!-- Bottom-left control cluster -->
        <div class="webronic-bl-cluster">
            <!-- Card 1: small info card -->
            <div class="webronic-card webronic-card--small" aria-live="polite">
                <!-- First line: 4box and numbers -->
                <div class="webronic-card-top">
                    <div class="webronic-icon-bg">
                        <img src="<?php echo esc_url($img_base . '4box.png'); ?>" alt="" />
                    </div>
                    <div class="webronic-card-numbers">
                        <span class="webronic-scene-index">1</span>
                        <span>/</span>
                        <span class="webronic-scene-total"><?php echo count($scenes); ?></span>
                    </div>
                </div>
                
                <!-- Second line: prev/next buttons -->
                <div class="webronic-card-bottom">
                    <div class="webronic-arrows">
                        <button class="webronic-arrow-btn webronic-prev" title="Previous scene" aria-label="Previous scene">
                            <img src="<?php echo esc_url($img_base . 'prev1.png'); ?>" alt="Previous" />
                        </button>
                        <button class="webronic-arrow-btn webronic-next" title="Next scene" aria-label="Next scene">
                            <img src="<?php echo esc_url($img_base . 'next1.png'); ?>" alt="Next" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Card 2: scene title - Extended width -->
            <div class="webronic-card webronic-card--title">
                <div class="webronic-scene-title">
                    <?php
                        $initial_title = '';
                        if ($current_scene_id) {
                            foreach ($scenes as $s) {
                                if ($s->scene_id === $current_scene_id) { 
                                    $initial_title = $s->scene_title; 
                                    break; 
                                }
                            }
                        }
                        echo esc_html($initial_title);
                    ?>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        /* Disable right-click context menu on the entire tour container */
        .webronic-virtual-tour-container {
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            -khtml-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        
        /* Specifically target Pannellum elements to hide version info */
        .pnlm-about-msg {
            display: none !important;
        }
        
        /* Hide Pannellum logo and controls if they appear */
        .pnlm-logo {
            display: none !important;
        }
    </style>
    
    <script>
        // Prevent right-click context menu
        document.addEventListener('DOMContentLoaded', function() {
            var tourContainer = document.querySelector('.webronic-virtual-tour-container');
            if (tourContainer) {
                tourContainer.addEventListener('contextmenu', function(e) {
                    e.preventDefault();
                    return false;
                });
            }
            
            // Also prevent right-click on the Pannellum container specifically
            var pannellumContainer = document.getElementById('pannellum-container');
            if (pannellumContainer) {
                pannellumContainer.addEventListener('contextmenu', function(e) {
                    e.preventDefault();
                    return false;
                });
            }
        });
    </script>
    <?php
    return ob_get_clean();
}