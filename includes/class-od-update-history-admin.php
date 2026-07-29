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
		add_action( 'admin_post_od_update_history_delete_older', array( $this, 'delete_older' ) );
		add_action( 'admin_post_od_update_history_save_retention', array( $this, 'save_retention' ) );
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

		$filters        = $this->get_requested_filters();
		$retention_days = OD_Update_History_Retention::get_retention_days();
		// No nonce is required for the read-only list view.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$total        = OD_Update_History_Database::count_entries( $filters );
		$entries      = OD_Update_History_Database::get_entries(
			array_merge(
				$filters,
				array(
					'limit'  => self::PER_PAGE,
					'offset' => ( $current_page - 1 ) * self::PER_PAGE,
				)
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

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$pruned_days = isset( $_GET['pruned-days'] ) && is_string( $_GET['pruned-days'] ) ? absint( $_GET['pruned-days'] ) : 0;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$pruned_count = isset( $_GET['pruned-count'] ) && is_string( $_GET['pruned-count'] ) ? absint( $_GET['pruned-count'] ) : 0;
			?>
			<?php if ( isset( $_GET['history-pruned'] ) && OD_Update_History_Retention::is_allowed_days( $pruned_days ) && 0 !== $pruned_days ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						printf(
							/* translators: 1: Retention days, 2: Number of deleted entries. */
							esc_html__( '%1$d日より古い更新履歴を%2$d件削除しました。', 'od-update-history' ),
							esc_html( number_format_i18n( $pruned_days ) ),
							esc_html( number_format_i18n( $pruned_count ) )
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['retention-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( '自動保持期間を更新しました。', 'od-update-history' ); ?></p>
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
					<option value="core" <?php selected( $filters['object_type'], 'core' ); ?>><?php esc_html_e( 'WordPress', 'od-update-history' ); ?></option>
					<option value="plugin" <?php selected( $filters['object_type'], 'plugin' ); ?>><?php esc_html_e( 'プラグイン', 'od-update-history' ); ?></option>
					<option value="theme" <?php selected( $filters['object_type'], 'theme' ); ?>><?php esc_html_e( 'テーマ', 'od-update-history' ); ?></option>
				</select>
				<label for="od-update-history-date-from"><?php esc_html_e( '開始日', 'od-update-history' ); ?></label>
				<input type="date" id="od-update-history-date-from" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
				<label for="od-update-history-date-to"><?php esc_html_e( '終了日', 'od-update-history' ); ?></label>
				<input type="date" id="od-update-history-date-to" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
				<label for="od-update-history-update-method"><?php esc_html_e( '更新方法', 'od-update-history' ); ?></label>
				<select id="od-update-history-update-method" name="update_method">
					<option value=""><?php esc_html_e( 'すべて', 'od-update-history' ); ?></option>
					<option value="manual" <?php selected( $filters['update_method'], 'manual' ); ?>><?php esc_html_e( '手動', 'od-update-history' ); ?></option>
					<option value="automatic" <?php selected( $filters['update_method'], 'automatic' ); ?>><?php esc_html_e( '自動', 'od-update-history' ); ?></option>
					<option value="wp_cli" <?php selected( $filters['update_method'], 'wp_cli' ); ?>><?php esc_html_e( 'WP-CLI', 'od-update-history' ); ?></option>
					<option value="unknown" <?php selected( $filters['update_method'], 'unknown' ); ?>><?php esc_html_e( '不明', 'od-update-history' ); ?></option>
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
				$pagination_args = array_filter( $filters );
				$pagination_args = array_merge(
					array(
						'page'  => 'od-update-history',
						'paged' => '%#%',
					),
					$pagination_args
				);
				$pagination      = paginate_links(
					array(
						'base'      => add_query_arg( $pagination_args, admin_url( 'admin.php' ) ),
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
			<p><?php esc_html_e( '現在の絞り込み条件に一致する履歴をダウンロードします。', 'od-update-history' ); ?></p>
			<p>
				<a class="button" href="<?php echo esc_url( $this->get_export_url( 'csv', $filters ) ); ?>">
					<?php esc_html_e( 'CSVをダウンロード', 'od-update-history' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( $this->get_export_url( 'txt', $filters ) ); ?>">
					<?php esc_html_e( 'TXTをダウンロード', 'od-update-history' ); ?>
				</a>
			</p>

			<hr>
			<h2><?php esc_html_e( 'データ管理', 'od-update-history' ); ?></h2>
			<h3><?php esc_html_e( '自動保持期間', 'od-update-history' ); ?></h3>
			<p><?php esc_html_e( '設定した日数より古い履歴を1日1回自動で削除します。初期値の「無期限」では自動削除しません。', 'od-update-history' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="od_update_history_save_retention">
				<label for="od-update-history-retention-days"><?php esc_html_e( '保持期間', 'od-update-history' ); ?></label>
				<select id="od-update-history-retention-days" name="retention_days">
					<option value="0" <?php selected( $retention_days, 0 ); ?>><?php esc_html_e( '無期限', 'od-update-history' ); ?></option>
					<option value="30" <?php selected( $retention_days, 30 ); ?>><?php esc_html_e( '30日', 'od-update-history' ); ?></option>
					<option value="90" <?php selected( $retention_days, 90 ); ?>><?php esc_html_e( '90日', 'od-update-history' ); ?></option>
					<option value="180" <?php selected( $retention_days, 180 ); ?>><?php esc_html_e( '180日', 'od-update-history' ); ?></option>
					<option value="365" <?php selected( $retention_days, 365 ); ?>><?php esc_html_e( '365日', 'od-update-history' ); ?></option>
				</select>
				<?php wp_nonce_field( 'od_update_history_save_retention' ); ?>
				<?php submit_button( __( '保持期間を保存', 'od-update-history' ), 'secondary', 'submit', false ); ?>
			</form>

			<h3><?php esc_html_e( '期間指定削除', 'od-update-history' ); ?></h3>
			<p><?php esc_html_e( '選択した日数より古い更新履歴を削除します。境界日時とそれ以降の履歴は残ります。', 'od-update-history' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( '指定期間より古い更新履歴を削除しますか？この操作は取り消せません。', 'od-update-history' ) ); ?>');">
				<input type="hidden" name="action" value="od_update_history_delete_older">
				<label for="od-update-history-delete-older-days"><?php esc_html_e( '削除対象', 'od-update-history' ); ?></label>
				<select id="od-update-history-delete-older-days" name="older_than_days">
					<option value="30"><?php esc_html_e( '30日より古い履歴', 'od-update-history' ); ?></option>
					<option value="90"><?php esc_html_e( '90日より古い履歴', 'od-update-history' ); ?></option>
					<option value="180"><?php esc_html_e( '180日より古い履歴', 'od-update-history' ); ?></option>
					<option value="365"><?php esc_html_e( '365日より古い履歴', 'od-update-history' ); ?></option>
				</select>
				<?php wp_nonce_field( 'od_update_history_delete_older' ); ?>
				<?php submit_button( __( '指定期間より古い履歴を削除', 'od-update-history' ), 'delete', 'submit', false ); ?>
			</form>

			<h3><?php esc_html_e( '全履歴削除', 'od-update-history' ); ?></h3>
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

		$format  = isset( $_GET['format'] ) && is_string( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : '';
		$filters = $this->get_requested_filters();

		if ( ! in_array( $format, array( 'csv', 'txt' ), true ) ) {
			wp_die( esc_html__( '未対応の出力形式です。', 'od-update-history' ) );
		}

		$entries  = OD_Update_History_Database::get_entries(
			array_merge(
				$filters,
				array(
					'limit'  => PHP_INT_MAX,
					'offset' => 0,
				)
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

		$this->redirect_to_history(
			array(
				'history-deleted' => '1',
			)
		);
	}

	/**
	 * Deletes history older than an allowed period.
	 *
	 * @return void
	 */
	public function delete_older() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'この操作を行う権限がありません。', 'od-update-history' ) );
		}

		check_admin_referer( 'od_update_history_delete_older' );

		$days = $this->get_posted_retention_days( 'older_than_days', false );

		if ( null === $days ) {
			wp_die( esc_html__( '削除期間が正しくありません。', 'od-update-history' ) );
		}

		$deleted = OD_Update_History_Database::delete_older_than(
			OD_Update_History_Retention::get_cutoff_date( $days )
		);

		if ( false === $deleted ) {
			wp_die( esc_html__( '更新履歴を削除できませんでした。', 'od-update-history' ) );
		}

		$this->redirect_to_history(
			array(
				'history-pruned' => '1',
				'pruned-days'    => $days,
				'pruned-count'   => $deleted,
			)
		);
	}

	/**
	 * Saves optional automatic retention.
	 *
	 * @return void
	 */
	public function save_retention() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'この操作を行う権限がありません。', 'od-update-history' ) );
		}

		check_admin_referer( 'od_update_history_save_retention' );

		$days = $this->get_posted_retention_days( 'retention_days', true );

		if ( null === $days || ! OD_Update_History_Retention::set_retention_days( $days ) ) {
			wp_die( esc_html__( '保持期間が正しくありません。', 'od-update-history' ) );
		}

		$this->redirect_to_history(
			array(
				'retention-updated' => '1',
			)
		);
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
	 * @param string                $format  Export format.
	 * @param array<string, string> $filters Validated filters.
	 * @return string
	 */
	private function get_export_url( $format, $filters ) {
		$query_args = array_merge(
			array(
				'action' => 'od_update_history_export',
				'format' => $format,
			),
			array_filter( $filters )
		);
		$url        = add_query_arg(
			$query_args,
			admin_url( 'admin-post.php' )
		);

		return wp_nonce_url( $url, 'od_update_history_export' );
	}

	/**
	 * Returns all validated list filters from the request.
	 *
	 * @return array{object_type: string, date_from: string, date_to: string, update_method: string}
	 */
	private function get_requested_filters() {
		$date_from = $this->get_requested_date( 'date_from' );
		$date_to   = $this->get_requested_date( 'date_to' );

		if ( '' !== $date_from && '' !== $date_to && $date_from > $date_to ) {
			$date_from = '';
			$date_to   = '';
		}

		return array(
			'object_type'   => $this->get_requested_object_type(),
			'date_from'     => $date_from,
			'date_to'       => $date_to,
			'update_method' => $this->get_requested_update_method(),
		);
	}

	/**
	 * Returns a validated object type from the request.
	 *
	 * @return string
	 */
	private function get_requested_object_type() {
		// The caller performs nonce verification for stateful actions; list filters are read-only.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$object_type = isset( $_GET['object_type'] ) && is_string( $_GET['object_type'] ) ? sanitize_key( wp_unslash( $_GET['object_type'] ) ) : '';

		return in_array( $object_type, array( 'core', 'plugin', 'theme' ), true ) ? $object_type : '';
	}

	/**
	 * Returns a validated date from the request.
	 *
	 * @param string $key Request key.
	 * @return string
	 */
	private function get_requested_date( $key ) {
		// The caller performs nonce verification for stateful actions; list filters are read-only.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date = isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';

		return $this->is_valid_date( $date ) ? $date : '';
	}

	/**
	 * Returns a validated update method from the request.
	 *
	 * @return string
	 */
	private function get_requested_update_method() {
		// The caller performs nonce verification for stateful actions; list filters are read-only.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$update_method = isset( $_GET['update_method'] ) && is_string( $_GET['update_method'] ) ? sanitize_key( wp_unslash( $_GET['update_method'] ) ) : '';

		return in_array( $update_method, array( 'manual', 'automatic', 'wp_cli', 'unknown' ), true ) ? $update_method : '';
	}

	/**
	 * Checks for a real date in the expected request format.
	 *
	 * @param string $date Date value.
	 * @return bool
	 */
	private function is_valid_date( $date ) {
		if ( 1 !== preg_match( '/\A(\d{4})-(\d{2})-(\d{2})\z/', $date, $matches ) ) {
			return false;
		}

		return checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] );
	}

	/**
	 * Returns an allowed retention period from POST data.
	 *
	 * @param string $key             Request key.
	 * @param bool   $allow_unlimited Whether zero is accepted.
	 * @return int|null
	 */
	private function get_posted_retention_days( $key, $allow_unlimited ) {
		// Stateful callers verify their dedicated nonce before this method runs.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$value = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';

		if ( ! ctype_digit( $value ) ) {
			return null;
		}

		$days = (int) $value;

		if (
			! OD_Update_History_Retention::is_allowed_days( $days ) ||
			( ! $allow_unlimited && 0 === $days )
		) {
			return null;
		}

		return $days;
	}

	/**
	 * Redirects back to the history page with a status message.
	 *
	 * @param array<string, string|int> $args Status query arguments.
	 * @return void
	 */
	private function redirect_to_history( $args ) {
		wp_safe_redirect(
			add_query_arg(
				array_merge(
					array(
						'page' => 'od-update-history',
					),
					$args
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
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
