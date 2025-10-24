<?php
function webronic_virtual_tour_admin_menu() {
    add_menu_page(
        'WEBRONIC 360 Tour',
        'WEBRONIC 360 Tour',
        'manage_options',
        'webronic-virtual-tour',
        'webronic_virtual_tour_admin_page',
        'dashicons-camera',
        6
    );

    add_submenu_page(
        null,
        'Edit Tour',
        'Edit Tour',
        'manage_options',
        'webronic-virtual-tour-editor',
        'webronic_virtual_tour_editor_page'
    );
}

function webronic_virtual_tour_admin_page() {
    global $wpdb;

    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['tour_id'])) {
        check_admin_referer('delete_tour_' . $_GET['tour_id']);
        $result = webronic_virtual_tour_delete_tour((int) $_GET['tour_id']);
        set_transient('webronic_tour_deleted_notice', $result !== false ? 'success' : 'error', 60);
        wp_redirect(admin_url('admin.php?page=webronic-virtual-tour'));
        exit;
    }

    $notice = get_transient('webronic_tour_deleted_notice');
    if ($notice) {
        echo $notice === 'success'
            ? '<div class="notice notice-success is-dismissible"><p>Tour deleted successfully!</p></div>'
            : '<div class="notice notice-error is-dismissible"><p>Error deleting tour.</p></div>';
        delete_transient('webronic_tour_deleted_notice');
    }

    $tours = webronic_virtual_tour_get_tours();

    // enqueue with version
    wp_enqueue_style(
        'webronic-virtual-tour-admin-css',
        WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL . 'assets/css/admin.css',
        [],
        WEBRONIC_VIRTUAL_TOUR_VERSION
    );
    wp_enqueue_script(
        'webronic-virtual-tour-admin-js',
        WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL . 'assets/js/admin.js',
        array('jquery'),
        WEBRONIC_VIRTUAL_TOUR_VERSION,
        true
    );
    wp_localize_script('webronic-virtual-tour-admin-js', 'webronic_virtual_tour_admin', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('webronic_virtual_tour_nonce'),
    ));

    // icon paths
    $edit_icon_url   = WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL . 'assets/img/edit.png';
    $delete_icon_url = WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL . 'assets/img/delete.png';
    $copy_icon_url   = WEBRONIC_VIRTUAL_TOUR_PLUGIN_URL . 'assets/img/copy.png';
    ?>
    <div class="wrap">
        <!-- Header: Title left + Add button right -->
        <div class="webronic-header">
            <h1 class="webronic-main-title">Webronic 360 Tour</h1>
            <button id="add-new-tour-btn" class="webronic-add-btn">+ Add New Tour</button>
        </div>
        <hr class="wp-header-end">

        <div id="virtual-tour-360-dashboard">
            <?php if (empty($tours)) : ?>
                <!-- No tours state (left intentionally blank to preserve original behavior) -->
            <?php else : ?>
                <div class="webronic-table-container">
                    <table class="wp-list-table widefat fixed striped table-view-list webronic-tours-table">
                        <thead>
                            <tr>
                                <th scope="col" class="manage-column column-id">Tour ID</th>
                                <th scope="col" class="manage-column column-title">Tour Name</th>
                                <th scope="col" class="manage-column column-shortcode">Embed code</th>
                                <th scope="col" class="manage-column column-scenes">Scenes</th>
                                <th scope="col" class="manage-column column-hotspots">Interactive Pins</th>
                                <th scope="col" class="manage-column column-date">Created on</th>
                                <th scope="col" class="manage-column column-actions">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $count = 1;
                            foreach ($tours as $tour) :
                                $scene_count   = webronic_virtual_tour_count_scenes($tour->id);
                                $hotspot_count = webronic_virtual_tour_count_hotspots($tour->id);
                                $row_class = ($count % 2 == 1) ? 'odd-row' : 'even-row';
                            ?>
                                <tr class="<?php echo esc_attr($row_class); ?>">
                                    <td class="column-id"><strong>#<?php echo esc_html($count++); ?></strong></td>
                                    <td class="column-title">
                                        <strong><?php echo esc_html($tour->tour_title); ?></strong>
                                        <div class="row-actions">
                                            <span class="edit">
                                                <a href="#" class="edit-tour" data-id="<?php echo esc_attr($tour->id); ?>" data-title="<?php echo esc_attr($tour->tour_title); ?>">Edit</a> |
                                            </span>
                                            <span class="trash">
                                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=webronic-virtual-tour&action=delete&tour_id=' . $tour->id), 'delete_tour_' . $tour->id)); ?>"
                                                   class="submitdelete">Delete</a>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="column-shortcode">
                                        <div class="shortcode-container">
                                            <div class="shortcode-input-wrapper">
                                                <input type="text" class="shortcode-input" value="<?php echo esc_attr($tour->shortcode); ?>" readonly>
                                                <button class="copy-shortcode copy-btn" title="Copy Shortcode">
                                                    <img src="<?php echo esc_url($copy_icon_url); ?>" alt="Copy" class="copy-icon" width="20" height="20" />
                                                </button>
                                            </div>
                                            <!-- Success message will be inserted here by JavaScript -->
                                        </div>
                                    </td>
                                    <td class="column-scenes"><strong><?php echo esc_html($scene_count); ?></strong></td>
                                    <td class="column-hotspots"><strong><?php echo esc_html($hotspot_count); ?></strong></td>
                                    <td class="column-date">
                                        <strong>
                                            <?php
                                            $created_raw = isset($tour->created_at) ? (string) $tour->created_at : '';

                                            if ($created_raw !== '') {
                                                // Assume created_at stored in DB as UTC MySQL DATETIME (e.g. "2025-10-23 07:26:25")
                                                // Convert to timestamp and let WordPress apply the site timezone via wp_date().
                                                $ts_utc = strtotime($created_raw . ' UTC');

                                                echo esc_html(
                                                    wp_date(
                                                        get_option('date_format') . ' ' . get_option('time_format'),
                                                        $ts_utc
                                                    )
                                                );
                                            } else {
                                                echo esc_html('-');
                                            }
                                            ?>
                                        </strong>
                                    </td>

                                    <td class="column-actions">
                                        <a class="action-icon img-icon edit-img"
                                           href="<?php echo esc_url(admin_url('admin.php?page=webronic-virtual-tour-editor&tour_id=' . $tour->id)); ?>"
                                           title="Edit Tour" aria-label="Edit Tour">
                                            <img src="<?php echo esc_url($edit_icon_url); ?>" alt="Edit" class="action-icon-img" width="28" height="28" loading="lazy" />
                                        </a>
                                        <a class="action-icon img-icon delete-img submitdelete"
                                           href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=webronic-virtual-tour&action=delete&tour_id=' . $tour->id), 'delete_tour_' . $tour->id)); ?>"
                                           title="Delete Tour" aria-label="Delete Tour">
                                            <img src="<?php echo esc_url($delete_icon_url); ?>" alt="Delete" class="action-icon-img action-icon-img--red" width="28" height="28" loading="lazy" />
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Add Tour Modal -->
        <div id="add-tour-modal" class="virtual-tour-modal" style="display:none;">
          <div class="virtual-tour-modal-content vtt-card">
            <div class="vtt-modal-head">
              <h2>Add New Tour</h2>
              <div class="vtt-actions">
                <button type="button" class="vtt-btn vtt-btn--cancel close-modal">Cancel</button>
                <button type="submit" form="add-tour-form" class="vtt-btn vtt-btn--save vtt-btn--add">Add</button>
              </div>
            </div>

            <div class="vtt-modal-body">
                <form id="add-tour-form">
                  <div class="form-group vtt-field">
                    <label for="tour-title">Tour Name</label>
                    <input type="text" id="tour-title" name="tour_title" required placeholder="Enter Tour Name">
                  </div>
                </form>
            </div>
          </div>
        </div>

        <!-- Edit Tour Modal -->
        <div id="edit-tour-modal" class="virtual-tour-modal" style="display:none;">
          <div class="virtual-tour-modal-content vtt-card">
            <div class="vtt-modal-head">
              <h2>Edit Tour</h2>
              <div class="vtt-actions">
                <button type="button" class="vtt-btn vtt-btn--cancel close-modal">Cancel</button>
                <button type="submit" form="edit-tour-form" class="vtt-btn vtt-btn--save">Save</button>
              </div>
            </div>

            <div class="vtt-modal-body">
                <form id="edit-tour-form">
                  <input type="hidden" id="edit-tour-id" name="tour_id" value="">
                  <div class="form-group vtt-field">
                    <label for="edit-tour-title">Tour Name</label>
                    <input type="text" id="edit-tour-title" name="tour_title" required value="">
                  </div>
                </form>
            </div>
          </div>
        </div>
    </div>
<?php
}
