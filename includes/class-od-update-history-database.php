<?php
/**
 * Database access for OD Update History.
 *
 * @package OD_Update_History
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and queries the update history table.
 */
class OD_Update_History_Database {

	/**
	 * Option name used to store the schema version.
	 *
	 * @var string
	 */
	const VERSION_OPTION = 'od_update_history_db_version';

	/**
	 * Creates or upgrades the database table.
	 *
	 * @return void
	 */
	public static function activate() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			occurred_at datetime NOT NULL,
			object_type varchar(20) NOT NULL,
			object_slug varchar(191) NOT NULL,
			object_name varchar(191) NOT NULL,
			version_from varchar(100) NOT NULL,
			version_to varchar(100) NOT NULL,
			active_before tinyint(1) DEFAULT NULL,
			active_after tinyint(1) DEFAULT NULL,
			update_method varchar(20) NOT NULL,
			result varchar(20) NOT NULL DEFAULT 'success',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			metadata longtext DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY occurred_at (occurred_at),
			KEY object_type (object_type),
			KEY object_slug (object_slug)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( self::VERSION_OPTION, OD_UPDATE_HISTORY_DB_VERSION, false );
	}

	/**
	 * Runs schema updates when the plugin files are newer than the database.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( OD_UPDATE_HISTORY_DB_VERSION !== get_option( self::VERSION_OPTION ) ) {
			self::activate();
		}
	}

	/**
	 * Returns the prefixed table name.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'od_update_history';
	}

	/**
	 * Inserts a history entry.
	 *
	 * @param array<string, mixed> $entry History data.
	 * @return int|false Inserted row ID or false.
	 */
	public static function insert( $entry ) {
		global $wpdb;

		$defaults = array(
			'occurred_at'   => current_time( 'mysql' ),
			'object_type'   => '',
			'object_slug'   => '',
			'object_name'   => '',
			'version_from'  => '',
			'version_to'    => '',
			'active_before' => null,
			'active_after'  => null,
			'update_method' => 'unknown',
			'result'        => 'success',
			'user_id'       => get_current_user_id(),
			'metadata'      => null,
		);
		$data     = wp_parse_args( $entry, $defaults );

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::get_table_name(),
			$data,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Returns history entries for the admin screen.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<int, object>
	 */
	public static function get_entries( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'object_type' => '',
				'limit'       => 20,
				'offset'      => 0,
			)
		);

		$table_name = self::get_table_name();
		$limit      = max( 1, (int) $args['limit'] );
		$offset     = max( 0, (int) $args['offset'] );

		if ( in_array( $args['object_type'], array( 'core', 'plugin', 'theme' ), true ) ) {
			return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					'SELECT * FROM %i WHERE object_type = %s ORDER BY occurred_at DESC, id DESC LIMIT %d OFFSET %d',
					$table_name,
					$args['object_type'],
					$limit,
					$offset
				)
			);
		}

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY occurred_at DESC, id DESC LIMIT %d OFFSET %d',
				$table_name,
				$limit,
				$offset
			)
		);
	}

	/**
	 * Counts history entries.
	 *
	 * @param string $object_type Optional object type.
	 * @return int
	 */
	public static function count_entries( $object_type = '' ) {
		global $wpdb;

		$table_name = self::get_table_name();

		if ( in_array( $object_type, array( 'core', 'plugin', 'theme' ), true ) ) {
			return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE object_type = %s',
					$table_name,
					$object_type
				)
			);
		}

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table_name )
		);
	}

	/**
	 * Deletes every history entry while keeping the table.
	 *
	 * @return int|false Number of deleted rows or false.
	 */
	public static function delete_all() {
		global $wpdb;

		$table_name = self::get_table_name();

		return $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( 'DELETE FROM %i', $table_name )
		);
	}
}
