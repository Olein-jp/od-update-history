<?php
/**
 * Records completed WordPress updates.
 *
 * @package OD_Update_History
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records version changes made through WP_Upgrader.
 */
class OD_Update_History_Recorder {

	/**
	 * Component state captured before an update begins.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private $pending = array();

	/**
	 * Registers update hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'upgrader_pre_install', array( $this, 'capture_before_update' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'record_completed_update' ), 10, 2 );
	}

	/**
	 * Captures installed versions before WP_Upgrader replaces files.
	 *
	 * @param bool|WP_Error               $response   Installation response.
	 * @param array<string, mixed>|string $hook_extra Upgrader context.
	 * @return bool|WP_Error
	 */
	public function capture_before_update( $response, $hook_extra ) {
		if (
			! is_array( $hook_extra ) ||
			( isset( $hook_extra['action'] ) && 'update' !== $hook_extra['action'] )
		) {
			return $response;
		}

		foreach ( $this->get_targets( $hook_extra ) as $target ) {
			$state = $this->get_component_state( $target['type'], $target['slug'] );

			if ( null !== $state ) {
				$this->pending[ $this->get_pending_key( $target['type'], $target['slug'] ) ] = $state;
			}
		}

		return $response;
	}

	/**
	 * Writes a row after an update actually changes the installed version.
	 *
	 * @param WP_Upgrader                $upgrader   Upgrader instance.
	 * @param array<string, mixed>|mixed $hook_extra Upgrader context.
	 * @return void
	 */
	public function record_completed_update( $upgrader, $hook_extra ) {
		if ( ! is_array( $hook_extra ) || 'update' !== ( $hook_extra['action'] ?? '' ) ) {
			return;
		}

		foreach ( $this->get_targets( $hook_extra ) as $target ) {
			$key    = $this->get_pending_key( $target['type'], $target['slug'] );
			$before = $this->pending[ $key ] ?? null;
			$after  = $this->get_component_state( $target['type'], $target['slug'] );

			if ( null === $before && 'core' === $target['type'] ) {
				$before = $this->get_loaded_core_state();
			}

			unset( $this->pending[ $key ] );

			if (
				null === $before ||
				null === $after ||
				empty( $before['version'] ) ||
				empty( $after['version'] ) ||
				$before['version'] === $after['version']
			) {
				continue;
			}

			OD_Update_History_Database::insert(
				array(
					'object_type'   => $target['type'],
					'object_slug'   => $target['slug'],
					'object_name'   => ! empty( $after['name'] ) ? $after['name'] : $before['name'],
					'version_from'  => $before['version'],
					'version_to'    => $after['version'],
					'active_before' => $before['active'],
					'active_after'  => $after['active'],
					'update_method' => $this->get_update_method( $upgrader ),
					'metadata'      => wp_json_encode(
						array(
							'php_version'  => PHP_VERSION,
							'wp_version'   => get_bloginfo( 'version' ),
							'is_multisite' => is_multisite(),
						)
					),
				)
			);
		}
	}

	/**
	 * Extracts update targets from upgrader context.
	 *
	 * @param array<string, mixed> $hook_extra Upgrader context.
	 * @return array<int, array{type: string, slug: string}>
	 */
	private function get_targets( $hook_extra ) {
		$type    = $hook_extra['type'] ?? '';
		$targets = array();

		if ( '' === $type && ! empty( $hook_extra['plugin'] ) && is_string( $hook_extra['plugin'] ) ) {
			$type = 'plugin';
		}

		if ( '' === $type && ! empty( $hook_extra['theme'] ) && is_string( $hook_extra['theme'] ) ) {
			$type = 'theme';
		}

		if ( 'plugin' === $type ) {
			$plugins = array();

			if ( ! empty( $hook_extra['plugin'] ) && is_string( $hook_extra['plugin'] ) ) {
				$plugins[] = $hook_extra['plugin'];
			} elseif ( ! empty( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
				$plugins = $hook_extra['plugins'];
			}

			foreach ( $plugins as $plugin ) {
				if ( is_string( $plugin ) ) {
					$targets[] = array(
						'type' => 'plugin',
						'slug' => plugin_basename( $plugin ),
					);
				}
			}
		}

		if ( 'theme' === $type ) {
			$themes = array();

			if ( ! empty( $hook_extra['theme'] ) && is_string( $hook_extra['theme'] ) ) {
				$themes[] = $hook_extra['theme'];
			} elseif ( ! empty( $hook_extra['themes'] ) && is_array( $hook_extra['themes'] ) ) {
				$themes = $hook_extra['themes'];
			}

			foreach ( $themes as $theme ) {
				if ( is_string( $theme ) ) {
					$targets[] = array(
						'type' => 'theme',
						'slug' => sanitize_key( $theme ),
					);
				}
			}
		}

		if ( 'core' === $type ) {
			$targets[] = array(
				'type' => 'core',
				'slug' => 'wordpress',
			);
		}

		return $targets;
	}

	/**
	 * Reads the current installed state of a component.
	 *
	 * @param string $type Component type.
	 * @param string $slug Component identifier.
	 * @return array{name: string, version: string, active: int|null}|null
	 */
	private function get_component_state( $type, $slug ) {
		if ( 'plugin' === $type ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';

			$plugin_file = WP_PLUGIN_DIR . '/' . $slug;

			if ( ! is_file( $plugin_file ) ) {
				return null;
			}

			$data = get_plugin_data( $plugin_file, false, false );

			return array(
				'name'    => (string) $data['Name'],
				'version' => (string) $data['Version'],
				'active'  => is_plugin_active( $slug ) ? 1 : 0,
			);
		}

		if ( 'theme' === $type ) {
			$theme = wp_get_theme( $slug );

			if ( ! $theme->exists() ) {
				return null;
			}

			return array(
				'name'    => $theme->get( 'Name' ),
				'version' => $theme->get( 'Version' ),
				'active'  => get_stylesheet() === $slug ? 1 : 0,
			);
		}

		if ( 'core' === $type ) {
			return array(
				'name'    => 'WordPress',
				'version' => $this->get_core_version(),
				'active'  => null,
			);
		}

		return null;
	}

	/**
	 * Reads the installed core version directly from the version file.
	 *
	 * Reading the file avoids using the version loaded at the beginning of the
	 * request after a core update replaces WordPress files.
	 *
	 * @return string
	 */
	private function get_core_version() {
		$version_file = ABSPATH . WPINC . '/version.php';

		if ( ! is_readable( $version_file ) ) {
			return '';
		}

		$read_version = static function ( $file ) {
			$wp_version = '';

			require $file;

			return (string) $wp_version;
		};

		return $read_version( $version_file );
	}

	/**
	 * Returns the core state loaded at the beginning of the request.
	 *
	 * Core_Upgrader does not run the upgrader_pre_install filter. The global
	 * version remains unchanged after core files are replaced, so it provides
	 * the installed version from before the update.
	 *
	 * @return array{name: string, version: string, active: null}
	 */
	private function get_loaded_core_state() {
		global $wp_version;

		return array(
			'name'    => 'WordPress',
			'version' => (string) $wp_version,
			'active'  => null,
		);
	}

	/**
	 * Determines how an update was initiated.
	 *
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @return string
	 */
	private function get_update_method( $upgrader ) {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'wp_cli';
		}

		if (
			( isset( $upgrader->skin ) && is_a( $upgrader->skin, 'Automatic_Upgrader_Skin' ) ) ||
			( defined( 'DOING_CRON' ) && DOING_CRON )
		) {
			return 'automatic';
		}

		return 'manual';
	}

	/**
	 * Returns a stable in-request storage key.
	 *
	 * @param string $type Component type.
	 * @param string $slug Component identifier.
	 * @return string
	 */
	private function get_pending_key( $type, $slug ) {
		return $type . ':' . $slug;
	}
}
