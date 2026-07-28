<?php
/**
 * Database tests.
 *
 * @package OD_Update_History
 */

/**
 * Tests the custom history table.
 */
class OD_Update_History_Database_Test extends WP_UnitTestCase {

	/**
	 * Creates the table and starts each test with no rows.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		OD_Update_History_Database::activate();
		OD_Update_History_Database::delete_all();
	}

	/**
	 * Removes data created by a test.
	 *
	 * @return void
	 */
	public function tear_down() {
		OD_Update_History_Database::delete_all();

		parent::tear_down();
	}

	/**
	 * Verifies that activation creates the expected table.
	 *
	 * @return void
	 */
	public function test_activation_creates_history_table() {
		global $wpdb;

		$table_name = OD_Update_History_Database::get_table_name();
		$found      = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) )
		);

		$this->assertSame( $table_name, $found );
		$this->assertSame( OD_UPDATE_HISTORY_DB_VERSION, get_option( OD_Update_History_Database::VERSION_OPTION ) );
	}

	/**
	 * Verifies insert, filtered reads, ordering, and counts.
	 *
	 * @return void
	 */
	public function test_insert_and_get_entries() {
		$plugin_id = OD_Update_History_Database::insert(
			array(
				'occurred_at'   => '2026-07-28 10:00:00',
				'object_type'   => 'plugin',
				'object_slug'   => 'sample/sample.php',
				'object_name'   => 'Sample Plugin',
				'version_from'  => '1.0.0',
				'version_to'    => '1.1.0',
				'active_before' => 1,
				'active_after'  => 1,
				'update_method' => 'manual',
			)
		);
		$theme_id  = OD_Update_History_Database::insert(
			array(
				'occurred_at'   => '2026-07-28 11:00:00',
				'object_type'   => 'theme',
				'object_slug'   => 'sample-theme',
				'object_name'   => 'Sample Theme',
				'version_from'  => '2.0.0',
				'version_to'    => '2.1.0',
				'active_before' => 0,
				'active_after'  => 0,
				'update_method' => 'automatic',
			)
		);

		$this->assertIsInt( $plugin_id );
		$this->assertIsInt( $theme_id );
		$this->assertSame( 2, OD_Update_History_Database::count_entries() );
		$this->assertSame( 1, OD_Update_History_Database::count_entries( 'plugin' ) );

		$all_entries = OD_Update_History_Database::get_entries();
		$plugins     = OD_Update_History_Database::get_entries(
			array(
				'object_type' => 'plugin',
			)
		);

		$this->assertCount( 2, $all_entries );
		$this->assertSame( 'theme', $all_entries[0]->object_type );
		$this->assertCount( 1, $plugins );
		$this->assertSame( '1.0.0', $plugins[0]->version_from );
		$this->assertSame( '1.1.0', $plugins[0]->version_to );
	}

	/**
	 * Verifies that all rows can be removed without dropping the table.
	 *
	 * @return void
	 */
	public function test_delete_all_removes_rows_and_keeps_table() {
		global $wpdb;

		OD_Update_History_Database::insert(
			array(
				'object_type'  => 'core',
				'object_slug'  => 'wordpress',
				'object_name'  => 'WordPress',
				'version_from' => '7.0.0',
				'version_to'   => '7.0.1',
			)
		);

		$this->assertSame( 1, OD_Update_History_Database::count_entries() );
		$this->assertSame( 1, OD_Update_History_Database::delete_all() );
		$this->assertSame( 0, OD_Update_History_Database::count_entries() );

		$table_name = OD_Update_History_Database::get_table_name();
		$found      = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) )
		);

		$this->assertSame( $table_name, $found );
	}
}
