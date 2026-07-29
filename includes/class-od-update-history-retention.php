<?php
/**
 * Retention settings and scheduled cleanup.
 *
 * @package OD_Update_History
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages optional automatic retention of update history.
 */
class OD_Update_History_Retention {

	/**
	 * Option storing the selected retention period.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'od_update_history_retention_days';

	/**
	 * Scheduled cleanup hook.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'od_update_history_retention_cleanup';

	/**
	 * Allowed retention values. Zero means unlimited.
	 *
	 * @var array<int, int>
	 */
	const ALLOWED_DAYS = array( 0, 30, 90, 180, 365 );

	/**
	 * Registers cleanup and keeps its schedule in sync with the setting.
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_cleanup' ) );
		self::sync_schedule();
	}

	/**
	 * Creates the default setting without changing existing data.
	 *
	 * @return void
	 */
	public static function activate() {
		add_option( self::OPTION_NAME, 0, '', false );
		self::sync_schedule();
	}

	/**
	 * Returns the validated retention period.
	 *
	 * @return int
	 */
	public static function get_retention_days() {
		$days = (int) get_option( self::OPTION_NAME, 0 );

		return self::is_allowed_days( $days ) ? $days : 0;
	}

	/**
	 * Saves a validated retention period and updates the schedule.
	 *
	 * @param int $days Retention period. Zero means unlimited.
	 * @return bool Whether the value was valid.
	 */
	public static function set_retention_days( $days ) {
		$days = (int) $days;

		if ( ! self::is_allowed_days( $days ) ) {
			return false;
		}

		update_option( self::OPTION_NAME, $days, false );
		self::sync_schedule();

		return true;
	}

	/**
	 * Ensures that finite retention has exactly one recurring schedule request.
	 *
	 * @return void
	 */
	public static function sync_schedule() {
		if ( 0 === self::get_retention_days() ) {
			self::unschedule();
			return;
		}

		if ( false === wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Removes scheduled cleanup without deleting settings or history.
	 *
	 * @return void
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Deletes expired history when finite retention is enabled.
	 *
	 * @return int|false Number of deleted rows, or false on database failure.
	 */
	public static function run_cleanup() {
		$days = self::get_retention_days();

		if ( 0 === $days ) {
			return 0;
		}

		return OD_Update_History_Database::delete_older_than( self::get_cutoff_date( $days ) );
	}

	/**
	 * Returns the site-local cutoff date for an allowed period.
	 *
	 * @param int $days Retention period.
	 * @return string Empty when the period is invalid or unlimited.
	 */
	public static function get_cutoff_date( $days ) {
		$days = (int) $days;

		if ( 0 === $days || ! self::is_allowed_days( $days ) ) {
			return '';
		}

		return current_datetime()->modify( '-' . $days . ' days' )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Checks an allowed retention period.
	 *
	 * @param int $days Retention period.
	 * @return bool
	 */
	public static function is_allowed_days( $days ) {
		return in_array( (int) $days, self::ALLOWED_DAYS, true );
	}
}
