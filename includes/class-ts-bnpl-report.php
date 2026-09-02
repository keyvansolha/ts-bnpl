<?php
/**
 * صفحه‌ی خلاصه‌ی قیمت‌های اقساطی.
 *
 * @package TS_BNPL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TS_BNPL_Report
 */
class TS_BNPL_Report {

	/**
	 * اسلاگ صفحه.
	 */
	const PAGE_SLUG = 'ts-bnpl-report';

	/**
	 * سطح دسترسی لازم.
	 */
	const CAPABILITY = 'manage_woocommerce';

	/**
	 * تعداد ردیف در هر صفحه.
	 */
	const PER_PAGE = 50;

	/**
	 * تعداد ردیف در هر دسته هنگام خروجی CSV.
	 */
	const EXPORT_CHUNK = 500;

	/**
	 * اکشن admin-post برای خروجی CSV.
	 */
	const EXPORT_ACTION = 'ts_bnpl_export';

	/**
	 * اکشن admin-post برای ذخیره‌ی تنظیمات نمایش.
	 */
	const SETTINGS_ACTION = 'ts_bnpl_save_settings';

	/**
	 * ثبت هوک‌ها.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_' . self::SETTINGS_ACTION, array( __CLASS__, 'handle_settings_save' ) );
	}

	/**
	 * ثبت زیرمنو زیر «محصولات».
	 *
	 * @return void
	 */
	public static function register_page() {
		add_submenu_page(
			'edit.php?post_type=product',
			__( 'قیمت‌های اقساطی', 'ts-bnpl' ),
			__( 'قیمت‌های اقساطی', 'ts-bnpl' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * بارگذاری استایل فقط در صفحه‌ی گزارش.
	 *
	 * @param string $hook_suffix شناسه‌ی صفحه‌ی جاری پیشخوان.
	 *
	 * @return void
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( 'product_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'ts-bnpl',
			TS_BNPL_URL . 'assets/css/ts-bnpl.css',
			array(),
			TS_BNPL_VERSION
		);
	}

	/*
	|--------------------------------------------------------------------------
	| کوئری
	|--------------------------------------------------------------------------
	*/

	/**
	 * ستون‌های مجاز برای مرتب‌سازی.
	 *
	 * کلید همان چیزی است که در آدرس می‌آید و مقدار، عبارت SQL معتبر.
	 * هیچ ورودی کاربر هرگز مستقیم در SQL درج نمی‌شود.
	 *
	 * @return array<string,string>
	 */
	private static function orderby_allowlist() {
		return array(
			'diff_amount'  => 'diff_amount',
			'diff_percent' => 'diff_percent',
		);
	}

	/**
	 * خواندن و پاکسازی پارامترهای درخواست.
	 *
	 * @return array{orderby:string,order:string,paged:int}
	 */
	private static function request_args() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- خواندنی و بدون تغییر داده.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'diff_percent';
		$order   = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'asc';
		$paged   = isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! array_key_exists( $orderby, self::orderby_allowlist() ) ) {
			$orderby = 'diff_percent';
		}

		return array(
			'orderby' => $orderby,
			'order'   => 'desc' === strtolower( $order ) ? 'DESC' : 'ASC',
			'paged'   => max( 1, $paged ),
		);
	}

	/**
	 * بخش SELECT مشترک کوئری‌ها.
	 *
	 * @return string
	 */
	private static function sql_select() {
		global $wpdb;

		return "
			SELECT
				p.ID              AS id,
				p.post_type       AS post_type,
				p.post_parent     AS post_parent,
				p.post_title      AS post_title,
				p.post_excerpt    AS attribute_summary,
				parent.post_title AS parent_title,
				bnpl.bnpl_price   AS bnpl_price,
				COALESCE( cash.cash_price, 0 ) AS cash_price,
				( bnpl.bnpl_price - COALESCE( cash.cash_price, 0 ) ) AS diff_amount,
				CASE
					WHEN COALESCE( cash.cash_price, 0 ) > 0
					THEN ( ( bnpl.bnpl_price - cash.cash_price ) / cash.cash_price ) * 100
					ELSE NULL
				END AS diff_percent,
				(
					SELECT sm.meta_value
					FROM {$wpdb->postmeta} AS sm
					WHERE sm.post_id = p.ID AND sm.meta_key = '_stock_status'
					LIMIT 1
				) AS stock_status
		";
	}

	/**
	 * بخش FROM/WHERE مشترک کوئری‌ها.
	 *
	 * زیرکوئری‌های گروه‌بندی‌شده تضمین می‌کنند حتی اگر متای تکراری روی یک پست
	 * وجود داشته باشد (مثلاً _price روی محصول متغیر) ردیف خروجی تکرار نشود.
	 *
	 * این بخش دو بار پارامتر meta_key قیمت اقساطی را می‌گیرد: یک بار برای
	 * انتخاب ردیف‌ها و یک بار برای محدود کردن زیرکوئری _price به همان مجموعه،
	 * تا در کاتالوگ چند هزار موردی کل جدول قیمت‌ها گروه‌بندی نشود.
	 *
	 * @return string
	 */
	private static function sql_from() {
		global $wpdb;

		return "
			FROM (
				SELECT pm.post_id, MAX( pm.meta_value + 0 ) AS bnpl_price
				FROM {$wpdb->postmeta} AS pm
				WHERE pm.meta_key = %s
				GROUP BY pm.post_id
				HAVING MAX( pm.meta_value + 0 ) > 0
			) AS bnpl
			INNER JOIN {$wpdb->posts} AS p ON p.ID = bnpl.post_id
			LEFT JOIN {$wpdb->posts} AS parent ON parent.ID = p.post_parent
			LEFT JOIN (
				SELECT pm2.post_id, MIN( pm2.meta_value + 0 ) AS cash_price
				FROM {$wpdb->postmeta} AS pm2
				INNER JOIN {$wpdb->postmeta} AS pm3
					ON pm3.post_id = pm2.post_id AND pm3.meta_key = %s
				WHERE pm2.meta_key = '_price'
				GROUP BY pm2.post_id
			) AS cash ON cash.post_id = p.ID
			WHERE p.post_type IN ( 'product', 'product_variation' )
				AND p.post_status = 'publish'
		";
	}

	/**
	 * دریافت یک صفحه از ردیف‌ها.
	 *
	 * @param string $orderby کلید مرتب‌سازی (از allowlist).
	 * @param string $order   ASC یا DESC (از پیش اعتبارسنجی‌شده).
	 * @param int    $limit   تعداد ردیف.
	 * @param int    $offset  آفست.
	 *
	 * @return array<int,object>
	 */
	private static function get_rows( $orderby, $order, $limit, $offset ) {
		global $wpdb;

		$allowlist = self::orderby_allowlist();
		$column    = isset( $allowlist[ $orderby ] ) ? $allowlist[ $orderby ] : 'diff_percent';
		$direction = 'DESC' === $order ? 'DESC' : 'ASC';

		$sql = self::sql_select() . self::sql_from() . " ORDER BY {$column} {$direction}, p.ID ASC LIMIT %d OFFSET %d";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results( $wpdb->prepare( $sql, TS_BNPL_Data::META, TS_BNPL_Data::META, (int) $limit, (int) $offset ) );
	}

	/**
	 * دریافت آمار کل (بدون صفحه‌بندی).
	 *
	 * @return object{total:int,avg_pct:?float,min_pct:?float,max_pct:?float}
	 */
	private static function get_summary() {
		global $wpdb;

		$inner = '
			SELECT
				CASE
					WHEN COALESCE( cash.cash_price, 0 ) > 0
					THEN ( ( bnpl.bnpl_price - cash.cash_price ) / cash.cash_price ) * 100
					ELSE NULL
				END AS diff_percent
		' . self::sql_from();

		$sql = "
			SELECT
				COUNT(*)             AS total,
				AVG( diff_percent )  AS avg_pct,
				MIN( diff_percent )  AS min_pct,
				MAX( diff_percent )  AS max_pct
			FROM ( {$inner} ) AS agg
		";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( $sql, TS_BNPL_Data::META, TS_BNPL_Data::META ) );

		if ( ! $row ) {
			$row = (object) array(
				'total'   => 0,
				'avg_pct' => null,
				'min_pct' => null,
				'max_pct' => null,
			);
		}

		$row->total = (int) $row->total;

		return $row;
	}

	/*
	|--------------------------------------------------------------------------
	| نمایش
	|--------------------------------------------------------------------------
	*/

	/**
	 * رندر صفحه‌ی گزارش.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'شما اجازه‌ی دسترسی به این صفحه را ندارید.', 'ts-bnpl' ) );
		}

		$args    = self::request_args();
		$summary = self::get_summary();
		$total   = $summary->total;

		$max_page = $total > 0 ? (int) ceil( $total / self::PER_PAGE ) : 1;
		$paged    = min( $args['paged'], $max_page );
		$offset   = ( $paged - 1 ) * self::PER_PAGE;

		$rows = $total > 0 ? self::get_rows( $args['orderby'], $args['order'], self::PER_PAGE, $offset ) : array();

		$export_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => self::EXPORT_ACTION,
					'orderby' => $args['orderby'],
					'order'   => strtolower( $args['order'] ),
				),
				admin_url( 'admin-post.php' )
			),
			self::EXPORT_ACTION
		);
		?>
		<div class="wrap ts-bnpl-report">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'قیمت‌های اقساطی', 'ts-bnpl' ); ?></h1>
			<?php if ( $total > 0 ) : ?>
				<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'خروجی CSV', 'ts-bnpl' ); ?></a>
			<?php endif; ?>
			<hr class="wp-header-end">

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط برای نمایش پیام.
			if ( isset( $_GET['ts-bnpl-saved'] ) ) :
				?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'تنظیمات نمایش ذخیره شد.', 'ts-bnpl' ); ?></p></div>
			<?php endif; ?>

			<?php self::render_settings_box(); ?>

			<?php if ( 0 === $total ) : ?>
				<div class="ts-bnpl-report__empty notice notice-info inline">
					<p><?php esc_html_e( 'هنوز هیچ محصولی قیمت اقساطی ندارد.', 'ts-bnpl' ); ?></p>
				</div>
			<?php else : ?>

				<div class="ts-bnpl-report__summary">
					<?php
					self::render_stat( __( 'تعداد کل', 'ts-bnpl' ), number_format_i18n( $total ) );
					self::render_stat( __( 'میانگین اختلاف', 'ts-bnpl' ), self::format_percent( $summary->avg_pct ) );
					self::render_stat( __( 'کمترین اختلاف', 'ts-bnpl' ), self::format_percent( $summary->min_pct ) );
					self::render_stat( __( 'بیشترین اختلاف', 'ts-bnpl' ), self::format_percent( $summary->max_pct ) );
					?>
				</div>

				<table class="wp-list-table widefat striped ts-bnpl-report__table">
					<thead>
						<tr>
							<th scope="col" class="manage-column"><?php esc_html_e( 'محصول', 'ts-bnpl' ); ?></th>
							<th scope="col" class="manage-column"><?php esc_html_e( 'قیمت نقدی', 'ts-bnpl' ); ?></th>
							<th scope="col" class="manage-column"><?php esc_html_e( 'قیمت اقساطی', 'ts-bnpl' ); ?></th>
							<?php
							self::render_sortable_header( __( 'اختلاف', 'ts-bnpl' ), 'diff_amount', $args );
							self::render_sortable_header( __( 'اختلاف ٪', 'ts-bnpl' ), 'diff_percent', $args );
							?>
							<th scope="col" class="manage-column"><?php esc_html_e( 'هر قسط', 'ts-bnpl' ); ?></th>
							<th scope="col" class="manage-column"><?php esc_html_e( 'موجودی', 'ts-bnpl' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$flag       = self::row_flag( $row );
							$row_class  = '' !== $flag ? 'ts-bnpl-report__row--' . $flag : '';
							$is_var     = 'product_variation' === $row->post_type;
							$title      = $is_var && '' !== (string) $row->parent_title ? $row->parent_title : $row->post_title;
							$edit_id    = $is_var && $row->post_parent ? (int) $row->post_parent : (int) $row->id;
							$edit_link  = get_edit_post_link( $edit_id );
							$attributes = $is_var ? self::attribute_summary( $row ) : '';
							?>
							<tr class="<?php echo esc_attr( $row_class ); ?>">
								<td>
									<?php if ( $edit_link ) : ?>
										<a href="<?php echo esc_url( $edit_link ); ?>"><strong><?php echo esc_html( $title ); ?></strong></a>
									<?php else : ?>
										<strong><?php echo esc_html( $title ); ?></strong>
									<?php endif; ?>
									<?php if ( '' !== $attributes ) : ?>
										<div class="ts-bnpl-report__attrs"><?php echo esc_html( $attributes ); ?></div>
									<?php endif; ?>
									<div class="ts-bnpl-report__id">#<?php echo esc_html( (string) (int) $row->id ); ?></div>
								</td>
								<td><?php echo esc_html( self::format_money( $row->cash_price ) ); ?></td>
								<td><?php echo esc_html( self::format_money( $row->bnpl_price ) ); ?></td>
								<td>
									<?php if ( '' !== $flag && 'zero' !== $flag ) : ?>
										<span class="ts-bnpl-report__warn" aria-hidden="true">⚠</span>
									<?php endif; ?>
									<?php echo esc_html( 'unknown' === $flag ? '—' : self::format_money( $row->diff_amount ) ); ?>
								</td>
								<td><?php echo esc_html( self::format_percent( $row->diff_percent ) ); ?></td>
								<td><?php echo esc_html( self::format_money( TS_BNPL_Data::installment( $row->bnpl_price ) ) ); ?></td>
								<td><?php echo esc_html( self::stock_label( $row->stock_status ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php self::render_pagination( $total, $paged, $args ); ?>

			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * جعبه‌ی تنظیمات نمایش خرید اعتباری در صفحه‌ی محصول.
	 *
	 * @return void
	 */
	private static function render_settings_box() {
		$settings = TS_BNPL_Display::get_settings();
		?>
		<form class="ts-bnpl-report__settings" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::SETTINGS_ACTION ); ?>" />
			<?php wp_nonce_field( self::SETTINGS_ACTION ); ?>

			<h2><?php esc_html_e( 'نمایش در صفحه‌ی محصول', 'ts-bnpl' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'خرید نقدی همیشه گزینه‌ی اصلی صفحه می‌ماند. مبلغ قسط و نام دیجی‌پی فقط پس از کلیک کاربر نمایش داده می‌شود.', 'ts-bnpl' ); ?>
			</p>

			<fieldset class="ts-bnpl-report__modes">
				<label>
					<input type="radio" name="mode" value="<?php echo esc_attr( TS_BNPL_Display::MODE_ACCORDION ); ?>" <?php checked( $settings['mode'], TS_BNPL_Display::MODE_ACCORDION ); ?> />
					<strong><?php esc_html_e( 'بازشونده', 'ts-bnpl' ); ?></strong>
					<span><?php esc_html_e( 'با کلیک، جزئیات همان‌جا زیر متن باز می‌شود. راهنمای کامل با دکمه‌ی «؟».', 'ts-bnpl' ); ?></span>
				</label>
				<label>
					<input type="radio" name="mode" value="<?php echo esc_attr( TS_BNPL_Display::MODE_MODAL ); ?>" <?php checked( $settings['mode'], TS_BNPL_Display::MODE_MODAL ); ?> />
					<strong><?php esc_html_e( 'پنل راهنما', 'ts-bnpl' ); ?></strong>
					<span><?php esc_html_e( 'با کلیک، پنل راهنما باز می‌شود و جزئیات طرح بالای توضیحات نمایش داده می‌شود.', 'ts-bnpl' ); ?></span>
				</label>
				<label>
					<input type="radio" name="mode" value="<?php echo esc_attr( TS_BNPL_Display::MODE_LANDING ); ?>" <?php checked( $settings['mode'], TS_BNPL_Display::MODE_LANDING ); ?> />
					<strong><?php esc_html_e( 'صفحه‌ی فرود', 'ts-bnpl' ); ?></strong>
					<span><?php esc_html_e( 'با کلیک، کاربر به صفحه‌ی راهنمای کامل خرید اعتباری می‌رود. نیازمند انتخاب صفحه در بخش پایین.', 'ts-bnpl' ); ?></span>
				</label>
				<label>
					<input type="radio" name="mode" value="<?php echo esc_attr( TS_BNPL_Display::MODE_VISUAL_LANDING ); ?>" <?php checked( $settings['mode'], TS_BNPL_Display::MODE_VISUAL_LANDING ); ?> />
					<strong><?php esc_html_e( 'لندینگ تصویری (آزمایشی)', 'ts-bnpl' ); ?></strong>
					<span><?php esc_html_e( 'همان برگه‌ی فرود را با چیدمان تصویری مستقل نمایش می‌دهد و با بازگشت به حالت قبلی فوراً قابل برگشت است.', 'ts-bnpl' ); ?></span>
				</label>
			</fieldset>

			<p class="ts-bnpl-report__teaser-field">
				<label for="ts-bnpl-teaser"><strong><?php esc_html_e( 'متن قابل کلیک', 'ts-bnpl' ); ?></strong></label>
				<input type="text" id="ts-bnpl-teaser" name="teaser" class="large-text" value="<?php echo esc_attr( $settings['teaser'] ); ?>" />
				<span class="description"><?php esc_html_e( 'خالی بگذارید تا متن پیش‌فرض استفاده شود.', 'ts-bnpl' ); ?></span>
			</p>

			<hr />

			<h2><?php esc_html_e( 'صفحه‌ی فرود خرید اعتباری', 'ts-bnpl' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'یک برگه‌ی معمولی وردپرس بسازید و اینجا انتخابش کنید. افزونه محتوای کامل راهنما و کاروسل کالاهای واجد شرایط را روی همان یک برگه می‌سازد؛ لازم نیست چیزی داخلش بنویسید. عنوان برگه به‌عنوان تیتر صفحه استفاده می‌شود.', 'ts-bnpl' ); ?>
			</p>

			<p class="ts-bnpl-report__landing-field">
				<label for="ts-bnpl-landing-page"><strong><?php esc_html_e( 'برگه‌ی فرود', 'ts-bnpl' ); ?></strong></label>
				<?php
				wp_dropdown_pages(
					array(
						'name'              => 'landing_page',
						'id'                => 'ts-bnpl-landing-page',
						'selected'          => (int) $settings['landing_page'],
						'show_option_none'  => __( '— هیچ صفحه‌ای انتخاب نشده —', 'ts-bnpl' ),
						'option_none_value' => '0',
						'post_status'       => 'publish',
					)
				);
				?>
				<span class="description"><?php esc_html_e( 'با «هیچ صفحه‌ای»، هیچ برگه‌ای تغییر نمی‌کند و حالت صفحه‌ی فرود هم غیرفعال می‌ماند.', 'ts-bnpl' ); ?></span>
			</p>

			<?php
			$landing_url = TS_BNPL_Landing::get_url();

			if ( $landing_url ) :
				?>
				<p class="ts-bnpl-report__landing-link">
					<a href="<?php echo esc_url( $landing_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'دیدن صفحه‌ی فرود', 'ts-bnpl' ); ?>
					</a>
				</p>
			<?php endif; ?>

			<?php submit_button( __( 'ذخیره‌ی تنظیمات', 'ts-bnpl' ), 'secondary', 'submit', true ); ?>
		</form>
		<?php
	}

	/**
	 * ذخیره‌ی تنظیمات نمایش.
	 *
	 * @return void
	 */
	public static function handle_settings_save() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'شما اجازه‌ی دسترسی به این صفحه را ندارید.', 'ts-bnpl' ) );
		}

		check_admin_referer( self::SETTINGS_ACTION );

		$mode    = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';
		$teaser  = isset( $_POST['teaser'] ) ? sanitize_text_field( wp_unslash( $_POST['teaser'] ) ) : '';
		$landing = isset( $_POST['landing_page'] ) ? absint( wp_unslash( $_POST['landing_page'] ) ) : 0;

		// فقط یک برگه‌ی منتشرشده پذیرفته می‌شود.
		if ( $landing > 0 && ( 'page' !== get_post_type( $landing ) || 'publish' !== get_post_status( $landing ) ) ) {
			$landing = 0;
		}

		TS_BNPL_Display::update_settings( $mode, $teaser, $landing );

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'     => 'product',
					'page'          => self::PAGE_SLUG,
					'ts-bnpl-saved' => '1',
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * رندر یک کارت آمار در نوار خلاصه.
	 *
	 * @param string $label برچسب.
	 * @param string $value مقدار آماده‌ی نمایش.
	 *
	 * @return void
	 */
	private static function render_stat( $label, $value ) {
		printf(
			'<div class="ts-bnpl-report__stat"><span class="ts-bnpl-report__stat-label">%1$s</span><span class="ts-bnpl-report__stat-value">%2$s</span></div>',
			esc_html( $label ),
			esc_html( $value )
		);
	}

	/**
	 * رندر سرستون قابل مرتب‌سازی.
	 *
	 * @param string                                     $label   برچسب ستون.
	 * @param string                                     $key     کلید مرتب‌سازی.
	 * @param array{orderby:string,order:string,paged:int} $args درخواست جاری.
	 *
	 * @return void
	 */
	private static function render_sortable_header( $label, $key, $args ) {
		$is_current = $args['orderby'] === $key;
		$next_order = ( $is_current && 'ASC' === $args['order'] ) ? 'desc' : 'asc';
		$classes    = 'manage-column ' . ( $is_current ? 'sorted ' . strtolower( $args['order'] ) : 'sortable asc' );

		$url = add_query_arg(
			array(
				'post_type' => 'product',
				'page'      => self::PAGE_SLUG,
				'orderby'   => $key,
				'order'     => $next_order,
			),
			admin_url( 'edit.php' )
		);

		printf(
			'<th scope="col" class="%1$s"><a href="%2$s"><span>%3$s</span><span class="sorting-indicator"></span></a></th>',
			esc_attr( $classes ),
			esc_url( $url ),
			esc_html( $label )
		);
	}

	/**
	 * رندر صفحه‌بندی.
	 *
	 * @param int                                        $total کل ردیف‌ها.
	 * @param int                                        $paged صفحه‌ی جاری.
	 * @param array{orderby:string,order:string,paged:int} $args درخواست جاری.
	 *
	 * @return void
	 */
	private static function render_pagination( $total, $paged, $args ) {
		$max_page = (int) ceil( $total / self::PER_PAGE );

		if ( $max_page < 2 ) {
			return;
		}

		// %#% نباید از add_query_arg رد شود چون کدگذاری می‌شود.
		$base = add_query_arg(
			array(
				'post_type' => 'product',
				'page'      => self::PAGE_SLUG,
				'orderby'   => $args['orderby'],
				'order'     => strtolower( $args['order'] ),
			),
			admin_url( 'edit.php' )
		) . '&paged=%#%';

		$links = paginate_links(
			array(
				'base'      => $base,
				'format'    => '',
				'current'   => $paged,
				'total'     => $max_page,
				'prev_text' => '&rsaquo;',
				'next_text' => '&lsaquo;',
				'type'      => 'plain',
			)
		);

		if ( ! $links ) {
			return;
		}

		echo '<div class="tablenav bottom"><div class="tablenav-pages">';
		printf(
			'<span class="displaying-num">%s</span> ',
			esc_html(
				sprintf(
					/* translators: %s: تعداد کل موارد. */
					__( '%s مورد', 'ts-bnpl' ),
					number_format_i18n( $total )
				)
			)
		);
		echo '<span class="pagination-links">' . wp_kses_post( $links ) . '</span>';
		echo '</div></div>';
	}

	/*
	|--------------------------------------------------------------------------
	| خروجی CSV
	|--------------------------------------------------------------------------
	*/

	/**
	 * تولید و تحویل فایل CSV.
	 *
	 * @return void
	 */
	public static function handle_export() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'شما اجازه‌ی دسترسی به این صفحه را ندارید.', 'ts-bnpl' ) );
		}

		check_admin_referer( self::EXPORT_ACTION );

		$args = self::request_args();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=ts-bnpl-' . gmdate( 'Y-m-d' ) . '.csv' );

		$handle = fopen( 'php://output', 'w' );

		if ( false === $handle ) {
			wp_die( esc_html__( 'امکان تولید خروجی وجود ندارد.', 'ts-bnpl' ) );
		}

		// BOM تا اکسل فارسی را درست باز کند.
		fwrite( $handle, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		self::put_csv_row(
			$handle,
			array(
				__( 'شناسه', 'ts-bnpl' ),
				__( 'نوع', 'ts-bnpl' ),
				__( 'محصول', 'ts-bnpl' ),
				__( 'صفت‌ها', 'ts-bnpl' ),
				__( 'قیمت نقدی', 'ts-bnpl' ),
				__( 'قیمت اقساطی', 'ts-bnpl' ),
				__( 'اختلاف', 'ts-bnpl' ),
				__( 'اختلاف ٪', 'ts-bnpl' ),
				__( 'هر قسط', 'ts-bnpl' ),
				__( 'موجودی', 'ts-bnpl' ),
			)
		);

		$offset = 0;

		do {
			$rows = (array) self::get_rows( $args['orderby'], $args['order'], self::EXPORT_CHUNK, $offset );

			foreach ( $rows as $row ) {
				$is_var = 'product_variation' === $row->post_type;
				$title  = $is_var && '' !== (string) $row->parent_title ? $row->parent_title : $row->post_title;

				self::put_csv_row(
					$handle,
					array(
						(int) $row->id,
						$is_var ? __( 'متغیر', 'ts-bnpl' ) : __( 'ساده', 'ts-bnpl' ),
						$title,
						$is_var ? self::attribute_summary( $row ) : '',
						(float) $row->cash_price,
						(float) $row->bnpl_price,
						(float) $row->diff_amount,
						null === $row->diff_percent ? '' : round( (float) $row->diff_percent, 2 ),
						round( (float) TS_BNPL_Data::installment( $row->bnpl_price ), 2 ),
						self::stock_label( $row->stock_status ),
					)
				);
			}

			$offset += self::EXPORT_CHUNK;

			if ( function_exists( 'wp_cache_flush_runtime' ) ) {
				wp_cache_flush_runtime();
			}
		} while ( count( $rows ) === self::EXPORT_CHUNK );

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		exit;
	}

	/*
	|--------------------------------------------------------------------------
	| کمکی‌ها
	|--------------------------------------------------------------------------
	*/

	/**
	 * نوشتن یک ردیف CSV.
	 *
	 * پارامتر escape صراحتاً خالی داده می‌شود؛ هم مکانیزم غیراستاندارد بک‌اسلش
	 * را غیرفعال می‌کند و هم از اخطار منسوخ‌شدگی در PHP های جدید جلوگیری می‌کند.
	 *
	 * @param resource            $handle هندل خروجی.
	 * @param array<int,mixed>    $fields ستون‌ها.
	 *
	 * @return void
	 */
	private static function put_csv_row( $handle, array $fields ) {
		fputcsv( $handle, $fields, ',', '"', '' );
	}

	/**
	 * تعیین وضعیت هشدار یک ردیف.
	 *
	 * negative: قیمت اقساطی کمتر از نقدی است، تقریباً همیشه یعنی سینک اشتباه کرده.
	 * unknown : قیمت نقدی موجود نیست و مقایسه ممکن نیست.
	 * zero    : اختلاف صفر است.
	 *
	 * @param object $row ردیف کوئری.
	 *
	 * @return string یکی از negative، unknown، zero یا رشته‌ی خالی.
	 */
	private static function row_flag( $row ) {
		if ( null === $row->diff_percent ) {
			return 'unknown';
		}

		$diff = (float) $row->diff_amount;

		if ( $diff < 0 ) {
			return 'negative';
		}

		if ( 0.0 === $diff ) {
			return 'zero';
		}

		return '';
	}

	/**
	 * خلاصه‌ی صفت‌های یک متغیر.
	 *
	 * ووکامرس این خلاصه را در post_excerpt متغیر نگه می‌دارد، پس در همان
	 * کوئری اصلی خوانده می‌شود. اگر خالی بود (متغیرهای قدیمی که دوباره ذخیره
	 * نشده‌اند) فقط برای همان ردیف از آبجکت محصول خوانده می‌شود.
	 *
	 * @param object $row ردیف کوئری.
	 *
	 * @return string
	 */
	private static function attribute_summary( $row ) {
		$summary = trim( (string) $row->attribute_summary );

		if ( '' !== $summary ) {
			return $summary;
		}

		$variation = wc_get_product( (int) $row->id );

		if ( $variation instanceof WC_Product_Variation ) {
			return (string) $variation->get_attribute_summary();
		}

		return '';
	}

	/**
	 * قالب‌بندی مبلغ برای پیشخوان.
	 *
	 * عمداً ارقام لاتین استفاده می‌شود تا مقایسه‌ی ستونی و کپی به اکسل ساده بماند.
	 *
	 * @param float $amount مبلغ.
	 *
	 * @return string
	 */
	private static function format_money( $amount ) {
		return number_format( (float) $amount );
	}

	/**
	 * قالب‌بندی درصد.
	 *
	 * @param float|null $percent درصد.
	 *
	 * @return string
	 */
	private static function format_percent( $percent ) {
		if ( null === $percent ) {
			return '—';
		}

		return number_format( (float) $percent, 1 ) . '٪';
	}

	/**
	 * برچسب فارسی وضعیت موجودی.
	 *
	 * @param string|null $status وضعیت خام.
	 *
	 * @return string
	 */
	private static function stock_label( $status ) {
		switch ( (string) $status ) {
			case 'instock':
				return __( 'موجود', 'ts-bnpl' );
			case 'outofstock':
				return __( 'ناموجود', 'ts-bnpl' );
			case 'onbackorder':
				return __( 'پیش‌سفارش', 'ts-bnpl' );
			default:
				return '—';
		}
	}
}
