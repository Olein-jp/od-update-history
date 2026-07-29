<?php
/**
 * Admin permission and nonce tests.
 *
 * @package OD_Update_History
 */

/**
 * Tests privileged admin operations.
 */
class OD_Update_History_Admin_Test extends WP_UnitTestCase {

	/**
	 * Admin controller under test.
	 *
	 * @var OD_Update_History_Admin
	 */
	private $admin;

	/**
	 * Starts each test with a custom wp_die handler and no rows.
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

		$this->admin = new OD_Update_History_Admin();

		add_filter( 'wp_die_handler', array( $this, 'filter_wp_die_handler' ) );
	}

	/**
	 * Restores global state and removes test data.
	 *
	 * @return void
	 */
	public function tear_down() {
		remove_filter( 'wp_die_handler', array( $this, 'filter_wp_die_handler' ) );

		OD_Update_History_Database::delete_all();
		OD_Update_History_Retention::unschedule();
		delete_option( OD_Update_History_Retention::OPTION_NAME );
		wp_set_current_user( 0 );

		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();

		parent::tear_down();
	}

	/**
	 * Replaces WordPress termination with a test exception.
	 *
	 * @return callable
	 */
	public function filter_wp_die_handler() {
		return array( $this, 'throw_wp_die_exception' );
	}

	/**
	 * Throws when protected code calls wp_die().
	 *
	 * @param string|WP_Error $message Error message.
	 * @return void
	 * @throws RuntimeException Always.
	 */
	public function throw_wp_die_exception( $message ) {
		if ( is_wp_error( $message ) ) {
			$message = $message->get_error_message();
		}

		throw new RuntimeException( esc_html( wp_strip_all_tags( (string) $message ) ) );
	}

	/**
	 * Verifies that administrators can render the history screen.
	 *
	 * @return void
	 */
	public function test_administrator_can_render_history_page() {
		$administrator = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $administrator );

		ob_start();
		$this->admin->render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<h1>更新履歴</h1>', $output );
	}

	/**
	 * Verifies combined filters in rows, pagination links, and export URLs.
	 *
	 * @return void
	 */
	public function test_history_page_preserves_combined_filters() {
		$administrator = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $administrator );

		for ( $index = 1; $index <= 21; $index++ ) {
			$this->insert_filter_entry(
				sprintf( 'Matching Plugin %02d', $index ),
				'plugin',
				'manual',
				sprintf( '2026-07-15 12:00:%02d', $index )
			);
		}

		$this->insert_filter_entry( 'Wrong Type', 'theme', 'manual', '2026-07-15 12:30:00' );
		$this->insert_filter_entry( 'Wrong Method', 'plugin', 'automatic', '2026-07-15 12:30:00' );
		$this->insert_filter_entry( 'Outside Range', 'plugin', 'manual', '2026-08-01 00:00:00' );

		$_GET = array(
			'page'          => 'od-update-history',
			'object_type'   => 'plugin',
			'date_from'     => '2026-07-01',
			'date_to'       => '2026-07-31',
			'update_method' => 'manual',
		);

		ob_start();
		$this->admin->render_page();
		$output         = ob_get_clean();
		$decoded_output = html_entity_decode( $output, ENT_QUOTES, 'UTF-8' );

		$this->assertSame( 20, substr_count( $output, 'Matching Plugin' ) );
		$this->assertStringNotContainsString( 'Wrong Type', $output );
		$this->assertStringNotContainsString( 'Wrong Method', $output );
		$this->assertStringNotContainsString( 'Outside Range', $output );
		$this->assertStringContainsString( 'object_type=plugin', $decoded_output );
		$this->assertStringContainsString( 'date_from=2026-07-01', $decoded_output );
		$this->assertStringContainsString( 'date_to=2026-07-31', $decoded_output );
		$this->assertStringContainsString( 'update_method=manual', $decoded_output );
		$this->assertStringContainsString( 'paged=2', $decoded_output );
		$this->assertStringContainsString( 'action=od_update_history_export', $decoded_output );
		$this->assertStringContainsString( 'format=csv', $decoded_output );
		$this->assertStringContainsString( 'format=txt', $decoded_output );
	}

	/**
	 * Verifies that invalid dates and methods are not reflected or applied.
	 *
	 * @return void
	 */
	public function test_history_page_ignores_invalid_filter_values() {
		$administrator = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $administrator );

		$this->insert_filter_entry( 'Visible Entry', 'plugin', 'manual', '2026-07-15 12:00:00' );

		$_GET = array(
			'date_from'     => '2026-02-30',
			'date_to'       => 'invalid-date',
			'update_method' => 'invalid-method',
		);

		ob_start();
		$this->admin->render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Visible Entry', $output );
		$this->assertStringNotContainsString( '2026-02-30', $output );
		$this->assertStringNotContainsString( 'invalid-date', $output );
		$this->assertStringNotContainsString( 'invalid-method', $output );
	}

	/**
	 * Verifies that lower-privilege users cannot read the history screen.
	 *
	 * @return void
	 */
	public function test_subscriber_cannot_render_history_page() {
		$subscriber = self::factory()->user->create(
			array(
				'role' => 'subscriber',
			)
		);

		wp_set_current_user( $subscriber );

		$this->expectException( RuntimeException::class );
		$this->admin->render_page();
	}

	/**
	 * Verifies that deletion is rejected when the current user lacks permission.
	 *
	 * @return void
	 */
	public function test_delete_all_requires_manage_options_capability() {
		$this->insert_history_entry();

		$subscriber = self::factory()->user->create(
			array(
				'role' => 'subscriber',
			)
		);
		wp_set_current_user( $subscriber );

		try {
			$this->admin->delete_all();
			$this->fail( 'Deletion should have been rejected.' );
		} catch ( RuntimeException $exception ) {
			$this->assertStringContainsString( '権限', $exception->getMessage() );
		}

		$this->assertSame( 1, OD_Update_History_Database::count_entries() );
	}

	/**
	 * Verifies that administrators still need a valid deletion nonce.
	 *
	 * @return void
	 */
	public function test_delete_all_requires_nonce() {
		$this->insert_history_entry();

		$administrator = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $administrator );

		$_REQUEST = array();

		try {
			$this->admin->delete_all();
			$this->fail( 'Deletion without a nonce should have been rejected.' );
		} catch ( RuntimeException $exception ) {
			$this->assertNotSame( '', $exception->getMessage() );
		}

		$this->assertSame( 1, OD_Update_History_Database::count_entries() );
	}

	/**
	 * Verifies that period deletion requires manage_options.
	 *
	 * @return void
	 */
	public function test_delete_older_requires_manage_options_capability() {
		$this->insert_history_entry();

		$subscriber = self::factory()->user->create(
			array(
				'role' => 'subscriber',
			)
		);
		wp_set_current_user( $subscriber );

		$_POST = array(
			'older_than_days' => '30',
		);

		try {
			$this->admin->delete_older();
			$this->fail( 'Period deletion should have been rejected.' );
		} catch ( RuntimeException $exception ) {
			$this->assertStringContainsString( '権限', $exception->getMessage() );
		}

		$this->assertSame( 1, OD_Update_History_Database::count_entries() );
	}

	/**
	 * Verifies that period deletion requires its dedicated nonce.
	 *
	 * @return void
	 */
	public function test_delete_older_requires_nonce() {
		$this->insert_history_entry();

		$administrator = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $administrator );

		$_POST = array(
			'older_than_days' => '30',
		);

		try {
			$this->admin->delete_older();
			$this->fail( 'Period deletion without a nonce should have been rejected.' );
		} catch ( RuntimeException $exception ) {
			$this->assertNotSame( '', $exception->getMessage() );
		}

		$this->assertSame( 1, OD_Update_History_Database::count_entries() );
	}

	/**
	 * Verifies that retention changes require manage_options.
	 *
	 * @return void
	 */
	public function test_save_retention_requires_manage_options_capability() {
		$subscriber = self::factory()->user->create(
			array(
				'role' => 'subscriber',
			)
		);
		wp_set_current_user( $subscriber );

		$_POST = array(
			'retention_days' => '30',
		);

		try {
			$this->admin->save_retention();
			$this->fail( 'Retention update should have been rejected.' );
		} catch ( RuntimeException $exception ) {
			$this->assertStringContainsString( '権限', $exception->getMessage() );
		}

		$this->assertSame( 0, OD_Update_History_Retention::get_retention_days() );
	}

	/**
	 * Verifies that retention changes require their dedicated nonce.
	 *
	 * @return void
	 */
	public function test_save_retention_requires_nonce() {
		$administrator = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $administrator );

		$_POST = array(
			'retention_days' => '30',
		);

		try {
			$this->admin->save_retention();
			$this->fail( 'Retention update without a nonce should have been rejected.' );
		} catch ( RuntimeException $exception ) {
			$this->assertNotSame( '', $exception->getMessage() );
		}

		$this->assertSame( 0, OD_Update_History_Retention::get_retention_days() );
	}

	/**
	 * Verifies that unsupported retention values are rejected.
	 *
	 * @return void
	 */
	public function test_save_retention_rejects_invalid_value() {
		$administrator = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $administrator );

		$_POST    = array(
			'retention_days' => '31',
		);
		$_REQUEST = array(
			'_wpnonce' => wp_create_nonce( 'od_update_history_save_retention' ),
		);

		try {
			$this->admin->save_retention();
			$this->fail( 'Unsupported retention should have been rejected.' );
		} catch ( RuntimeException $exception ) {
			$this->assertStringContainsString( '保持期間', $exception->getMessage() );
		}

		$this->assertSame( 0, OD_Update_History_Retention::get_retention_days() );
		$this->assertFalse( wp_next_scheduled( OD_Update_History_Retention::CRON_HOOK ) );
	}

	/**
	 * Verifies retention controls and the period deletion count notice.
	 *
	 * @return void
	 */
	public function test_history_page_renders_retention_controls_and_delete_notice() {
		$administrator = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $administrator );

		$_GET = array(
			'history-pruned' => '1',
			'pruned-days'    => '90',
			'pruned-count'   => '3',
		);

		ob_start();
		$this->admin->render_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="retention_days"', $output );
		$this->assertStringContainsString( 'name="older_than_days"', $output );
		$this->assertStringContainsString( '90日より古い更新履歴を3件削除しました。', $output );
	}

	/**
	 * Inserts one row for destructive-action tests.
	 *
	 * @return void
	 */
	private function insert_history_entry() {
		OD_Update_History_Database::insert(
			array(
				'object_type'  => 'plugin',
				'object_slug'  => 'sample/sample.php',
				'object_name'  => 'Sample Plugin',
				'version_from' => '1.0.0',
				'version_to'   => '1.1.0',
			)
		);
	}

	/**
	 * Inserts one row for list filtering tests.
	 *
	 * @param string $object_name   Display name.
	 * @param string $object_type   Component type.
	 * @param string $update_method Update method.
	 * @param string $occurred_at   Stored local date and time.
	 * @return void
	 */
	private function insert_filter_entry( $object_name, $object_type, $update_method, $occurred_at ) {
		OD_Update_History_Database::insert(
			array(
				'occurred_at'   => $occurred_at,
				'object_type'   => $object_type,
				'object_slug'   => sanitize_key( $object_name ),
				'object_name'   => $object_name,
				'version_from'  => '1.0.0',
				'version_to'    => '1.1.0',
				'update_method' => $update_method,
			)
		);
	}
}
