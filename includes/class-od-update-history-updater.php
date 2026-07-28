<?php
/**
 * GitHub Releases updater integration.
 *
 * @package OD_Update_History
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers update delivery through GitHub Releases.
 */
class OD_Update_History_Updater {

	/**
	 * GitHub repository owner.
	 *
	 * @var string
	 */
	const REPOSITORY_OWNER = 'Olein-jp';

	/**
	 * GitHub repository name.
	 *
	 * @var string
	 */
	const REPOSITORY_NAME = 'od-update-history';

	/**
	 * Updater instance.
	 *
	 * @var \Inc2734\WP_GitHub_Plugin_Updater\Bootstrap|null
	 */
	private $updater;

	/**
	 * Registers the updater and package URL filter.
	 *
	 * @return void
	 */
	public function register_hooks() {
		if ( ! class_exists( '\Inc2734\WP_GitHub_Plugin_Updater\Bootstrap' ) ) {
			add_action( 'admin_notices', array( $this, 'render_missing_dependency_notice' ) );
			return;
		}

		add_filter(
			'inc2734_github_plugin_updater_zip_url_Olein-jp/od-update-history',
			array( $this, 'get_release_package_url' ),
			10,
			4
		);

		$this->updater = new \Inc2734\WP_GitHub_Plugin_Updater\Bootstrap(
			plugin_basename( OD_UPDATE_HISTORY_FILE ),
			self::REPOSITORY_OWNER,
			self::REPOSITORY_NAME,
			array(
				'homepage'        => 'https://github.com/Olein-jp/od-update-history',
				'description_url' => 'https://raw.githubusercontent.com/Olein-jp/od-update-history/main/README.md',
				'tested'          => '7.0',
				'requires_php'    => '7.4',
				'requires'        => '6.9',
			)
		);
	}

	/**
	 * Uses the release ZIP that includes production Composer dependencies.
	 *
	 * @param string|false $url        Default package URL.
	 * @param string       $user_name  GitHub repository owner.
	 * @param string       $repository GitHub repository name.
	 * @param string|null  $tag_name   Release tag name.
	 * @return string|false
	 */
	public function get_release_package_url( $url, $user_name, $repository, $tag_name ) {
		if (
			self::REPOSITORY_OWNER !== $user_name ||
			self::REPOSITORY_NAME !== $repository ||
			! is_string( $tag_name ) ||
			'' === $tag_name
		) {
			return $url;
		}

		return sprintf(
			'https://github.com/%1$s/%2$s/releases/download/%3$s/%2$s.zip',
			self::REPOSITORY_OWNER,
			self::REPOSITORY_NAME,
			rawurlencode( $tag_name )
		);
	}

	/**
	 * Shows a notice when a development checkout has no Composer dependencies.
	 *
	 * @return void
	 */
	public function render_missing_dependency_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p>
				<?php
				esc_html_e(
					'OD Update History の更新機能に必要な依存ライブラリがありません。配布ZIPを再インストールするか、開発環境で composer install を実行してください。',
					'od-update-history'
				);
				?>
			</p>
		</div>
		<?php
	}
}
