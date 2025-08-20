<?php
/**
 * Plugin Name: Task Manager Lite
 * Description: A simple task manager inside WordPress admin (CRUD) using a custom DB table.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPLv2 or later
 * Text Domain: task-manager-lite
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

global $tml_db_version;
$tml_db_version = '1.0';

define( 'TML_PLUGIN_FILE', __FILE__ );
define( 'TML_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TML_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Activation: create custom table
function tml_activate() {
    global $wpdb;
    global $tml_db_version;

    $table_name = $wpdb->prefix . 'tml_tasks';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
      id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      title VARCHAR(200) NOT NULL,
      description TEXT NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'pending',
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    add_option( 'tml_db_version', $tml_db_version );
}
register_activation_hook( __FILE__, 'tml_activate' );

// Deactivation: nothing destructive
function tml_deactivate() {
    // Keep data.
}
register_deactivation_hook( __FILE__, 'tml_deactivate' );

// Uninstall: remove table
if ( ! function_exists( 'tml_uninstall' ) ) {
    function tml_uninstall() {
        if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }
        global $wpdb;
        $table_name = $wpdb->prefix . 'tml_tasks';
        $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
        delete_option( 'tml_db_version' );
    }
}

// Admin menu
add_action( 'admin_menu', function() {
    add_menu_page(
        __( 'Task Manager', 'task-manager-lite' ),
        __( 'Task Manager', 'task-manager-lite' ),
        'manage_options',
        'task-manager-lite',
        'tml_render_admin_page',
        'dashicons-list-view',
        26
    );
} );

// Handle form actions
function tml_handle_actions() {
    if ( ! is_admin() ) return;
    if ( ! current_user_can( 'manage_options' ) ) return;

    $action = isset($_POST['tml_action']) ? sanitize_text_field($_POST['tml_action']) : ( isset($_GET['tml_action']) ? sanitize_text_field($_GET['tml_action']) : '' );

    if ( empty($action) ) return;

    if ( isset($_POST['_wpnonce']) && ! wp_verify_nonce( $_POST['_wpnonce'], 'tml_nonce' ) ) {
        wp_die( 'Security check failed' );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'tml_tasks';

    if ( $action === 'create' ) {
        $title = sanitize_text_field( $_POST['title'] ?? '' );
        $description = wp_kses_post( $_POST['description'] ?? '' );
        if ( ! empty( $title ) ) {
            $wpdb->insert( $table, [
                'title' => $title,
                'description' => $description,
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ] );
        }
        wp_redirect( admin_url( 'admin.php?page=task-manager-lite&msg=created' ) );
        exit;
    }

    if ( $action === 'update' ) {
        $id = intval( $_POST['id'] ?? 0 );
        $title = sanitize_text_field( $_POST['title'] ?? '' );
        $description = wp_kses_post( $_POST['description'] ?? '' );
        $status = in_array( $_POST['status'] ?? 'pending', ['pending','done'], true ) ? $_POST['status'] : 'pending';
        if ( $id > 0 && ! empty( $title ) ) {
            $wpdb->update( $table, [
                'title' => $title,
                'description' => $description,
                'status' => $status
            ], [ 'id' => $id ], [ '%s','%s','%s' ], [ '%d' ] );
        }
        wp_redirect( admin_url( 'admin.php?page=task-manager-lite&msg=updated' ) );
        exit;
    }

    if ( $action === 'delete' ) {
        $id = intval( $_GET['id'] ?? 0 );
        if ( $id > 0 ) {
            $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
        }
        wp_redirect( admin_url( 'admin.php?page=task-manager-lite&msg=deleted' ) );
        exit;
    }
}
add_action( 'admin_init', 'tml_handle_actions' );

// Admin page renderer
function tml_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    global $wpdb;
    $table = $wpdb->prefix . 'tml_tasks';

    // Edit mode?
    $edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
    $edit_task = null;
    if ( $edit_id > 0 ) {
        $edit_task = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $edit_id ) );
    }

    // Fetch tasks
    $tasks = $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC" );
    $msg = isset($_GET['msg']) ? sanitize_text_field($_GET['msg']) : '';

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Task Manager Lite', 'task-manager-lite'); ?></h1>
        <?php if ( $msg ): ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html( ucfirst($msg) ); ?></p></div>
        <?php endif; ?>

        <h2><?php echo $edit_task ? esc_html__('Edit Task', 'task-manager-lite') : esc_html__('Add New Task', 'task-manager-lite'); ?></h2>
        <form method="post">
            <?php wp_nonce_field( 'tml_nonce' ); ?>
            <?php if ( $edit_task ): ?>
                <input type="hidden" name="id" value="<?php echo esc_attr( $edit_task->id ); ?>" />
            <?php endif; ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="title"><?php esc_html_e('Title', 'task-manager-lite'); ?></label></th>
                    <td><input name="title" type="text" id="title" value="<?php echo esc_attr( $edit_task->title ?? '' ); ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="description"><?php esc_html_e('Description', 'task-manager-lite'); ?></label></th>
                    <td>
                        <textarea name="description" id="description" rows="5" class="large-text"><?php echo esc_textarea( $edit_task->description ?? '' ); ?></textarea>
                    </td>
                </tr>
                <?php if ( $edit_task ): ?>
                <tr>
                    <th scope="row"><label for="status"><?php esc_html_e('Status', 'task-manager-lite'); ?></label></th>
                    <td>
                        <select name="status" id="status">
                            <option value="pending" <?php selected( $edit_task->status, 'pending' ); ?>><?php esc_html_e('Pending', 'task-manager-lite'); ?></option>
                            <option value="done" <?php selected( $edit_task->status, 'done' ); ?>><?php esc_html_e('Done', 'task-manager-lite'); ?></option>
                        </select>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
            <p class="submit">
                <button type="submit" name="tml_action" value="<?php echo $edit_task ? 'update' : 'create'; ?>" class="button button-primary">
                    <?php echo $edit_task ? esc_html__('Update Task', 'task-manager-lite') : esc_html__('Add Task', 'task-manager-lite'); ?>
                </button>
                <?php if ( $edit_task ): ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=task-manager-lite' ) ); ?>" class="button"><?php esc_html_e('Cancel', 'task-manager-lite'); ?></a>
                <?php endif; ?>
            </p>
        </form>

        <hr>

        <h2><?php esc_html_e('All Tasks', 'task-manager-lite'); ?></h2>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('ID', 'task-manager-lite'); ?></th>
                    <th><?php esc_html_e('Title', 'task-manager-lite'); ?></th>
                    <th><?php esc_html_e('Status', 'task-manager-lite'); ?></th>
                    <th><?php esc_html_e('Created At', 'task-manager-lite'); ?></th>
                    <th><?php esc_html_e('Actions', 'task-manager-lite'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( $tasks ) : foreach ( $tasks as $task ) : ?>
                    <tr>
                        <td><?php echo esc_html( $task->id ); ?></td>
                        <td><?php echo esc_html( $task->title ); ?></td>
                        <td><?php echo esc_html( ucfirst($task->status) ); ?></td>
                        <td><?php echo esc_html( $task->created_at ); ?></td>
                        <td>
                            <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=task-manager-lite&edit=' . intval($task->id) ) ); ?>"><?php esc_html_e('Edit', 'task-manager-lite'); ?></a>
                            <a class="button-link-delete" style="color:#b32d2e;margin-left:8px;" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=task-manager-lite&tml_action=delete&id=' . intval($task->id) ), 'tml_nonce' ) ); ?>" onclick="return confirm('Delete this task?');"><?php esc_html_e('Delete', 'task-manager-lite'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="5"><?php esc_html_e('No tasks yet.', 'task-manager-lite'); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
