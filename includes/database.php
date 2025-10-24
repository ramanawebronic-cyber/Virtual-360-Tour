<?php
function webronic_virtual_tour_create_tables() {
    global $wpdb;
    
    $tours_table = $wpdb->prefix . 'webronic_virtual_tours';
    $scenes_table = $wpdb->prefix . 'webronic_virtual_tour_scenes';
    $hotspots_table = $wpdb->prefix . 'webronic_virtual_tour_hotspots';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql_tours = "CREATE TABLE $tours_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        tour_title varchar(255) NOT NULL,
        shortcode varchar(255) NOT NULL,
        created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    $sql_scenes = "CREATE TABLE $scenes_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        tour_id mediumint(9) NOT NULL,
        scene_id varchar(100) NOT NULL,
        scene_title varchar(255) NOT NULL,
        panorama_image varchar(255) NOT NULL,
        scene_yaw int(11) DEFAULT 0,
        scene_pitch int(11) DEFAULT 0,
        scene_hfov int(11) DEFAULT 100,
        is_default tinyint(1) DEFAULT 0,
        created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id),
        KEY tour_id (tour_id)
    ) $charset_collate;";
    
    $sql_hotspots = "CREATE TABLE $hotspots_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        tour_id mediumint(9) NOT NULL,
        scene_id varchar(100) NOT NULL,
        hotspot_id varchar(100) NOT NULL,
        hotspot_type varchar(50) NOT NULL DEFAULT 'scene',
        pitch decimal(10,6) NOT NULL,
        yaw decimal(10,6) NOT NULL,
        text varchar(255) DEFAULT NULL,
        pin_info text DEFAULT NULL,
        pin_image varchar(255) DEFAULT NULL,
        target_scene_id varchar(100) DEFAULT NULL,
        css_class varchar(100) DEFAULT NULL,
        created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id),
        KEY tour_id (tour_id),
        KEY scene_id (scene_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_tours);
    dbDelta($sql_scenes);
    dbDelta($sql_hotspots);
    
    add_option('webronic_virtual_tour_db_version', '1.0');
}

function webronic_virtual_tour_insert_tour($title) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'webronic_virtual_tours';
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'tour_title' => sanitize_text_field($title),
            'shortcode' => '',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ),
        array('%s', '%s', '%s', '%s')
    );
    
    if ($result === false) return false;
    
    $tour_id = $wpdb->insert_id;
    $shortcode = '[webronic_virtual_tour id="' . $tour_id . '"]';
    
    $wpdb->update(
        $table_name,
        array('shortcode' => $shortcode),
        array('id' => $tour_id),
        array('%s'),
        array('%d')
    );
    
    return array('shortcode' => $shortcode, 'id' => $tour_id);
}

function webronic_virtual_tour_get_tours() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'webronic_virtual_tours';
    // Changed from DESC to ASC to show oldest tours first
    return $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at ASC");
}

function webronic_virtual_tour_get_tour($tour_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'webronic_virtual_tours';
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $tour_id
    ));
}

function webronic_virtual_tour_update_tour($tour_id, $title) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'webronic_virtual_tours';
    
    return $wpdb->update(
        $table_name,
        array(
            'tour_title' => sanitize_text_field($title),
            'updated_at' => current_time('mysql')
        ),
        array('id' => $tour_id),
        array('%s', '%s'),
        array('%d')
    );
}

function webronic_virtual_tour_delete_tour($tour_id) {
    global $wpdb;
    $tours_table = $wpdb->prefix . 'webronic_virtual_tours';
    $scenes_table = $wpdb->prefix . 'webronic_virtual_tour_scenes';
    $hotspots_table = $wpdb->prefix . 'webronic_virtual_tour_hotspots';
    
    // First delete all hotspots for this tour
    $wpdb->delete(
        $hotspots_table,
        array('tour_id' => $tour_id),
        array('%d')
    );
    
    // Then delete all scenes
    $wpdb->delete(
        $scenes_table,
        array('tour_id' => $tour_id),
        array('%d')
    );
    
    // Finally delete the tour
    return $wpdb->delete(
        $tours_table,
        array('id' => $tour_id),
        array('%d')
    );
}

function webronic_virtual_tour_get_scenes($tour_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'webronic_virtual_tour_scenes';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE tour_id = %d ORDER BY created_at ASC",
        $tour_id
    ));
}

function webronic_virtual_tour_get_scene($tour_id, $scene_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'webronic_virtual_tour_scenes';
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE tour_id = %d AND scene_id = %s",
        $tour_id,
        $scene_id
    ));
}

function webronic_virtual_tour_save_scene($tour_id, $scene_data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'webronic_virtual_tour_scenes';
    
    // Prepare the default scene status
    $is_default = isset($scene_data['is_default']) && $scene_data['is_default'] ? 1 : 0;
    
    // If this scene is being set as default, first unset any existing default
    if ($is_default) {
        $wpdb->update(
            $table_name,
            array('is_default' => 0),
            array('tour_id' => $tour_id, 'is_default' => 1),
            array('%d'),
            array('%d', '%d')
        );
    }
    
    // Check if scene already exists
    $existing_scene = webronic_virtual_tour_get_scene($tour_id, $scene_data['scene_id']);
    
    if ($existing_scene) {
        // Update existing scene
        return $wpdb->update(
            $table_name,
            array(
                'scene_title' => sanitize_text_field($scene_data['scene_title']),
                'panorama_image' => esc_url_raw($scene_data['panorama_image']),
                'scene_yaw' => intval($scene_data['scene_yaw']),
                'scene_pitch' => intval($scene_data['scene_pitch']),
                'scene_hfov' => intval($scene_data['scene_hfov']),
                'is_default' => $is_default,
                'updated_at' => current_time('mysql')
            ),
            array(
                'tour_id' => $tour_id,
                'scene_id' => $scene_data['scene_id']
            ),
            array('%s', '%s', '%d', '%d', '%d', '%d', '%s'),
            array('%d', '%s')
        );
    } else {
        // Insert new scene
        return $wpdb->insert(
            $table_name,
            array(
                'tour_id' => $tour_id,
                'scene_id' => sanitize_text_field($scene_data['scene_id']),
                'scene_title' => sanitize_text_field($scene_data['scene_title']),
                'panorama_image' => esc_url_raw($scene_data['panorama_image']),
                'scene_yaw' => intval($scene_data['scene_yaw']),
                'scene_pitch' => intval($scene_data['scene_pitch']),
                'scene_hfov' => intval($scene_data['scene_hfov']),
                'is_default' => $is_default,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s')
        );
    }
}

function webronic_virtual_tour_delete_scene($tour_id, $scene_id) {
    global $wpdb;
    $scenes_table = $wpdb->prefix . 'webronic_virtual_tour_scenes';
    $hotspots_table = $wpdb->prefix . 'webronic_virtual_tour_hotspots';
    
    // First delete all hotspots for this scene
    $wpdb->delete(
        $hotspots_table,
        array(
            'tour_id' => $tour_id,
            'scene_id' => $scene_id
        ),
        array('%d', '%s')
    );
    
    // FIX 1: Also delete hotspots that point TO this scene (navigation hotspots)
    $wpdb->delete(
        $hotspots_table,
        array(
            'tour_id' => $tour_id,
            'target_scene_id' => $scene_id
        ),
        array('%d', '%s')
    );
    
    // Then delete the scene
    return $wpdb->delete(
        $scenes_table,
        array(
            'tour_id' => $tour_id,
            'scene_id' => $scene_id
        ),
        array('%d', '%s')
    );
}

function webronic_virtual_tour_get_hotspots($tour_id, $scene_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'webronic_virtual_tour_hotspots';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE tour_id = %d AND scene_id = %s ORDER BY created_at ASC",
        $tour_id,
        $scene_id
    ));
}

function webronic_virtual_tour_get_hotspot($tour_id, $hotspot_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'webronic_virtual_tour_hotspots';
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE tour_id = %d AND hotspot_id = %s",
        $tour_id,
        $hotspot_id
    ));
}

function webronic_virtual_tour_save_hotspot($tour_id, $hotspot_data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'webronic_virtual_tour_hotspots';
    
    // Check if hotspot already exists
    $existing_hotspot = webronic_virtual_tour_get_hotspot($tour_id, $hotspot_data['hotspot_id']);
    
    if ($existing_hotspot && $hotspot_data['hotspot_id']) {
        // Update existing hotspot
        return $wpdb->update(
            $table_name,
            array(
                'hotspot_type' => sanitize_text_field($hotspot_data['hotspot_type']),
                'pitch' => floatval($hotspot_data['pitch']),
                'yaw' => floatval($hotspot_data['yaw']),
                'text' => sanitize_text_field($hotspot_data['text']),
                'pin_info' => sanitize_textarea_field($hotspot_data['pin_info']),
                'pin_image' => esc_url_raw($hotspot_data['pin_image']),
                'target_scene_id' => sanitize_text_field($hotspot_data['target_scene_id']),
                'css_class' => sanitize_text_field($hotspot_data['css_class']),
                'updated_at' => current_time('mysql')
            ),
            array(
                'tour_id' => $tour_id,
                'hotspot_id' => $hotspot_data['hotspot_id']
            ),
            array('%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s'),
            array('%d', '%s')
        );
    } else {
        // Generate a new unique hotspot ID if not provided
        $hotspot_id = !empty($hotspot_data['hotspot_id']) ? $hotspot_data['hotspot_id'] : uniqid('hotspot_');
        // Insert new hotspot
        return $wpdb->insert(
            $table_name,
            array(
                'tour_id' => $tour_id,
                'scene_id' => sanitize_text_field($hotspot_data['scene_id']),
                'hotspot_id' => $hotspot_id,
                'hotspot_type' => sanitize_text_field($hotspot_data['hotspot_type']),
                'pitch' => floatval($hotspot_data['pitch']),
                'yaw' => floatval($hotspot_data['yaw']),
                'text' => sanitize_text_field($hotspot_data['text']),
                'pin_info' => sanitize_textarea_field($hotspot_data['pin_info']),
                'pin_image' => esc_url_raw($hotspot_data['pin_image']),
                'target_scene_id' => sanitize_text_field($hotspot_data['target_scene_id']),
                'css_class' => sanitize_text_field($hotspot_data['css_class']),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
}

function webronic_virtual_tour_delete_hotspot($tour_id, $hotspot_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'webronic_virtual_tour_hotspots';
    return $wpdb->delete(
        $table_name,
        array(
            'tour_id' => $tour_id,
            'hotspot_id' => $hotspot_id
        ),
        array('%d', '%s')
    );
}

function webronic_virtual_tour_get_default_scene($tour_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'webronic_virtual_tour_scenes';
    
    $scene = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE tour_id = %d AND is_default = 1 LIMIT 1",
        $tour_id
    ));
    
    if (!$scene) {
        $scene = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE tour_id = %d ORDER BY created_at ASC LIMIT 1",
            $tour_id
        ));
    }
    
    return $scene;
}

function webronic_virtual_tour_get_scene_title($tour_id, $scene_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'webronic_virtual_tour_scenes';
    $scene = $wpdb->get_row($wpdb->prepare(
        "SELECT scene_title FROM $table_name WHERE tour_id = %d AND scene_id = %s",
        $tour_id,
        $scene_id
    ));
    return $scene ? $scene->scene_title : 'Unknown Scene';
}

function webronic_virtual_tour_count_scenes($tour_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'webronic_virtual_tour_scenes';
    return $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE tour_id = %d",
        $tour_id
    ));
}

function webronic_virtual_tour_count_hotspots($tour_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'webronic_virtual_tour_hotspots';
    return $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE tour_id = %d",
        $tour_id
    ));
}