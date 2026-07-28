<?php
/**
 * Admin screen and actions.
 *
 * @package OD_Update_History
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides update history administration.
 */
class OD_Update_History_Admin {

	/**
	 * Number of rows shown per page.
	 *
	 * @var int
	 */
	const PER_PAGE = 20;

	/**
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_post_od_update_history_export', array( $this, 'export' ) );
		add_action( 'admin_post_od_update_history_delete_all', array( $this, 'delete_all' ) );
	}

	/**
	 * Adds the dedicated history screen.
	 *
	 * @return void
	 */
	public function add_menu_page() {
		add_menu_page(
			__( '更新履歴', 'od-update-history' ),
			__( '更新履歴', 'od-update-history' ),
			'manage_options',
			'od-update-history',
			array( $this, 'render_page' ),
			'dashicons-update',
			80
		);
	}

	/**
	 * Renders the history and data management screen.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'このページを表示する権限がありません。', 'od-update-history' ) );
		}

		$object_type = $this->get_requested_object_type();
		// No nonce is required for the read-only list view.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$total        = OD_Update_History_Database::count_entries( $object_type );
		$entries      = OD_Update_History_Database::get_entries(
			array(
				'object_type' => $object_type,
				'limit'       => self::PER_PAGE,
				'offset'      => ( $current_page - 1 ) * self::PER_PAGE,
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( '更新履歴', 'od-update-history' ); ?></h1>

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<?php if ( isset( $_GET['history-deleted'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( '更新履歴をすべて削除しました。', 'od-update-history' ); ?></p>
				</div>
			<?php endif; ?>

			<p>
				<?php
				printf(
					/* translators: %s: Number of saved history entries. */
					esc_html__( '保存件数: %s件', 'od-update-history' ),
					esc_html( number_format_i18n( OD_Update_History_Database::count_entries() ) )
				);
				?>
			</p>

			<form method="get">
				<input type="hidden" name="page" value="od-update-history">
				<label for="od-update-history-object-type"><?php esc_html_e( '種別', 'od-update-history' ); ?></label>
				<select id="od-update-history-object-type" name="object_type">
					<option value=""><?php esc_html_e( 'すべて', 'od-update-history' ); ?></option>
					<option value="core" <?php selected( $object_type, 'core' ); ?>><?php esc_html_e( 'WordPress', 'od-update-history' ); ?></option>
					<option value="plugin" <?php selected( $object_type, 'plugin' ); ?>><?php esc_html_e( 'プラグイン', 'od-update-history' ); ?></option>
					<option value="theme" <?php selected( $object_type, 'theme' ); ?>><?php esc_html_e( 'テーマ', 'od-update-history' ); ?></option>
				</select>
				<?php submit_button( __( '絞り込む', 'od-update-history' ), 'secondary', 'filter_action', false ); ?>
			</form>

			<table class="widefat fixed striped" style="margin-top: 1em;">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( '日時', 'od-update-history' ); ?></th>
						<th scope="col"><?php esc_html_e( '種別', 'od-update-history' ); ?></th>
						<th scope="col"><?php esc_html_e( '対象', 'od-update-history' ); ?></th>
						<th scope="col"><?php esc_html_e( 'バージョン', 'od-update-history' ); ?></th>
						<th scope="col"><?php esc_html_e( '状態', 'od-update-history' ); ?></th>
						<th scope="col"><?php esc_html_e( '更新方法', 'od-update-history' ); ?></th>
						<th scope="col"><?php esc_html_e( '実行者', 'od-update-history' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $entries ) ) : ?>
						<tr>
							<td colspan="7"><?php esc_html_e( '更新履歴はまだありません。', 'od-update-history' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $entries as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( $this->format_date( $entry->occurred_at ) ); ?></td>
								<td><?php echo esc_html( $this->get_type_label( $entry->object_type ) ); ?></td>
								<td>
									<strong><?php echo esc_html( $entry->object_name ); ?></strong><br>
									<code><?php echo esc_html( $entry->object_slug ); ?></code>
								</td>
								<td><?php echo esc_html( $entry->version_from . ' → ' . $entry->version_to ); ?></td>
								<td><?php echo esc_html( $this->get_active_label( $entry->active_before ) ); ?></td>
								<td><?php echo esc_html( $this->get_method_label( $entry->update_method ) ); ?></td>
								<td><?php echo esc_html( $this->get_user_label( (int) $entry->user_id ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php
			$total_pages = (int) ceil( $total / self::PER_PAGE );

			if ( $total_pages > 1 ) {
				$pagination = paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'current'   => $current_page,
						'total'     => $total_pages,
						'type'      => 'list',
						'prev_text' => __( '前へ', 'od-update-history' ),
						'next_text' => __( '次へ', 'od-update-history' ),
					)
				);

				if ( $pagination ) {
					echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $pagination ) . '</div></div>';
				}
			}
			?>

			<hr>
			<h2><?php esc_html_e( 'エクスポート', 'od-update-history' ); ?></h2>
			<p><?php esc_html_e( '現在の種別フィルターに一致する履歴をダウンロードします。', 'od-update-history' ); ?></p>
			<p>
				<a class="button" href="<?php echo esc_url( $this->get_export_url( 'csv', $object_type ) ); ?>">
					<?php esc_html_e( 'CSVをダウンロード', 'od-update-history' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( $this->get_export_url( 'txt', $object_type ) ); ?>">
					<?php esc_html_e( 'TXTをダウンロード', 'od-update-history' ); ?>
				</a>
			</p>

			<hr>
			<h2><?php esc_html_e( 'データ管理', 'od-update-history' ); ?></h2>
			<p><?php esc_html_e( 'テーブルを残したまま、保存済みの更新履歴をすべて削除します。この操作は取り消せません。', 'od-update-history' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( '更新履歴をすべて削除しますか？この操作は取り消せません。', 'od-update-history' ) ); ?>');">
				<input type="hidden" name="action" value="od_update_history_delete_all">
				<?php wp_nonce_field( 'od_update_history_delete_all' ); ?>
				<?php submit_button( __( '更新履歴をすべて削除', 'od-update-history' ), 'delete', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Downloads history as CSV or plain text.
	 *
	 * @return void
	 */
	public function export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'この操作を行う権限がありません。', 'od-update-history' ) );
		}

		check_admin_referer( 'od_update_history_export' );

		$format      = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : '';
		$object_type = $this->get_requested_object_type();

		if ( ! in_array( $format, array( 'csv', 'txt' ), true ) ) {
			wp_die( esc_html__( '未対応の出力形式です。', 'od-update-history' ) );
		}

		$entries  = OD_Update_History_Database::get_entries(
			array(
				'object_type' => $object_type,
				'limit'       => PHP_INT_MAX,
				'offset'      => 0,
			)
		);
		$filename = 'od-update-history-' . gmdate( 'Ymd-His' ) . '.' . $format;

		nocache_headers();
		header( 'Content-Type: ' . ( 'csv' === $format ? 'text/csv' : 'text/plain' ) . '; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		if ( 'csv' === $format ) {
			$this->output_csv( $entries );
		} else {
			$this->output_text( $entries );
		}

		exit;
	}

	/**
	 * Deletes all history after capability and nonce checks.
	 *
	 * @return void
	 */
	public function delete_all() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'この操作を行う権限がありません。', 'od-update-history' ) );
		}

		check_admin_referer( 'od_update_history_delete_all' );
		OD_Update_History_Database::delete_all();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => 'od-update-history',
					'history-deleted' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Outputs CSV data.
	 *
	 * @param array<int, object> $entries History entries.
	 * @return void
	 */
	private function output_csv( $entries ) {
		$output = fopen( 'php://output', 'w' );

		if ( false === $output ) {
			return;
		}

		fwrite( $output, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fputcsv( $output, array( 'date', 'type', 'name', 'slug', 'version_from', 'version_to', 'status', 'method', 'user' ) );

		foreach ( $entries as $entry ) {
			fputcsv(
				$output,
				array(
					$entry->occurred_at,
					$entry->object_type,
					$entry->object_name,
					$entry->object_slug,
					$entry->version_from,
					$entry->version_to,
					$this->get_active_export_value( $entry->active_before ),
					$entry->update_method,
					$this->get_user_label( (int) $entry->user_id ),
				)
			);
		}

		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}

	/**
	 * Outputs human-readable text.
	 *
	 * @param array<int, object> $entries History entries.
	 * @return void
	 */
	private function output_text( $entries ) {
		echo "OD Update History\n";
		echo 'Site: ' . esc_url_raw( home_url( '/' ) ) . "\n";
		echo 'Exported: ' . esc_html( current_time( 'mysql' ) ) . "\n\n";

		foreach ( $entries as $entry ) {
			echo esc_html( $entry->occurred_at ) . "\n";
			echo esc_html( $this->get_type_label( $entry->object_type ) . ': ' . $entry->object_name ) . "\n";
			echo esc_html( 'Version: ' . $entry->version_from . ' -> ' . $entry->version_to ) . "\n";
			echo esc_html( 'Status: ' . $this->get_active_export_value( $entry->active_before ) ) . "\n";
			echo esc_html( 'Method: ' . $entry->update_method ) . "\n";
			echo esc_html( 'User: ' . $this->get_user_label( (int) $entry->user_id ) ) . "\n\n";
		}
	}

	/**
	 * Gets a nonce-protected export URL.
	 *
	 * @param string $format      Export format.
	 * @param string $object_type Optional type filter.
	 * @return string
	 */
	private function get_export_url( $format, $object_type ) {
		$url = add_query_arg(
			array(
				'action'      => 'od_update_history_export',
				'format'      => $format,
				'object_type' => $object_type,
			),
			admin_url( 'admin-post.php' )
		);

		return wp_nonce_url( $url, 'od_update_history_export' );
	}

	/**
	 * Returns a validated object type from the request.
	 *
	 * @return string
	 */
	private function get_requested_object_type() {
		// The caller performs nonce verification for stateful actions; list filters are read-only.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$object_type = isset( $_GET['object_type'] ) ? sanitize_key( wp_unslash( $_GET['object_type'] ) ) : '';

		return in_array( $object_type, array( 'core', 'plugin', 'theme' ), true ) ? $object_type : '';
	}

	/**
	 * Formats a stored local date.
	 *
	 * @param string $date MySQL date.
	 * @return string
	 */
	private function format_date( $date ) {
		return mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $date );
	}

	/**
	 * Returns a translated component type.
	 *
	 * @param string $type Component type.
	 * @return string
	 */
	private function get_type_label( $type ) {
		$labels = array(
			'core'   => __( 'WordPress', 'od-update-history' ),
			'plugin' => __( 'プラグイン', 'od-update-history' ),
			'theme'  => __( 'テーマ', 'od-update-history' ),
		);

		return $labels[ $type ] ?? $type;
	}

	/**
	 * Returns a translated active state.
	 *
	 * @param string|int|null $active Active state.
	 * @return string
	 */
	private function get_active_label( $active ) {
		if ( null === $active ) {
			return '—';
		}

		return (int) $active ? __( '有効', 'od-update-history' ) : __( '無効', 'od-update-history' );
	}

	/**
	 * Returns a stable active state for exports.
	 *
	 * @param string|int|null $active Active state.
	 * @return string
	 */
	private function get_active_export_value( $active ) {
		if ( null === $active ) {
			return 'n/a';
		}

		return (int) $active ? 'active' : 'inactive';
	}

	/**
	 * Returns a translated update method.
	 *
	 * @param string $method Update method.
	 * @return string
	 */
	private function get_method_label( $method ) {
		$labels = array(
			'automatic' => __( '自動更新', 'od-update-history' ),
			'manual'    => __( '手動更新', 'od-update-history' ),
			'wp_cli'    => __( 'WP-CLI', 'od-update-history' ),
			'unknown'   => __( '不明', 'od-update-history' ),
		);

		return $labels[ $method ] ?? $method;
	}

	/**
	 * Returns a user display name or a system label.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function get_user_label( $user_id ) {
		if ( 0 === $user_id ) {
			return __( 'システム', 'od-update-history' );
		}

		$user = get_userdata( $user_id );

		return $user ? $user->display_name : __( '不明', 'od-update-history' );
	}
}
