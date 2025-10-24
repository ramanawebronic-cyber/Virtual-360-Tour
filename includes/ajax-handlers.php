<?php
add_action('wp_ajax_webronic_save_tour', 'webronic_virtual_tour_save_tour_ajax_handler');
add_action('wp_ajax_webronic_update_tour', 'webronic_virtual_tour_update_tour_ajax_handler');
add_action('wp_ajax_webronic_get_hotspots', 'webronic_virtual_tour_get_hotspots_ajax_handler');

function webronic_virtual_tour_save_tour_ajax_handler() {
    global $wpdb;

    check_ajax_referer('webronic_virtual_tour_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have sufficient permissions');
    }

    if (empty($_POST['tour_title'])) {
        wp_send_json_error('Tour title is required');
    }

    $title = sanitize_text_field($_POST['tour_title']);
    $result = webronic_virtual_tour_insert_tour($title);

    if ($result === false) {
        wp_send_json_error('Failed to create tour. Database error: ' . $wpdb->last_error);
    }

    wp_send_json_success(array(
        'message' => 'Tour created successfully!',
        'shortcode' => $result['shortcode'],
        'id' => $result['id']
    ));
}

function webronic_virtual_tour_update_tour_ajax_handler() {
    global $wpdb;

    check_ajax_referer('webronic_virtual_tour_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have sufficient permissions');
    }

    if (empty($_POST['tour_id']) || empty($_POST['tour_title'])) {
        wp_send_json_error('Tour ID and title are required');
    }

    $result = webronic_virtual_tour_update_tour(
        intval($_POST['tour_id']),
        sanitize_text_field($_POST['tour_title'])
    );

    if ($result === false) {
        wp_send_json_error('Failed to update tour. Database error: ' . $wpdb->last_error);
    }

    wp_send_json_success(array(
        'message' => 'Tour updated successfully!'
    ));
}

function webronic_virtual_tour_get_hotspots_ajax_handler() {
    global $wpdb;
    
    check_ajax_referer('webronic_virtual_tour_editor_nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have sufficient permissions');
    }

    if (empty($_POST['tour_id']) || empty($_POST['scene_id'])) {
        wp_send_json_error('Tour ID and scene ID are required');
    }

    $tour_id = intval($_POST['tour_id']);
    $scene_id = sanitize_text_field($_POST['scene_id']);
    $hotspots = webronic_virtual_tour_get_hotspots($tour_id, $scene_id);

    ob_start();
    if (!empty($hotspots)): ?>
        <table class="wp-list-table widefat fixed striped hotspots-table">
            <thead>
                <tr>
                    <th>Hotspot Name</th>
                    <th>Target Scene</th>
                    <th>Position</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($hotspots as $hotspot): ?>
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
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=webronic-virtual-tour-editor&tour_id=' . $tour_id . '&delete_hotspot=' . $hotspot->hotspot_id . '&current_scene=' . $scene_id), 'delete_hotspot_' . $hotspot->hotspot_id); ?>" 
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
    <?php endif;

    $output = ob_get_clean();
    wp_send_json_success($output);
}