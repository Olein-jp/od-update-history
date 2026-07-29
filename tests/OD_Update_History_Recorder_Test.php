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
	 * Verifies single and bulk update target extraction.
	 *
	 * @return void
	 */
	public function test_capture_before_update_detects_single_and_bulk_targets() {
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
				'theme'       => $theme_slug,
				'temp_backup' => array(
					'slug' => $theme_slug,
				),
			)
		);

		$pending = $this->get_pending( $recorder );

		$this->assertArrayHasKey( 'plugin:' . $plugin_basename, $pending );
		$this->assertArrayHasKey( 'theme:' . $theme_slug, $pending );
		$this->assertSame( OD_UPDATE_HISTORY_VERSION, $pending[ 'plugin:' . $plugin_basename ]['version'] );
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
	 * Verifies WordPress bulk update hook data records plugins and themes.
	 *
	 * @return void
	 */
	public function test_bulk_update_context_records_changed_plugin_and_theme() {
		$recorder   = new OD_Update_History_Recorder();
		$plugin_one = plugin_basename( OD_UPDATE_HISTORY_DIR . 'tests/fixtures/plugins/fixture-one.php' );
		$plugin_two = plugin_basename( OD_UPDATE_HISTORY_DIR . 'tests/fixtures/plugins/fixture-two.php' );
		$theme_slug = get_stylesheet();

		$recorder->capture_before_update(
			true,
			array(
				'plugin'      => $plugin_one,
				'temp_backup' => array(
					'slug' => dirname( $plugin_one ),
				),
			)
		);
		$recorder->capture_before_update(
			true,
			array(
				'plugin'      => $plugin_two,
				'temp_backup' => array(
					'slug' => dirname( $plugin_two ),
				),
			)
		);
		$recorder->capture_before_update(
			true,
			array(
				'theme'       => $theme_slug,
				'temp_backup' => array(
					'slug' => $theme_slug,
				),
			)
		);

		$pending = $this->get_pending( $recorder );

		$pending[ 'plugin:' . $plugin_one ]['version'] = '1.0.0';
		$pending[ 'plugin:' . $plugin_two ]['version'] = '1.0.0';
		$pending[ 'theme:' . $theme_slug ]['version']  = '0.0.1';
		$this->set_pending( $recorder, $pending );

		$recorder->record_completed_update(
			new stdClass(),
			array(
				'action'  => 'update',
				'type'    => 'plugin',
				'bulk'    => true,
				'plugins' => array( $plugin_one, $plugin_two ),
			)
		);
		$recorder->record_completed_update(
			new stdClass(),
			array(
				'action' => 'update',
				'type'   => 'theme',
				'bulk'   => true,
				'themes' => array( $theme_slug ),
			)
		);

		$entries = OD_Update_History_Database::get_entries();
		$types   = array_values( array_unique( wp_list_pluck( $entries, 'object_type' ) ) );
		$plugins = array_values(
			wp_list_pluck(
				array_filter(
					$entries,
					static function ( $entry ) {
						return 'plugin' === $entry->object_type;
					}
				),
				'object_slug'
			)
		);
		sort( $types );
		sort( $plugins );

		$this->assertCount( 3, $entries );
		$this->assertSame( array(), $this->get_pending( $recorder ) );
		$this->assertSame( array( 'plugin', 'theme' ), $types );
		$this->assertSame( array( $plugin_one, $plugin_two ), $plugins );
	}

	/**
	 * Verifies a core update uses the version loaded at request start.
	 *
	 * @return void
	 */
	public function test_core_update_records_loaded_and_installed_versions() {
		global $wp_version;

		$recorder        = new OD_Update_History_Recorder();
		$loaded_version  = '0.0.1';
		$current_version = $wp_version;
		$wp_version      = $loaded_version;

		try {
			$recorder->record_completed_update(
				new stdClass(),
				array(
					'action' => 'update',
					'type'   => 'core',
				)
			);
		} finally {
			$wp_version = $current_version;
		}

		$entries = OD_Update_History_Database::get_entries();

		$this->assertCount( 1, $entries );
		$this->assertSame( 'core', $entries[0]->object_type );
		$this->assertSame( 'wordpress', $entries[0]->object_slug ); // phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText
		$this->assertSame( $loaded_version, $entries[0]->version_from );
		$this->assertSame( $current_version, $entries[0]->version_to );
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
