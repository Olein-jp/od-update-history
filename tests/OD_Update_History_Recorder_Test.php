<?php
/**
 * Recorder tests.
 *
 * @package OD_Update_History
 */

/**
 * Tests update target detection and history recording.
 */
class OD_Update_History_Recorder_Test extends WP_UnitTestCase {

	/**
	 * Starts each test with an empty history table.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		OD_Update_History_Database::activate();
		OD_Update_History_Database::delete_all();
	}

	/**
	 * Removes data and user state created by a test.
	 *
	 * @return void
	 */
	public function tear_down() {
		OD_Update_History_Database::delete_all();
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * Verifies plugin, theme, and core target extraction.
	 *
	 * @return void
	 */
	public function test_capture_before_update_detects_supported_targets() {
		$recorder        = new OD_Update_History_Recorder();
		$plugin_basename = plugin_basename( OD_UPDATE_HISTORY_FILE );
		$theme_slug      = get_stylesheet();

		$recorder->capture_before_update(
			true,
			array(
				'action' => 'update',
				'type'   => 'plugin',
				'plugin' => $plugin_basename,
			)
		);
		$recorder->capture_before_update(
			true,
			array(
				'action' => 'update',
				'type'   => 'theme',
				'theme'  => $theme_slug,
			)
		);
		$recorder->capture_before_update(
			true,
			array(
				'action' => 'update',
				'type'   => 'core',
			)
		);

		$pending = $this->get_pending( $recorder );

		$this->assertArrayHasKey( 'plugin:' . $plugin_basename, $pending );
		$this->assertArrayHasKey( 'theme:' . $theme_slug, $pending );
		$this->assertArrayHasKey( 'core:wordpress', $pending ); // phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText
		$this->assertSame( OD_UPDATE_HISTORY_VERSION, $pending[ 'plugin:' . $plugin_basename ]['version'] );
		$this->assertNotSame( '', $pending['core:wordpress']['version'] );
	}

	/**
	 * Verifies unsupported hook data does not create pending state.
	 *
	 * @return void
	 */
	public function test_capture_before_update_skips_invalid_or_non_update_contexts() {
		$recorder = new OD_Update_History_Recorder();

		$this->assertTrue( $recorder->capture_before_update( true, 'invalid' ) );
		$this->assertTrue(
			$recorder->capture_before_update(
				true,
				array(
					'action' => 'install',
					'type'   => 'plugin',
					'plugin' => plugin_basename( OD_UPDATE_HISTORY_FILE ),
				)
			)
		);

		$this->assertSame( array(), $this->get_pending( $recorder ) );
	}

	/**
	 * Verifies that a real version change is stored exactly once.
	 *
	 * @return void
	 */
	public function test_completed_update_records_changed_version() {
		$recorder        = new OD_Update_History_Recorder();
		$plugin_basename = plugin_basename( OD_UPDATE_HISTORY_FILE );
		$administrator   = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $administrator );
		$this->set_pending(
			$recorder,
			array(
				'plugin:' . $plugin_basename => array(
					'name'    => 'OD Update History',
					'version' => '0.0.1',
					'active'  => 1,
				),
			)
		);

		$recorder->record_completed_update(
			new stdClass(),
			array(
				'action' => 'update',
				'type'   => 'plugin',
				'plugin' => $plugin_basename,
			)
		);

		$entries = OD_Update_History_Database::get_entries();

		$this->assertCount( 1, $entries );
		$this->assertSame( $plugin_basename, $entries[0]->object_slug );
		$this->assertSame( '0.0.1', $entries[0]->version_from );
		$this->assertSame( OD_UPDATE_HISTORY_VERSION, $entries[0]->version_to );
		$this->assertSame( 'manual', $entries[0]->update_method );
		$this->assertSame( $administrator, (int) $entries[0]->user_id );
		$this->assertSame( array(), $this->get_pending( $recorder ) );
	}

	/**
	 * Verifies unchanged versions and missing pending state are skipped.
	 *
	 * @return void
	 */
	public function test_completed_update_skips_unchanged_or_missing_state() {
		$recorder        = new OD_Update_History_Recorder();
		$plugin_basename = plugin_basename( OD_UPDATE_HISTORY_FILE );
		$context         = array(
			'action' => 'update',
			'type'   => 'plugin',
			'plugin' => $plugin_basename,
		);

		$this->set_pending(
			$recorder,
			array(
				'plugin:' . $plugin_basename => array(
					'name'    => 'OD Update History',
					'version' => OD_UPDATE_HISTORY_VERSION,
					'active'  => 1,
				),
			)
		);

		$recorder->record_completed_update( new stdClass(), $context );
		$recorder->record_completed_update( new stdClass(), $context );

		$this->assertSame( 0, OD_Update_History_Database::count_entries() );
		$this->assertSame( array(), $this->get_pending( $recorder ) );
	}

	/**
	 * Reads the recorder's in-request pending state.
	 *
	 * @param OD_Update_History_Recorder $recorder Recorder instance.
	 * @return array<string, array<string, mixed>>
	 */
	private function get_pending( $recorder ) {
		$property = new ReflectionProperty( $recorder, 'pending' );
		$property->setAccessible( true );

		return $property->getValue( $recorder );
	}

	/**
	 * Replaces the recorder's in-request pending state.
	 *
	 * @param OD_Update_History_Recorder          $recorder Recorder instance.
	 * @param array<string, array<string, mixed>> $pending Pending state.
	 * @return void
	 */
	private function set_pending( $recorder, $pending ) {
		$property = new ReflectionProperty( $recorder, 'pending' );
		$property->setAccessible( true );
		$property->setValue( $recorder, $pending );
	}
}
