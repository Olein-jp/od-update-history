<?php
/**
 * Retention and scheduled cleanup tests.
 *
 * @package OD_Update_History
 */

/**
 * Tests optional automatic retention.
 */
class OD_Update_History_Retention_Test extends WP_UnitTestCase {

	/**
	 * Resets retention, schedules, and history.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		OD_Update_History_Database::activate();
		OD_Update_History_Database::delete_all();
		OD_Update_History_Retention::unschedule();
		delete_option( OD_Update_History_Retention::OPTION_NAME );
		OD_Update_History_Retention::activate();
	}

	/**
	 * Removes retention state created by a test.
	 *
	 * @return void
	 */
	public function tear_down() {
		OD_Update_History_Retention::unschedule();
		delete_option( OD_Update_History_Retention::OPTION_NAME );
		OD_Update_History_Database::delete_all();

		parent::tear_down();
	}

	/**
	 * Verifies that unlimited retention is the safe default.
	 *
	 * @return void
	 */
	public function test_unlimited_retention_is_default_and_does_not_delete() {
		$this->insert_history_entry( '2000-01-01 00:00:00', 'Old Entry' );

		$this->assertSame( 0, OD_Update_History_Retention::get_retention_days() );
		$this->assertFalse( wp_next_scheduled( OD_Update_History_Retention::CRON_HOOK ) );
		$this->assertSame( 0, OD_Update_History_Retention::run_cleanup() );
		$this->assertSame( 1, OD_Update_History_Database::count_entries() );
	}

	/**
	 * Verifies that repeated synchronization does not duplicate cron events.
	 *
	 * @return void
	 */
	public function test_schedule_is_not_duplicated() {
		$this->assertTrue( OD_Update_History_Retention::set_retention_days( 30 ) );

		$scheduled = wp_next_scheduled( OD_Update_History_Retention::CRON_HOOK );

		$this->assertIsInt( $scheduled );

		OD_Update_History_Retention::sync_schedule();
		OD_Update_History_Retention::sync_schedule();

		$this->assertSame( $scheduled, wp_next_scheduled( OD_Update_History_Retention::CRON_HOOK ) );
		$this->assertSame( 1, $this->count_scheduled_cleanup_events() );
	}

	/**
	 * Verifies that cleanup is idempotent and keeps current entries.
	 *
	 * @return void
	 */
	public function test_cleanup_is_idempotent() {
		$this->insert_history_entry( '2000-01-01 00:00:00', 'Expired Entry' );
		$this->insert_history_entry( current_time( 'mysql' ), 'Current Entry' );
		OD_Update_History_Retention::set_retention_days( 30 );

		$this->assertSame( 1, OD_Update_History_Retention::run_cleanup() );
		$this->assertSame( 0, OD_Update_History_Retention::run_cleanup() );

		$entries = OD_Update_History_Database::get_entries();

		$this->assertCount( 1, $entries );
		$this->assertSame( 'Current Entry', $entries[0]->object_name );
	}

	/**
	 * Verifies that returning to unlimited unschedules without deleting data.
	 *
	 * @return void
	 */
	public function test_unlimited_setting_unschedules_and_keeps_history() {
		$this->insert_history_entry( '2000-01-01 00:00:00', 'Retained Entry' );
		OD_Update_History_Retention::set_retention_days( 90 );

		$this->assertIsInt( wp_next_scheduled( OD_Update_History_Retention::CRON_HOOK ) );
		$this->assertTrue( OD_Update_History_Retention::set_retention_days( 0 ) );
		$this->assertFalse( wp_next_scheduled( OD_Update_History_Retention::CRON_HOOK ) );
		$this->assertSame( 1, OD_Update_History_Database::count_entries() );
	}

	/**
	 * Counts all scheduled instances of the cleanup hook.
	 *
	 * @return int
	 */
	private function count_scheduled_cleanup_events() {
		$count = 0;
		$cron  = _get_cron_array();

		foreach ( $cron as $hooks ) {
			if ( isset( $hooks[ OD_Update_History_Retention::CRON_HOOK ] ) ) {
				$count += count( $hooks[ OD_Update_History_Retention::CRON_HOOK ] );
			}
		}

		return $count;
	}

	/**
	 * Inserts a history row.
	 *
	 * @param string $occurred_at Stored local date and time.
	 * @param string $object_name Display name.
	 * @return void
	 */
	private function insert_history_entry( $occurred_at, $object_name ) {
		OD_Update_History_Database::insert(
			array(
				'occurred_at'  => $occurred_at,
				'object_type'  => 'plugin',
				'object_slug'  => sanitize_key( $object_name ),
				'object_name'  => $object_name,
				'version_from' => '1.0.0',
				'version_to'   => '1.1.0',
			)
		);
	}
}
