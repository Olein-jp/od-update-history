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
}
