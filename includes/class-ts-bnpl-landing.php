<?php
/**
 * صفحه‌ی فرود خرید اعتباری.
 *
 * یک صفحه‌ی معمولی وردپرس که مدیر از تنظیمات افزونه انتخاب می‌کند و افزونه
 * محتوای لندینگ را روی همان یک صفحه رندر می‌کند. هیچ شناسه‌ای هاردکد نیست و
 * تا وقتی صفحه‌ای انتخاب نشده باشد، هیچ صفحه‌ای تغییر نمی‌کند.
 *
 * @package TS_BNPL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TS_BNPL_Landing
 */
class TS_BNPL_Landing {

	/**
	 * کلید تنظیمات صفحه‌ی فرود در آپشن مشترک افزونه.
	 */
	const SETTING = 'landing_page';

	/**
	 * لنگر بخش محصولات، برای CTAهای داخل صفحه.
	 */
	const PRODUCTS_ANCHOR = 'ts-bnpl-eligible';

	/**
	 * حداکثر تعداد محصول در کاروسل.
	 */
	const MAX_PRODUCTS = 20;

	/**
	 * ثبت هوک‌ها.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'render' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 20 );
	}

	/*
	|--------------------------------------------------------------------------
	| تنظیمات
	|--------------------------------------------------------------------------
	*/

	/**
	 * شناسه‌ی صفحه‌ی انتخاب‌شده.
	 *
	 * @return int صفر یعنی هیچ صفحه‌ای انتخاب نشده.
	 */
	public static function get_page_id() {
		$settings = TS_BNPL_Display::get_settings();
		$page_id  = isset( $settings[ self::SETTING ] ) ? absint( $settings[ self::SETTING ] ) : 0;

		return (int) apply_filters( 'ts_bnpl_landing_page_id', $page_id );
	}

	/**
	 * نشانی صفحه‌ی فرود.
	 *
	 * @return string رشته‌ی خالی اگر صفحه‌ای انتخاب نشده یا منتشر نشده باشد.
	 */
	public static function get_url() {
		$page_id = self::get_page_id();

		if ( $page_id <= 0 || 'publish' !== get_post_status( $page_id ) ) {
			return '';
		}

		return (string) get_permalink( $page_id );
	}

	/**
	 * آیا صفحه‌ی جاری همان صفحه‌ی فرود است؟
	 *
	 * @return bool
	 */
	public static function is_landing() {
		$page_id = self::get_page_id();

		return $page_id > 0 && is_page( $page_id );
	}

	/*
	|--------------------------------------------------------------------------
	| بارگذاری استایل
	|--------------------------------------------------------------------------
	*/

	/**
	 * استایل لندینگ فقط روی همان یک صفحه.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		if ( ! self::is_landing() ) {
			return;
		}

		wp_enqueue_style(
			'ts-bnpl-landing',
			TS_BNPL_URL . 'assets/css/ts-bnpl-landing.css',
			array(),
			TS_BNPL_VERSION
		);

		/*
		 * قالب سوایپر را فقط در بخش‌هایی که لازم دارد صف می‌کند و صفحه‌ی
		 * برگه‌ی معمولی جزوشان نیست. با همان هندل قالب صف می‌کنیم تا اگر
		 * جای دیگری هم لود شده باشد، دوباره بارگذاری نشود.
		 */
		$is_mobile = defined( 'IS_MOBILE' ) ? IS_MOBILE : wp_is_mobile();

		if ( $is_mobile || ! defined( 'THEME_ASSETS' ) ) {
			return;
		}

		if ( ! wp_style_is( 'swiper', 'enqueued' ) ) {
			wp_enqueue_style( 'swiper', THEME_ASSETS . 'plugins/swiper/swiper.min.css', array(), TS_BNPL_VERSION );
		}

		if ( ! wp_script_is( 'swiper', 'enqueued' ) ) {
			wp_enqueue_script( 'swiper', THEME_ASSETS . 'plugins/swiper/swiper.min.js', array(), TS_BNPL_VERSION, true );
		}

		wp_enqueue_script(
			'ts-bnpl-landing',
			TS_BNPL_URL . 'assets/js/ts-bnpl-landing.js',
			array( 'swiper' ),
			TS_BNPL_VERSION,
			true
		);
	}

	/*
	|--------------------------------------------------------------------------
	| رندر
	|--------------------------------------------------------------------------
	*/

	/**
	 * تزریق محتوای لندینگ در صفحه‌ی انتخاب‌شده.
	 *
	 * هدر، فوتر، بردکرامپ و عنوان صفحه از خود قالب می‌آیند؛ این متد فقط بدنه
	 * را می‌سازد. محتوای دستی خود صفحه بالای لندینگ حفظ می‌شود تا مدیر بتواند
	 * مقدمه‌ی دلخواهش را اضافه کند.
	 *
	 * @param string $content محتوای اصلی صفحه.
	 *
	 * @return string
	 */
	public static function render( $content ) {
		if ( ! self::is_landing() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		// جلوگیری از رندر دوباره اگر قالب the_content را چند بار صدا بزند.
		static $done = false;

		if ( $done ) {
			return $content;
		}

		$done = true;

		return $content . self::landing_html();
	}

	/**
	 * مارک‌آپ کامل لندینگ.
	 *
	 * @return string
	 */
	public static function landing_html() {
		ob_start();
		?>
		<div class="ts-bnpl-landing" dir="rtl">
			<?php
			self::section_hero();
			self::section_steps();
			self::section_identify();
			self::section_products();
			self::section_pricing();
			self::section_faq();
			self::section_cta();
			?>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * هیرو.
	 *
	 * عمداً بدون h1 است؛ عنوان صفحه را خود قالب چاپ می‌کند.
	 *
	 * @return void
	 */
	private static function section_hero() {
		?>
		<section class="ts-bnpl-landing__hero">
			<p class="ts-bnpl-landing__eyebrow"><?php esc_html_e( 'خرید اعتباری', 'ts-bnpl' ); ?></p>

			<h2 class="ts-bnpl-landing__headline">
				<?php esc_html_e( 'کالای مورد نظرتان را امروز تحویل بگیرید، هزینه‌اش را در طول زمان بپردازید.', 'ts-bnpl' ); ?>
			</h2>

			<p class="ts-bnpl-landing__lede">
				<?php esc_html_e( 'در تهران‌اسپیکر بخشی از کالاها را می‌توانید به‌جای پرداخت نقدی، از طریق روش‌های پرداخت اعتباری خریداری کنید. کالا مثل هر خرید دیگری برایتان ارسال می‌شود و پرداخت آن طبق شرایط روش اعتباری انتخابی شما انجام می‌گیرد.', 'ts-bnpl' ); ?>
			</p>

			<div class="ts-bnpl-landing__actions">
				<a class="ts-bnpl-landing__btn ts-bnpl-landing__btn--primary" href="#<?php echo esc_attr( self::PRODUCTS_ANCHOR ); ?>">
					<?php esc_html_e( 'دیدن کالاهای واجد شرایط', 'ts-bnpl' ); ?>
				</a>
				<a class="ts-bnpl-landing__btn ts-bnpl-landing__btn--ghost" href="#ts-bnpl-how">
					<?php esc_html_e( 'مراحل خرید', 'ts-bnpl' ); ?>
				</a>
			</div>
		</section>
		<?php
	}

	/**
	 * مراحل خرید.
	 *
	 * @return void
	 */
	private static function section_steps() {
		$steps = array(
			array(
				'title' => __( 'کالای واجد شرایط را انتخاب کنید', 'ts-bnpl' ),
				'text'  => __( 'در صفحه‌ی هر کالا، اگر امکان خرید اعتباری وجود داشته باشد، نشان آن نزدیک دکمه‌ی «افزودن به سبد خرید» دیده می‌شود.', 'ts-bnpl' ),
			),
			array(
				'title' => __( 'کالا را به سبد خرید اضافه کنید', 'ts-bnpl' ),
				'text'  => __( 'هیچ مسیر جداگانه‌ای لازم نیست؛ درست مثل یک خرید معمولی کالا را به سبد اضافه کنید.', 'ts-bnpl' ),
			),
			array(
				'title' => __( 'به صفحه‌ی تسویه‌حساب بروید', 'ts-bnpl' ),
				'text'  => __( 'اطلاعات گیرنده و نشانی تحویل را مثل همیشه کامل کنید.', 'ts-bnpl' ),
			),
			array(
				'title' => __( 'روش پرداخت اعتباری را انتخاب کنید', 'ts-bnpl' ),
				'text'  => __( 'در بخش پرداخت، به‌جای پرداخت نقدی یکی از روش‌های اعتباری فعال را انتخاب کنید و مراحل را دنبال کنید.', 'ts-bnpl' ),
			),
		);
		?>
		<section class="ts-bnpl-landing__section" id="ts-bnpl-how">
			<h2 class="ts-bnpl-landing__title"><?php esc_html_e( 'خرید اعتباری چطور انجام می‌شود؟', 'ts-bnpl' ); ?></h2>
			<p class="ts-bnpl-landing__intro">
				<?php esc_html_e( 'کل مسیر همان مسیر خرید همیشگی است و فقط در گام آخر، روش پرداخت را تغییر می‌دهید.', 'ts-bnpl' ); ?>
			</p>

			<ol class="ts-bnpl-landing__steps">
				<?php foreach ( $steps as $index => $step ) : ?>
					<li class="ts-bnpl-landing__step">
						<span class="ts-bnpl-landing__step-num" aria-hidden="true"><?php echo esc_html( self::to_persian_digits( (string) ( $index + 1 ) ) ); ?></span>
						<h3 class="ts-bnpl-landing__step-title"><?php echo esc_html( $step['title'] ); ?></h3>
						<p class="ts-bnpl-landing__step-text"><?php echo esc_html( $step['text'] ); ?></p>
					</li>
				<?php endforeach; ?>
			</ol>

			<p class="ts-bnpl-landing__note">
				<?php esc_html_e( 'روش‌های پرداخت اعتباریِ در دسترس، به سرویس‌هایی بستگی دارد که در زمان خرید روی سایت فعال هستند. فهرست کامل را در همان مرحله‌ی پرداخت می‌بینید.', 'ts-bnpl' ); ?>
			</p>
		</section>
		<?php
	}

	/**
	 * تشخیص کالای واجد شرایط.
	 *
	 * @return void
	 */
	private static function section_identify() {
		?>
		<section class="ts-bnpl-landing__section">
			<h2 class="ts-bnpl-landing__title"><?php esc_html_e( 'از کجا بفهمم یک کالا قابل خرید اعتباری است؟', 'ts-bnpl' ); ?></h2>

			<div class="ts-bnpl-landing__split">
				<div class="ts-bnpl-landing__split-text">
					<p>
						<?php esc_html_e( 'لازم نیست جایی را جست‌وجو کنید. کافی است وارد صفحه‌ی کالا شوید: اگر آن کالا امکان خرید اعتباری داشته باشد، نزدیک قیمت و دکمه‌ی افزودن به سبد خرید، نشان خرید اعتباری نمایش داده می‌شود.', 'ts-bnpl' ); ?>
					</p>
					<p>
						<?php esc_html_e( 'اگر این نشان را نمی‌بینید، یعنی آن کالا در حال حاضر فقط به‌صورت نقدی قابل خرید است. فهرست کالاهای واجد شرایط ثابت نیست و به مرور تغییر می‌کند.', 'ts-bnpl' ); ?>
					</p>
					<p>
						<?php esc_html_e( 'برای کالاهایی که چند مدل یا رنگ دارند، این امکان ممکن است فقط روی بعضی از مدل‌ها فعال باشد؛ پس بعد از انتخاب مدل دوباره نگاه کنید.', 'ts-bnpl' ); ?>
					</p>
				</div>

				<?php /* نمونه‌ی تزئینی نشان، صرفاً برای آشنایی چشم کاربر. */ ?>
				<div class="ts-bnpl-landing__sample" aria-hidden="true">
					<span class="ts-bnpl-landing__sample-label"><?php esc_html_e( 'نمونه‌ی نشان در صفحه‌ی کالا', 'ts-bnpl' ); ?></span>
					<span class="ts-bnpl-landing__sample-chip">
						<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false">
							<rect x="2.5" y="5" width="19" height="14" rx="2.5"></rect>
							<path d="M2.5 10h19M6 14.5h3"></path>
						</svg>
						<?php esc_html_e( 'امکان خرید اعتباری این کالا', 'ts-bnpl' ); ?>
					</span>
				</div>
			</div>
		</section>
		<?php
	}

	/*
	|--------------------------------------------------------------------------
	| کاروسل کالاهای واجد شرایط
	|--------------------------------------------------------------------------
	*/

	/**
	 * شناسه‌ی کالاهایی که در حال حاضر قابل خرید اعتباری‌اند.
	 *
	 * همان منطق صفحه‌ی محصول: متای TS_BNPL_Data::META با مقدار بزرگ‌تر از صفر.
	 * برای کالاهای متغیر، متا روی خودِ متغیر است، پس شناسه‌ی والد برگردانده
	 * می‌شود تا کارت محصول درست رندر شود.
	 *
	 * @return array<int,int>
	 */
	public static function get_eligible_product_ids() {
		global $wpdb;

		/*
		 * سقف عمدی: کاروسل حداکثر MAX_PRODUCTS کارت نشان می‌دهد، پس کشیدن کل
		 * جدول بی‌فایده است. بافر می‌گذاریم تا اگر فیلتر زیر چیزی را کنار
		 * گذاشت، باز هم کاروسل پر بماند.
		 */
		$limit = self::MAX_PRODUCTS * 5;

		$sql = "
			SELECT
				CASE WHEN p.post_type = 'product_variation' THEN p.post_parent ELSE p.ID END AS product_id,
				MAX( p.post_date ) AS sort_date
			FROM {$wpdb->postmeta} AS pm
			INNER JOIN {$wpdb->posts} AS p ON p.ID = pm.post_id
			LEFT JOIN {$wpdb->posts} AS parent ON parent.ID = p.post_parent
			WHERE pm.meta_key = %s
				AND pm.meta_value + 0 > 0
				AND p.post_type IN ( 'product', 'product_variation' )
				AND (
					( p.post_type = 'product' AND p.post_status = 'publish' )
					OR ( p.post_type = 'product_variation' AND parent.post_status = 'publish' )
				)
			GROUP BY product_id
			ORDER BY sort_date DESC
			LIMIT %d
		";

		$rows = $wpdb->get_col( $wpdb->prepare( $sql, TS_BNPL_Data::META, $limit ) ); // phpcs:ignore WordPress.DB -- کوئری آماده؛ کش عمداً ندارد تا فهرست همیشه تازه بماند.
		$ids  = array_values( array_unique( array_filter( array_map( 'absint', (array) $rows ) ) ) );

		return (array) apply_filters( 'ts_bnpl_eligible_product_ids', $ids );
	}

	/**
	 * بخش کالاهای واجد شرایط.
	 *
	 * کارت‌ها عمداً همان simple-card.php قالب هستند تا با بقیه‌ی سایت یکی
	 * باشند. هیچ نشان یا کلاس اضافه‌ای به کارت اضافه نمی‌شود؛ عنوان بخش
	 * خودش گویاست.
	 *
	 * @return void
	 */
	private static function section_products() {
		$ids = self::get_eligible_product_ids();
		?>
		<section class="ts-bnpl-landing__section ts-bnpl-landing__section--products" id="<?php echo esc_attr( self::PRODUCTS_ANCHOR ); ?>">
			<h2 class="ts-bnpl-landing__title"><?php esc_html_e( 'محصولاتی که قابلیت خرید اعتباری دارند', 'ts-bnpl' ); ?></h2>

			<?php
			if ( empty( $ids ) ) {
				?>
				<p class="ts-bnpl-landing__empty">
					<?php esc_html_e( 'در حال حاضر کالایی برای خرید اعتباری فعال نیست. این فهرست به مرور به‌روز می‌شود؛ کمی بعد دوباره سر بزنید.', 'ts-bnpl' ); ?>
				</p>
				</section>
				<?php
				return;
			}

			$query = new WP_Query(
				array(
					'post_type'              => 'product',
					'post__in'               => $ids,
					'posts_per_page'         => self::MAX_PRODUCTS,
					'orderby'                => 'post__in',
					'post_status'            => 'publish',
					'ignore_sticky_posts'    => true,
					'no_found_rows'          => true,
					'update_post_term_cache' => false,
				)
			);

			if ( ! $query->have_posts() ) {
				wp_reset_postdata();
				?>
				<p class="ts-bnpl-landing__empty">
					<?php esc_html_e( 'در حال حاضر کالایی برای خرید اعتباری فعال نیست. این فهرست به مرور به‌روز می‌شود؛ کمی بعد دوباره سر بزنید.', 'ts-bnpl' ); ?>
				</p>
				</section>
				<?php
				return;
			}

			$card      = self::card_template();
			$is_mobile = defined( 'IS_MOBILE' ) ? IS_MOBILE : wp_is_mobile();
			$slider_id = 'ts-bnpl-products-carousel';
			?>

			<div class="ts-bnpl-landing__carousel<?php echo $is_mobile ? ' is-mobile' : ''; ?>">
				<?php if ( ! $is_mobile ) : ?>
					<div class="products-carousel swiper" id="<?php echo esc_attr( $slider_id ); ?>">
						<div class="swiper-wrapper">
							<?php
							while ( $query->have_posts() ) :
								$query->the_post();
								global $product;
								?>
								<div class="swiper-slide">
									<?php
									if ( $card ) {
										require $card;
									}
									?>
								</div>
								<?php
							endwhile;
							?>
						</div>
					</div>
					<div class="wbs-products-nav wbs-prev" id="<?php echo esc_attr( $slider_id ); ?>-prev"><i class="icon-arrow-right"></i></div>
					<div class="wbs-products-nav wbs-next" id="<?php echo esc_attr( $slider_id ); ?>-next"><i class="icon-arrow-left"></i></div>
				<?php else : ?>
					<div class="products-carousel">
						<?php
						while ( $query->have_posts() ) :
							$query->the_post();
							global $product;
							?>
							<div class="item">
								<?php
								if ( $card ) {
									require $card;
								}
								?>
							</div>
							<?php
						endwhile;
						?>
					</div>
				<?php endif; ?>
			</div>
			<?php
			wp_reset_postdata();
			?>
		</section>
		<?php
	}

	/**
	 * مسیر کارت محصول قالب.
	 *
	 * عمداً کارت تازه‌ای ساخته نمی‌شود؛ همان فایلی که آرشیو، جست‌وجو و صفحه‌ی
	 * اصلی استفاده می‌کنند require می‌شود تا ظاهر و رفتار یکی بماند. اگر قالب
	 * عوض شود و فایل نباشد، بخش بی‌سروصدا خالی می‌ماند.
	 *
	 * @return string مسیر فایل یا رشته‌ی خالی.
	 */
	private static function card_template() {
		if ( ! defined( 'THEME_COMPONENTS' ) ) {
			return '';
		}

		$is_mobile = defined( 'IS_MOBILE' ) ? IS_MOBILE : wp_is_mobile();
		$candidates = $is_mobile
			? array( 'product-cards/simple-card-mobile.php', 'product-cards/simple-card.php' )
			: array( 'product-cards/simple-card.php' );

		foreach ( $candidates as $candidate ) {
			$path = THEME_COMPONENTS . $candidate;

			if ( file_exists( $path ) ) {
				return $path;
			}
		}

		return '';
	}

	/**
	 * هشدار قیمت.
	 *
	 * @return void
	 */
	private static function section_pricing() {
		?>
		<section class="ts-bnpl-landing__section">
			<div class="ts-bnpl-landing__callout">
				<h2 class="ts-bnpl-landing__callout-title"><?php esc_html_e( 'درباره‌ی مبلغ نهایی', 'ts-bnpl' ); ?></h2>
				<p>
					<?php esc_html_e( 'قیمتی که در صفحه‌ی کالا می‌بینید، قیمت خرید نقدی است. اگر پرداخت را با یک روش اعتباری انجام دهید، مبلغ نهایی می‌تواند با این عدد متفاوت باشد.', 'ts-bnpl' ); ?>
				</p>
				<p>
					<?php esc_html_e( 'مبلغ دقیق و شرایط پرداخت، در مرحله‌ی تسویه‌حساب و بر اساس روش اعتباری‌ای که انتخاب می‌کنید مشخص و به شما نمایش داده می‌شود. پیش از تأیید نهایی، فرصت دارید مبلغ و شرایط را ببینید و در صورت تمایل به پرداخت نقدی برگردید.', 'ts-bnpl' ); ?>
				</p>
			</div>
		</section>
		<?php
	}

	/**
	 * پرسش‌های پرتکرار.
	 *
	 * @return void
	 */
	private static function section_faq() {
		$faq = array(
			array(
				'q' => __( 'آیا همه‌ی کالاهای سایت را می‌شود اعتباری خرید؟', 'ts-bnpl' ),
				'a' => __( 'خیر. فقط کالاهایی که در صفحه‌شان نشان خرید اعتباری دارند. این فهرست به مرور به‌روز می‌شود.', 'ts-bnpl' ),
			),
			array(
				'q' => __( 'برای خرید اعتباری باید مسیر جداگانه‌ای بروم؟', 'ts-bnpl' ),
				'a' => __( 'نه. کالا را مثل همیشه به سبد خرید اضافه می‌کنید و فقط در مرحله‌ی پرداخت، به‌جای پرداخت نقدی یکی از روش‌های اعتباری را انتخاب می‌کنید.', 'ts-bnpl' ),
			),
			array(
				'q' => __( 'اگر سبد خرید من چند کالا داشته باشد چه می‌شود؟', 'ts-bnpl' ),
				'a' => __( 'برای استفاده از پرداخت اعتباری لازم است همه‌ی کالاهای داخل سبد واجد شرایط باشند. اگر کالایی در سبد این امکان را نداشته باشد، روش‌های اعتباری در مرحله‌ی پرداخت نمایش داده نمی‌شوند.', 'ts-bnpl' ),
			),
			array(
				'q' => __( 'شرایط و مبلغ نهایی را کجا می‌بینم؟', 'ts-bnpl' ),
				'a' => __( 'در مرحله‌ی پرداخت و پس از انتخاب روش اعتباری. تا پیش از تأیید نهایی، هیچ پرداختی انجام نمی‌شود.', 'ts-bnpl' ),
			),
			array(
				'q' => __( 'ارسال و گارانتی کالا فرقی می‌کند؟', 'ts-bnpl' ),
				'a' => __( 'خیر. کالا، اصالت، گارانتی، بسته‌بندی و روند ارسال دقیقاً مثل خرید نقدی است. تنها تفاوت، روش پرداخت است.', 'ts-bnpl' ),
			),
			array(
				'q' => __( 'اگر روش اعتباری در مرحله‌ی پرداخت نبود چه کنم؟', 'ts-bnpl' ),
				'a' => __( 'یعنی در آن لحظه برای سبد شما در دسترس نیست. می‌توانید سبد را بازبینی کنید یا خرید را به‌صورت نقدی ادامه دهید.', 'ts-bnpl' ),
			),
		);
		?>
		<section class="ts-bnpl-landing__section">
			<h2 class="ts-bnpl-landing__title"><?php esc_html_e( 'پرسش‌های پرتکرار', 'ts-bnpl' ); ?></h2>

			<div class="ts-bnpl-landing__faq">
				<?php foreach ( $faq as $entry ) : ?>
					<details class="ts-bnpl-landing__faq-item">
						<summary class="ts-bnpl-landing__faq-q">
							<span><?php echo esc_html( $entry['q'] ); ?></span>
							<span class="ts-bnpl-landing__faq-icon" aria-hidden="true">
								<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
									<path d="m6 9 6 6 6-6"></path>
								</svg>
							</span>
						</summary>
						<p class="ts-bnpl-landing__faq-a"><?php echo esc_html( $entry['a'] ); ?></p>
					</details>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * فراخوان پایانی.
	 *
	 * @return void
	 */
	private static function section_cta() {
		$shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';
		?>
		<section class="ts-bnpl-landing__section">
			<div class="ts-bnpl-landing__final">
				<h2 class="ts-bnpl-landing__final-title"><?php esc_html_e( 'آماده‌ی شروع هستید؟', 'ts-bnpl' ); ?></h2>
				<p><?php esc_html_e( 'کالاهای واجد شرایط را ببینید و خریدتان را مثل همیشه شروع کنید.', 'ts-bnpl' ); ?></p>
				<div class="ts-bnpl-landing__actions">
					<a class="ts-bnpl-landing__btn ts-bnpl-landing__btn--primary" href="#<?php echo esc_attr( self::PRODUCTS_ANCHOR ); ?>">
						<?php esc_html_e( 'کالاهای واجد شرایط', 'ts-bnpl' ); ?>
					</a>
					<?php if ( $shop ) : ?>
						<a class="ts-bnpl-landing__btn ts-bnpl-landing__btn--ghost" href="<?php echo esc_url( $shop ); ?>">
							<?php esc_html_e( 'همه‌ی محصولات فروشگاه', 'ts-bnpl' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</section>
		<?php
	}

	/**
	 * تبدیل ارقام لاتین به فارسی.
	 *
	 * @param string $value ورودی.
	 *
	 * @return string
	 */
	private static function to_persian_digits( $value ) {
		return str_replace(
			array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ),
			array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ),
			(string) $value
		);
	}
}
