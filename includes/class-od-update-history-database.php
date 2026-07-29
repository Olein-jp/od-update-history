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
				'object_type'   => '',
				'date_from'     => '',
				'date_to'       => '',
				'update_method' => '',
				'limit'         => 20,
				'offset'        => 0,
			)
		);

		$table_name = self::get_table_name();
		$limit      = max( 1, (int) $args['limit'] );
		$offset     = max( 0, (int) $args['offset'] );
		$conditions = self::get_filter_conditions( $args );
		$query      = 'SELECT * FROM %i';
		$values     = array( $table_name );

		if ( ! empty( $conditions['clauses'] ) ) {
			$query .= ' WHERE ' . implode( ' AND ', $conditions['clauses'] );
			$values = array_merge( $values, $conditions['values'] );
		}

		$query   .= ' ORDER BY occurred_at DESC, id DESC LIMIT %d OFFSET %d';
		$values[] = $limit;
		$values[] = $offset;

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( $query, $values ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * Counts history entries.
	 *
	 * @param array<string, mixed>|string $args Optional filters or object type.
	 * @return int
	 */
	public static function count_entries( $args = array() ) {
		global $wpdb;

		if ( is_string( $args ) ) {
			$args = array(
				'object_type' => $args,
			);
		}

		$args       = wp_parse_args(
			$args,
			array(
				'object_type'   => '',
				'date_from'     => '',
				'date_to'       => '',
				'update_method' => '',
			)
		);
		$table_name = self::get_table_name();
		$conditions = self::get_filter_conditions( $args );
		$query      = 'SELECT COUNT(*) FROM %i';
		$values     = array( $table_name );

		if ( ! empty( $conditions['clauses'] ) ) {
			$query .= ' WHERE ' . implode( ' AND ', $conditions['clauses'] );
			$values = array_merge( $values, $conditions['values'] );
		}

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( $query, $values ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * Builds validated WHERE conditions for history queries.
	 *
	 * Stored dates use the site timezone, so date boundaries can be compared
	 * directly without converting them to UTC.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array{clauses: array<int, string>, values: array<int, string>}
	 */
	private static function get_filter_conditions( $args ) {
		$clauses = array();
		$values  = array();

		if ( in_array( $args['object_type'], array( 'core', 'plugin', 'theme' ), true ) ) {
			$clauses[] = 'object_type = %s';
			$values[]  = $args['object_type'];
		}

		if ( in_array( $args['update_method'], array( 'manual', 'automatic', 'wp_cli', 'unknown' ), true ) ) {
			$clauses[] = 'update_method = %s';
			$values[]  = $args['update_method'];
		}

		$date_from = self::is_valid_date( $args['date_from'] ) ? $args['date_from'] : '';
		$date_to   = self::is_valid_date( $args['date_to'] ) ? $args['date_to'] : '';

		if ( '' !== $date_from && '' !== $date_to && $date_from > $date_to ) {
			$date_from = '';
			$date_to   = '';
		}

		if ( '' !== $date_from ) {
			$clauses[] = 'occurred_at >= %s';
			$values[]  = $date_from . ' 00:00:00';
		}

		if ( '' !== $date_to ) {
			$clauses[] = 'occurred_at <= %s';
			$values[]  = $date_to . ' 23:59:59';
		}

		return array(
			'clauses' => $clauses,
			'values'  => $values,
		);
	}

	/**
	 * Checks for a real date in the expected request format.
	 *
	 * @param mixed $date Date value.
	 * @return bool
	 */
	private static function is_valid_date( $date ) {
		if ( ! is_string( $date ) || 1 !== preg_match( '/\A(\d{4})-(\d{2})-(\d{2})\z/', $date, $matches ) ) {
			return false;
		}

		return checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] );
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

	/**
	 * Deletes entries strictly older than a site-local cutoff date.
	 *
	 * An entry at the exact cutoff remains stored.
	 *
	 * @param string $cutoff MySQL date in the site timezone.
	 * @return int|false Number of deleted rows, or false for invalid input or query failure.
	 */
	public static function delete_older_than( $cutoff ) {
		global $wpdb;

		if ( ! self::is_valid_datetime( $cutoff ) ) {
			return false;
		}

		$table_name = self::get_table_name();

		return $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'DELETE FROM %i WHERE occurred_at < %s',
				$table_name,
				$cutoff
			)
		);
	}

	/**
	 * Checks for a real MySQL date and time.
	 *
	 * @param mixed $date Date value.
	 * @return bool
	 */
	private static function is_valid_datetime( $date ) {
		if (
			! is_string( $date ) ||
			1 !== preg_match( '/\A(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})\z/', $date, $matches )
		) {
			return false;
		}

		return (
			checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) &&
			(int) $matches[4] <= 23 &&
			(int) $matches[5] <= 59 &&
			(int) $matches[6] <= 59
		);
	}
}
