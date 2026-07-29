<?php
/**
 * Plugin Name:       OD Update History
 * Description:       Records update history in WordPress.
 * Version:           0.1.1
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            Olein Design
 * Update URI:        https://github.com/Olein-jp/od-update-history
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       od-update-history
 *
 * @package OD_Update_History
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OD_UPDATE_HISTORY_VERSION', '0.1.1' );
define( 'OD_UPDATE_HISTORY_DB_VERSION', '1' );
define( 'OD_UPDATE_HISTORY_FILE', __FILE__ );
define( 'OD_UPDATE_HISTORY_DIR', plugin_dir_path( __FILE__ ) );

$od_update_history_autoloader = OD_UPDATE_HISTORY_DIR . 'vendor/autoload.php';

if ( is_readable( $od_update_history_autoloader ) ) {
	require_once $od_update_history_autoloader;
}

require_once OD_UPDATE_HISTORY_DIR . 'includes/class-od-update-history-database.php';
require_once OD_UPDATE_HISTORY_DIR . 'includes/class-od-update-history-recorder.php';
require_once OD_UPDATE_HISTORY_DIR . 'includes/class-od-update-history-admin.php';
require_once OD_UPDATE_HISTORY_DIR . 'includes/class-od-update-history-updater.php';

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

	$updater = new OD_Update_History_Updater();
	$updater->register_hooks();

	if ( is_admin() ) {
		$admin = new OD_Update_History_Admin();
		$admin->register_hooks();
	}
}
add_action( 'plugins_loaded', 'od_update_history_init' );
