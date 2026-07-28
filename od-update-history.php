<?php
/**
 * Plugin Name:       OD Update History
 * Description:       Records update history in WordPress.
 * Version:           0.1.0
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            Olein Design
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       od-update-history
 *
 * @package OD_Update_History
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OD_UPDATE_HISTORY_VERSION', '0.1.0' );
define( 'OD_UPDATE_HISTORY_DB_VERSION', '1' );
define( 'OD_UPDATE_HISTORY_FILE', __FILE__ );
define( 'OD_UPDATE_HISTORY_DIR', plugin_dir_path( __FILE__ ) );

require_once OD_UPDATE_HISTORY_DIR . 'includes/class-od-update-history-database.php';
require_once OD_UPDATE_HISTORY_DIR . 'includes/class-od-update-history-recorder.php';
require_once OD_UPDATE_HISTORY_DIR . 'includes/class-od-update-history-admin.php';

register_activation_hook( __FILE__, array( 'OD_Update_History_Database', 'activate' ) );

/**
 * Starts the plugin.
 *
 * @return void
 */
function od_update_history_init() {
	OD_Update_History_Database::maybe_upgrade();

	$recorder = new OD_Update_History_Recorder();
	$recorder->register_hooks();

	if ( is_admin() ) {
		$admin = new OD_Update_History_Admin();
		$admin->register_hooks();
	}
}
add_action( 'plugins_loaded', 'od_update_history_init' );
