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
	 * پارامتر نشانی که آرشیو فروشگاه را به کالاهای اعتباری محدود می‌کند.
	 */
	const QUERY_FLAG = 'ts_bnpl';

	/**
	 * ثبت هوک‌ها.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'render' ), 20 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 20 );
		add_filter( 'woocommerce_product_data_store_cpt_get_products_query', array( __CLASS__, 'filter_shop_query' ), 20, 2 );
	}

	/**
	 * کلاس نشانه‌ی صفحه‌ی فرود روی تگ body.
	 *
	 * قالب هرچه داخل `.page-content` باشد را «متن ویرایشگر» فرض می‌کند و با
	 * قانون‌های !important به p، h2..h6، a و img دست می‌زند (فایل
	 * assets/scss/components/_content-global.scss که در page.css می‌نشیند).
	 * لندینگ از the_content تزریق می‌شود، پس دقیقاً وسط همان قانون‌ها می‌افتد.
	 *
	 * این کلاس به شیت لندینگ اجازه می‌دهد آن لایه را با انتخابگر سه‌کلاسه
	 * خنثی کند؛ یعنی مشخصه‌اش قطعاً از (0,2,2) آن قانون‌ها بالاتر است و به
	 * ترتیب چاپ فایل‌ها وابسته نمی‌ماند.
	 *
	 * @param string[] $classes کلاس‌های فعلی body.
	 *
	 * @return string[]
	 */
	public static function body_class( $classes ) {
		if ( self::should_render_legacy() ) {
			$classes[] = 'ts-bnpl-landing-page';
		}

		return $classes;
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

	/**
	 * آیا نسخه‌ی قدیمی لندینگ باید روی صفحه‌ی انتخاب‌شده رندر شود؟
	 *
	 * بازدید مستقیم صفحه در حالت‌های بازشونده و پنل، رفتار فعلی خود را حفظ
	 * می‌کند. فقط حالت تصویری مالک رندر همان صفحه می‌شود.
	 *
	 * @return bool
	 */
	public static function should_render_legacy() {
		return self::is_landing() && TS_BNPL_Display::MODE_VISUAL_LANDING !== TS_BNPL_Display::get_mode();
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
		if ( ! self::should_render_legacy() ) {
			return;
		}

		/*
		 * اول دارایی‌های قالب، بعد شیت لندینگ.
		 *
		 * شیت لندینگ لایه‌ی «متن برگه» قالب را خنثی می‌کند، پس باید بعد از
		 * همه‌ی شیت‌هایی چاپ شود که این صفحه از قالب می‌گیرد. مشخصه‌ی
		 * انتخابگرها خودش این کار را می‌کند، ولی وابستگی صریح باعث می‌شود
		 * ترتیب چاپ هم دیگر تصادفی نباشد.
		 */
		$deps = self::enqueue_theme_card_assets();

		wp_enqueue_style(
			'ts-bnpl-landing',
			TS_BNPL_URL . 'assets/css/ts-bnpl-landing.css',
			$deps,
			TS_BNPL_VERSION
		);
	}

	/**
	 * استایل‌های کارت محصول و کاروسل از خود قالب.
	 *
	 * قالب این فایل‌ها را فقط در آرشیو و صفحه‌هایی که ساختار page-builder
	 * دارند صف می‌کند، و برگه‌ی ساده جزوشان نیست. بدون آن‌ها کارت محصول
	 * بی‌استایل رندر می‌شود و رنگ‌هایش را از جای اشتباه به ارث می‌برد — همان
	 * چیزی که در حالت تاریک متن را سبز نشان می‌داد.
	 *
	 * عمداً هیچ استایل کارتی اینجا بازنویسی نمی‌شود؛ فقط همان فایل‌های قالب با
	 * همان هندل‌ها صف می‌شوند تا اگر جای دیگری هم لود شده باشند تکرار نشوند.
	 *
	 * @return string[] هندل شیت‌هایی که این صفحه از قالب می‌گیرد، تا شیت
	 *                  لندینگ بتواند به آن‌ها وابسته شود و بعدشان چاپ شود.
	 */
	public static function enqueue_theme_card_assets() {
		if ( ! defined( 'THEME_ASSETS' ) || ! defined( 'THEME_LIB' ) ) {
			return array();
		}

		$is_mobile = defined( 'IS_MOBILE' ) ? IS_MOBILE : wp_is_mobile();
		$theme_dir = get_template_directory();

		/*
		 * شیت‌های خود مسیر برگه هم در فهرست وابستگی می‌آیند.
		 *
		 * «Page» همان فایلی است که لایه‌ی متن ویرایشگر را روی .page-content
		 * می‌گذارد و شیت لندینگ آن را خنثی می‌کند؛ پس باید قبل از لندینگ چاپ
		 * شود، نه بعدش.
		 */
		$deps = array();

		foreach ( array( 'style', 'Page' ) as $handle ) {
			if ( wp_style_is( $handle, 'enqueued' ) ) {
				$deps[] = $handle;
			}
		}

		// کارت محصول: همان فایلی که آرشیو فروشگاه استفاده می‌کند.
		$archive_rel = $is_mobile
			? 'lib/Archive/assets/scss/archiveModularMobile.css'
			: 'lib/Archive/assets/scss/archiveModular.css';

		if ( file_exists( $theme_dir . '/' . $archive_rel ) ) {
			if ( ! wp_style_is( 'archive', 'enqueued' ) ) {
				wp_enqueue_style( 'archive', get_template_directory_uri() . '/' . $archive_rel, array(), TS_BNPL_VERSION );
			}

			$deps[] = 'archive';
		}

		/*
		 * اندازه و فاصله‌ی اسلایدها؛ به .products-carousel-panel اسکوپ شده است.
		 *
		 * هندل عمداً «products-carousel» است، همان اسمی که مسیر محصول و مسیر
		 * ۴۰۴ قالب برای همین فایل به کار می‌برند. هندلِ مفردِ قبلی با آن‌ها یکی
		 * نبود و اگر هر دو در یک صفحه فعال می‌شدند، فایل دو بار می‌آمد.
		 */
		$module_rel = 'assets/scss/modules/product-carousel.css';

		if ( file_exists( $theme_dir . '/' . $module_rel ) ) {
			if ( ! wp_style_is( 'products-carousel', 'enqueued' ) && ! wp_style_is( 'product-carousel', 'enqueued' ) ) {
				wp_enqueue_style( 'products-carousel', THEME_ASSETS . 'scss/modules/product-carousel.css', array(), TS_BNPL_VERSION );
			}

			$deps[] = 'products-carousel';
		}

		// روی موبایل کاروسل یک اسکرول افقی ساده است و سوایپر لازم ندارد.
		if ( $is_mobile ) {
			return array_values( array_unique( array_filter( $deps, 'wp_style_is' ) ) );
		}

		/*
		 * سوایپر باید حتماً بیلد bundle باشد، نه هسته‌ی خالی.
		 *
		 * مسیر Page قالب روی هر برگه‌ی ساده، هندل «swiper» را به
		 * assets/plugins/swiper/swiper.min.js وصل می‌کند. آن بیلد فقط Resize و
		 * Observer را نصب می‌کند و ماژول Navigation ندارد، پس دکمه‌های
		 * قبلی/بعدی کاروسل هیچ کاری نمی‌کنند. چون آن هندل از قبل در صف بود،
		 * شرطِ «اگر صف نشده صف کن» هرگز اجرا نمی‌شد و افزونه در عمل با همان
		 * هسته‌ی خالی کار می‌کرد.
		 *
		 * پس به‌جای شرط، خود هندل روی بیلد درست نشانده می‌شود — همان فایلی که
		 * صفحه‌ی محصول با همین اسکریپت کاروسل می‌دهد. جای‌گزینی فقط روی همین
		 * یک برگه اتفاق می‌افتد و هر وابسته‌ای به هندل «swiper» هم درست
		 * می‌ماند، چون اسم هندل عوض نمی‌شود.
		 */
		$swiper_css = 'lib/Archive/assets/plugins/swiper/swiper-bundle.min.css';
		$swiper_js  = 'lib/Archive/assets/plugins/swiper/swiper-bundle.min.js';

		if ( file_exists( $theme_dir . '/' . $swiper_css ) ) {
			if ( ! wp_style_is( 'swiper', 'enqueued' ) ) {
				wp_enqueue_style( 'swiper', get_template_directory_uri() . '/' . $swiper_css, array(), TS_BNPL_VERSION );
			}

			$deps[] = 'swiper';
		}

		if ( file_exists( $theme_dir . '/' . $swiper_js ) ) {
			$bundle_url = get_template_directory_uri() . '/' . $swiper_js;
			$registered = wp_scripts()->query( 'swiper', 'registered' );

			if ( $registered && $bundle_url !== $registered->src ) {
				wp_deregister_script( 'swiper' );
			}

			if ( ! wp_script_is( 'swiper', 'registered' ) ) {
				wp_register_script( 'swiper', $bundle_url, array( 'jquery' ), TS_BNPL_VERSION, true );
			}

			wp_enqueue_script( 'swiper' );
		}

		/*
		 * اسکریپت کاروسل خود قالب، نه یک کپی از تنظیماتش.
		 *
		 * آن فایل عمداً slidesPerView سراسری ندارد و اندازه‌ی اسلاید را به
		 * breakpointها می‌سپارد؛ دقیقاً همان چیزی که صفحه‌ی محصول هم می‌دهد.
		 */
		$carousel_rel = 'assets/js/modular/product-carousel.min.js';

		if ( ! wp_script_is( 'product-carousel', 'enqueued' ) && file_exists( $theme_dir . '/' . $carousel_rel ) ) {
			wp_enqueue_script( 'product-carousel', THEME_ASSETS . 'js/modular/product-carousel.min.js', array( 'jquery', 'swiper' ), TS_BNPL_VERSION, true );
		}

		return array_values( array_unique( array_filter( $deps, 'wp_style_is' ) ) );
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
		if ( ! self::should_render_legacy() || ! in_the_loop() || ! is_main_query() ) {
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
			<?php
			/*
			 * لوگوی گرد خود سایت، به‌عنوان گرافیک پس‌زمینه. عمداً aria-hidden و
			 * pointer-events:none است و در جریان چیدمان قرار نمی‌گیرد، پس متن
			 * هیرو را جابه‌جا نمی‌کند. بخشی از آن بیرون کادر می‌افتد و هیرو
			 * با overflow آن را می‌بُرد.
			 */
			$logo = self::hero_logo_url();

			if ( $logo ) :
				?>
				<span class="ts-bnpl-landing__glyph" aria-hidden="true">
					<img src="<?php echo esc_url( $logo ); ?>" alt="" width="440" height="440" loading="lazy" decoding="async" />
				</span>
			<?php endif; ?>

			<p class="ts-bnpl-landing__eyebrow"><?php esc_html_e( 'خرید اعتباری', 'ts-bnpl' ); ?></p>

			<h2 class="ts-bnpl-landing__headline">
				<?php esc_html_e( 'کالای مورد نظرتان را امروز تحویل بگیرید، هزینه‌اش را در طول زمان بپردازید.', 'ts-bnpl' ); ?>
			</h2>

			<p class="ts-bnpl-landing__lede">
				<?php esc_html_e( 'در تهران‌اسپیکر بخشی از کالاها را می‌توانید به‌جای پرداخت نقدی، از طریق روش‌های پرداخت اعتباری خریداری کنید. کالا مثل هر خرید دیگری برایتان ارسال می‌شود و پرداخت آن طبق شرایط روش اعتباری انتخابی شما انجام می‌گیرد.', 'ts-bnpl' ); ?>
			</p>

			<?php $shop = self::shop_url(); ?>

			<div class="ts-bnpl-landing__actions">
				<?php /* فهرست کامل در آرشیو فروشگاه باز می‌شود، با مرتب‌سازی و فیلترهای همیشگی. */ ?>
				<a class="wbs-button button-no-hover ts-bnpl-landing__btn" href="<?php echo esc_url( $shop ? $shop : '#' . self::PRODUCTS_ANCHOR ); ?>">
					<?php esc_html_e( 'مشاهده محصولاتی که قابلیت خرید اعتباری دارند', 'ts-bnpl' ); ?>
				</a>
				<a class="wbs-button button-simple button-no-hover ts-bnpl-landing__btn ts-bnpl-landing__btn--simple" href="#ts-bnpl-how">
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
	public static function get_eligible_product_ids( $limit = null ) {
		global $wpdb;

		/*
		 * پیش‌فرض برای کاروسل سقف دارد، چون بیش از MAX_PRODUCTS کارت نشان
		 * نمی‌دهد. آرشیو فروشگاه با limit صفر همه را می‌خواهد.
		 */
		if ( null === $limit ) {
			$limit = self::MAX_PRODUCTS * 5;
		}

		$limit = max( 0, (int) $limit );
		$bound = $limit > 0 ? $limit : PHP_INT_MAX;

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

		$rows = $wpdb->get_col( $wpdb->prepare( $sql, TS_BNPL_Data::META, $bound ) ); // phpcs:ignore WordPress.DB -- کوئری آماده؛ کش عمداً ندارد تا فهرست همیشه تازه بماند.
		$ids  = array_values( array_unique( array_filter( array_map( 'absint', (array) $rows ) ) ) );

		return (array) apply_filters( 'ts_bnpl_eligible_product_ids', $ids );
	}

	/*
	|--------------------------------------------------------------------------
	| آرشیو فروشگاه، محدود به کالاهای اعتباری
	|--------------------------------------------------------------------------
	*/

	/**
	 * نشانی آرشیو فروشگاه با فیلتر خرید اعتباری.
	 *
	 * @return string رشته‌ی خالی اگر برگه‌ی فروشگاه تعریف نشده باشد.
	 */
	public static function shop_url() {
		if ( ! function_exists( 'wc_get_page_permalink' ) ) {
			return '';
		}

		$shop = wc_get_page_permalink( 'shop' );

		if ( ! $shop ) {
			return '';
		}

		return add_query_arg( self::QUERY_FLAG, '1', $shop );
	}

	/**
	 * آیا درخواست جاری آرشیو فروشگاهِ فیلترشده است؟
	 *
	 * @return bool
	 */
	public static function is_shop_filter_request() {
		if ( is_admin() ) {
			return false;
		}

		/*
		 * مسیر ایجکس آرشیو.
		 *
		 * صفحه‌بندی و مرتب‌سازی آرشیو، فرم فیلتر را serialize می‌کنند و
		 * پارامترهای نشانی همراهشان نمی‌رود. برای همین پرچم به شکل یک ورودی
		 * پنهان داخل همان فرم است و اینجا از رشته‌ی سریالایزشده خوانده می‌شود.
		 * عمداً فقط همین یک اکشن پذیرفته می‌شود.
		 */
		if ( wp_doing_ajax() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- فقط یک فیلتر نمایشی؛ نانس را خود قالب بررسی می‌کند.
			$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';

			if ( 'loadMoreProducts' !== $action ) {
				return false;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- همان بالا.
			$raw = isset( $_POST['fields']['filter'] ) ? wp_unslash( $_POST['fields']['filter'] ) : '';

			if ( ! is_string( $raw ) || '' === $raw ) {
				return false;
			}

			$parsed = array();
			parse_str( $raw, $parsed );

			return ! empty( $parsed[ self::QUERY_FLAG ] );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط یک فیلتر نمایشی است، نه تغییر داده.
		if ( empty( $_GET[ self::QUERY_FLAG ] ) ) {
			return false;
		}

		// فقط آرشیو محصولات؛ هیچ صفحه‌ی دیگری نباید تحت تأثیر باشد.
		return function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() );
	}

	/**
	 * محدود کردن کوئری محصولات به کالاهای واجد شرایط.
	 *
	 * قالب، آرشیو را با WC_Product_Query خودش می‌سازد و هیچ هوکی هم ندارد، پس
	 * به‌جای دست بردن در آن، همین فیلتر ووکامرس گرفته می‌شود. این‌طوری فهرست،
	 * شمارش «X کالا»، بازه‌ی قیمت و صفحه‌بندی همگی با هم فیلتر می‌شوند و
	 * مرتب‌سازی و فیلترهای موجود دست‌نخورده کار می‌کنند.
	 *
	 * از post__in استفاده می‌شود نه meta_query، چون در کالاهای متغیر متای
	 * قیمت اقساطی روی خودِ متغیر است و کوئری روی والد پیدایش نمی‌کند.
	 *
	 * @param array $args      آرگومان‌های WP_Query.
	 * @param array $query_vars آرگومان‌های اصلی WC_Product_Query.
	 *
	 * @return array
	 */
	public static function filter_shop_query( $args, $query_vars = array() ) {
		unset( $query_vars );

		if ( ! is_array( $args ) || ! self::is_shop_filter_request() ) {
			return $args;
		}

		$ids = self::get_eligible_product_ids( 0 );

		if ( empty( $ids ) ) {
			// هیچ کالای واجد شرایطی نیست: آرشیو باید خالی دربیاید، نه کامل.
			$args['post__in'] = array( 0 );

			return $args;
		}

		// اگر کوئری از قبل محدود شده، فقط اشتراکشان باقی می‌ماند.
		if ( ! empty( $args['post__in'] ) && is_array( $args['post__in'] ) ) {
			$ids = array_values( array_intersect( $args['post__in'], $ids ) );

			if ( empty( $ids ) ) {
				$ids = array( 0 );
			}
		}

		$args['post__in'] = $ids;

		return $args;
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
			$shop_all = self::shop_url();

			if ( $shop_all ) :
				?>
				<p class="ts-bnpl-landing__seeall">
					<a href="<?php echo esc_url( $shop_all ); ?>"><?php esc_html_e( 'مشاهده‌ی همه در فروشگاه', 'ts-bnpl' ); ?></a>
				</p>
			<?php endif; ?>

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

			/*
			 * شناسه و کلاس‌های ناوبری عمداً همان قرارداد قالب‌اند.
			 *
			 * اسکریپت modular/product-carousel.js قالب روی هر .products-carousel
			 * می‌چرخد و با اندیس حلقه به «products-carousel-{i}» وصل می‌شود. چون
			 * در این صفحه فقط یک کاروسل هست، اندیس صفر است. این‌طوری همان
			 * اسکریپت خود قالب راه‌اندازی‌اش می‌کند و لازم نیست تنظیمات سوایپر
			 * را جای دیگری تکرار کنیم.
			 */
			$slider_id = 'products-carousel-0';
			?>

			<?php
			/*
			 * کلاس‌های wbs-panel و products-carousel-panel عمدی‌اند: CSS ماژول
			 * کاروسل قالب به همین ظرف اسکوپ شده و عرض اسلاید و اسکرول موبایل
			 * از آنجا می‌آید. بدون این ظرف، اندازه‌ی کارت‌ها می‌شکند.
			 */
			?>
			<div class="wbs-panel products-carousel-panel ts-bnpl-landing__carousel">
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
					<div class="wbs-products-nav wbs-prev wbs-products-prev-0"><i class="icon-arrow-right"></i></div>
					<div class="wbs-products-nav wbs-next wbs-products-next-0"><i class="icon-arrow-left"></i></div>
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
	public static function card_template() {
		if ( ! defined( 'THEME_COMPONENTS' ) ) {
			return '';
		}

		/*
		 * روی موبایل هم همین فایل استفاده می‌شود، نه simple-card-mobile.php.
		 *
		 * کاروسل‌های خود قالب هم در هر دو viewport همین را رندر می‌کنند، و CSS
		 * ماژول کاروسل هم عرض اسلاید را روی `.item article` یعنی خروجی همین
		 * فایل می‌گذارد. ضمناً هیچ‌کدام از دو شیت آرشیو کلاس
		 * product-simple-mobile-card را استایل نمی‌کنند، پس آن فایل اینجا
		 * کاملاً بی‌استایل و روی‌هم‌افتاده رندر می‌شد.
		 */
		$path = THEME_COMPONENTS . 'product-cards/simple-card.php';

		return file_exists( $path ) ? $path : '';
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
		$shop     = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';
		$filtered = self::shop_url();
		?>
		<section class="ts-bnpl-landing__section">
			<div class="ts-bnpl-landing__final">
				<h2 class="ts-bnpl-landing__final-title"><?php esc_html_e( 'آماده‌ی شروع هستید؟', 'ts-bnpl' ); ?></h2>
				<p><?php esc_html_e( 'کالاهای واجد شرایط را ببینید و خریدتان را مثل همیشه شروع کنید.', 'ts-bnpl' ); ?></p>
				<div class="ts-bnpl-landing__actions">
					<a class="wbs-button button-no-hover ts-bnpl-landing__btn" href="<?php echo esc_url( $filtered ? $filtered : '#' . self::PRODUCTS_ANCHOR ); ?>">
						<?php esc_html_e( 'مشاهده محصولاتی که قابلیت خرید اعتباری دارند', 'ts-bnpl' ); ?>
					</a>
					<?php if ( $shop ) : ?>
						<a class="wbs-button button-simple button-no-hover ts-bnpl-landing__btn ts-bnpl-landing__btn--simple" href="<?php echo esc_url( $shop ); ?>">
							<?php esc_html_e( 'همه‌ی محصولات فروشگاه', 'ts-bnpl' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</section>
		<?php
	}

	/**
	 * نشانی لوگوی گرد سایت برای گرافیک هیرو.
	 *
	 * از دارایی خود قالب استفاده می‌شود؛ نسخه‌ی تازه‌ای ساخته نمی‌شود. اگر قالب
	 * عوض شود و فایل نباشد، هیرو بی‌سروصدا بدون گرافیک رندر می‌شود.
	 *
	 * @return string
	 */
	public static function hero_logo_url() {
		$relative = 'images/logo.svg';

		if ( file_exists( get_template_directory() . '/' . $relative ) ) {
			return get_template_directory_uri() . '/' . $relative;
		}

		return '';
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
