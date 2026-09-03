<?php
/**
 * رندر مستقل حالت چهارم لندینگ تصویری.
 *
 * @package TS_BNPL
 */

defined( 'ABSPATH' ) || exit;

/**
 * Visual Landing frontend controller and renderer.
 */
class TS_BNPL_Visual_Landing {

	/**
	 * ثبت هوک‌ها.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'render' ), 21 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 21 );
	}

	/**
	 * آیا درخواست جاری دقیقاً متعلق به حالت تصویری است؟
	 *
	 * @return bool
	 */
	public static function is_active() {
		return class_exists( 'TS_BNPL_Landing' )
			&& TS_BNPL_Landing::is_landing()
			&& TS_BNPL_Display::MODE_VISUAL_LANDING === TS_BNPL_Display::get_mode();
	}

	/**
	 * کلاس بدنه‌ی اختصاصی برای جلوگیری از نشت CSS.
	 *
	 * @param string[] $classes کلاس‌ها.
	 *
	 * @return string[]
	 */
	public static function body_class( $classes ) {
		if ( self::is_active() ) {
			$classes[] = 'ts-bnpl-visual-landing-page';
		}

		return $classes;
	}

	/**
	 * دارایی‌های قالب و افزونه، فقط روی حالت فعال.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		if ( ! self::is_active() ) {
			return;
		}

		$deps = TS_BNPL_Landing::enqueue_theme_card_assets( true );
		self::enqueue_faq_assets();
		TS_BNPL_Display::enqueue_style();

		if ( wp_style_is( 'ts-faq', 'enqueued' ) ) {
			$deps[] = 'ts-faq';
		}
		if ( wp_style_is( 'ts-bnpl', 'enqueued' ) ) {
			$deps[] = 'ts-bnpl';
		}

		wp_enqueue_style(
			'ts-bnpl-visual-landing',
			TS_BNPL_URL . 'assets/css/ts-bnpl-visual-landing.css',
			array_values( array_unique( $deps ) ),
			TS_BNPL_VERSION
		);

		$settings = TS_BNPL_Visual_Settings::get();
		$banners  = self::valid_banners( $settings['banners'] );

		if ( count( $banners ) > 1 ) {
			wp_enqueue_script(
				'ts-bnpl-visual-landing',
				TS_BNPL_URL . 'assets/js/ts-bnpl-visual-landing.js',
				array( 'swiper' ),
				TS_BNPL_VERSION,
				true
			);
		}
	}

	/**
	 * جایگزینی محتوای ویرایشگر تا بنر نخستین بخش دیداری باشد.
	 *
	 * @param string $content محتوای صفحه.
	 *
	 * @return string
	 */
	public static function render( $content ) {
		if ( ! self::is_active() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		return self::landing_html();
	}

	/**
	 * تمام بدنه در ترتیب مصوب.
	 *
	 * @return string
	 */
	public static function landing_html() {
		$settings    = TS_BNPL_Visual_Settings::get();
		$has_banners = ! empty( self::valid_banners( $settings['banners'] ) );

		ob_start();
		?>
		<div class="ts-bnpl-visual-landing" dir="rtl">
			<?php self::section_banners( $settings['banners'] ); ?>
			<?php self::section_hero( $settings['hero'], ! $has_banners ); ?>
			<?php self::section_providers( $settings['providers'] ); ?>
			<?php self::section_steps(); ?>
			<?php self::section_eligibility( $settings['eligibility'] ); ?>
			<?php self::section_products(); ?>
			<?php self::section_conditions( $settings['conditions'] ); ?>
			<?php self::section_faq(); ?>
			<?php self::section_final_cta( $settings['final_cta'] ); ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/** @return void */
	private static function section_banners( $rows ) {
		$banners = self::valid_banners( $rows );
		$count   = count( $banners );

		if ( 0 === $count ) {
			return;
		}
		?>
		<section class="ts-bnpl-visual-banner<?php echo $count > 1 ? ' swiper' : ' ts-bnpl-visual-banner--static'; ?>" data-ts-bnpl-visual-banner data-slide-count="<?php echo esc_attr( (string) $count ); ?>" aria-label="<?php esc_attr_e( 'پیشنهادهای خرید اعتباری', 'ts-bnpl' ); ?>">
			<div class="ts-bnpl-visual-banner__track<?php echo $count > 1 ? ' swiper-wrapper' : ''; ?>">
				<?php foreach ( $banners as $index => $banner ) : ?>
					<?php $link_label = '' !== $banner['media']['alt'] ? $banner['media']['alt'] : __( 'مشاهده پیشنهاد خرید اعتباری', 'ts-bnpl' ); ?>
					<div class="ts-bnpl-visual-banner__slide<?php echo $count > 1 ? ' swiper-slide' : ''; ?>">
						<?php if ( '' !== $banner['url'] ) : ?><a href="<?php echo esc_url( $banner['url'] ); ?>" aria-label="<?php echo esc_attr( $link_label ); ?>"><?php endif; ?>
						<?php
						echo TS_BNPL_Responsive_Media::render(
							$banner['media'],
							array(
								'class'         => 'ts-bnpl-visual-banner__image',
								'loading'       => 0 === $index ? 'eager' : 'lazy',
								'fetchpriority' => 0 === $index ? 'high' : 'low',
								'sizes'         => '(max-width: 650px) 100vw, min(1326px, 100vw)',
							)
						); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- component escapes attributes.
						?>
						<?php if ( '' !== $banner['url'] ) : ?></a><?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
			<?php if ( $count > 1 ) : ?>
				<button type="button" class="ts-bnpl-visual-banner__control ts-bnpl-visual-banner__prev" aria-label="<?php esc_attr_e( 'بنر قبلی', 'ts-bnpl' ); ?>"><i class="icon-arrow-right" aria-hidden="true"></i></button>
				<button type="button" class="ts-bnpl-visual-banner__control ts-bnpl-visual-banner__next" aria-label="<?php esc_attr_e( 'بنر بعدی', 'ts-bnpl' ); ?>"><i class="icon-arrow-left" aria-hidden="true"></i></button>
				<div class="ts-bnpl-visual-banner__pagination" aria-label="<?php esc_attr_e( 'انتخاب بنر', 'ts-bnpl' ); ?>"></div>
			<?php endif; ?>
		</section>
		<?php
	}

	/** @return void */
	private static function section_hero( $hero, $is_first_visual = false ) {
		$visual = TS_BNPL_Responsive_Media::render(
			$hero['media'],
			array(
				'class'         => 'ts-bnpl-visual-hero__image',
				'loading'       => $is_first_visual ? 'eager' : 'lazy',
				'fetchpriority' => $is_first_visual ? 'high' : '',
				'sizes'         => '(max-width: 767px) 100vw, 50vw',
			)
		);
		$logo = TS_BNPL_Landing::hero_logo_url();
		?>
		<section class="ts-bnpl-visual-hero ts-bnpl-visual-section<?php echo '' === $visual ? ' ts-bnpl-visual-hero--text-only' : ''; ?>">
			<div class="ts-bnpl-visual-hero__content">
				<?php if ( $logo ) : ?>
					<span class="ts-bnpl-visual-hero__glyph" aria-hidden="true"><img src="<?php echo esc_url( $logo ); ?>" alt="" width="440" height="440" loading="lazy" decoding="async" /></span>
				<?php endif; ?>
				<?php if ( '' !== $hero['eyebrow'] ) : ?><p class="ts-bnpl-visual-eyebrow"><?php echo esc_html( $hero['eyebrow'] ); ?></p><?php endif; ?>
				<h1><?php echo esc_html( $hero['title'] ); ?></h1>
				<p class="ts-bnpl-visual-lede"><?php echo esc_html( $hero['description'] ); ?></p>
				<ul class="ts-bnpl-visual-hero__benefits">
					<?php foreach ( $hero['benefits'] as $benefit ) : ?>
						<?php if ( '' !== $benefit ) : ?><li><i class="icon-check" aria-hidden="true"></i><?php echo esc_html( $benefit ); ?></li><?php endif; ?>
					<?php endforeach; ?>
				</ul>
				<div class="ts-bnpl-visual-actions">
					<?php self::button( $hero['primary_label'], $hero['primary_url'], false ); ?>
					<?php self::button( $hero['secondary_label'], $hero['secondary_url'], true ); ?>
				</div>
			</div>
			<?php if ( '' !== $visual ) : ?><div class="ts-bnpl-visual-hero__media"><?php echo $visual; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><?php endif; ?>
		</section>
		<?php
	}

	/** @return void */
	private static function section_providers( $entries ) {
		$providers = TS_BNPL_Providers::public_entries( $entries );
		?>
		<section class="ts-bnpl-visual-providers ts-bnpl-visual-section" aria-labelledby="ts-bnpl-visual-providers-title">
			<div class="ts-bnpl-visual-heading">
				<p class="ts-bnpl-visual-eyebrow"><?php esc_html_e( 'انتخاب در مرحله‌ی پرداخت', 'ts-bnpl' ); ?></p>
				<h2 id="ts-bnpl-visual-providers-title"><?php esc_html_e( 'سرویس‌های اعتباری فعال', 'ts-bnpl' ); ?></h2>
				<p><?php esc_html_e( 'این فهرست سرویس‌هایی را نشان می‌دهد که در تنظیمات فروشگاه فعال‌اند؛ نمایش نهایی هر روش در پرداخت به شرایط همان سبد بستگی دارد.', 'ts-bnpl' ); ?></p>
			</div>
			<?php if ( $providers ) : ?>
				<div class="ts-bnpl-visual-providers__grid" data-provider-count="<?php echo esc_attr( (string) count( $providers ) ); ?>">
					<?php foreach ( $providers as $provider ) : ?>
						<?php self::provider_card( $provider ); ?>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="ts-bnpl-visual-empty"><?php esc_html_e( 'در حال حاضر هیچ سرویس اعتباری فعالی در تنظیمات فروشگاه ثبت نشده است.', 'ts-bnpl' ); ?></p>
			<?php endif; ?>
		</section>
		<?php
	}

	/** @return void */
	private static function provider_card( $provider ) {
		$name = '' !== $provider['display_name'] ? $provider['display_name'] : $provider['provider_id'];
		$logo = '';
		if ( ! empty( $provider['logo_id'] ) ) {
			$image = wp_get_attachment_image_src( $provider['logo_id'], 'full' );
			if ( is_array( $image ) ) {
				$logo = (string) $image[0];
			}
		}
		if ( '' === $logo && ! empty( $provider['fallback_logo_url'] ) ) {
			$logo = $provider['fallback_logo_url'];
		}
		$tag = '' !== $provider['url'] ? 'a' : 'article';
		?>
		<<?php echo $tag; ?> class="ts-bnpl-visual-provider"<?php if ( 'a' === $tag ) : ?> href="<?php echo esc_url( $provider['url'] ); ?>"<?php endif; ?>>
			<?php if ( $logo ) : ?><img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $name ); ?>" width="160" height="72" loading="lazy" decoding="async" /><?php endif; ?>
			<div><h3><?php echo esc_html( $name ); ?></h3><?php if ( '' !== $provider['description'] ) : ?><p><?php echo esc_html( $provider['description'] ); ?></p><?php endif; ?></div>
		</<?php echo $tag; ?>>
		<?php
	}

	/** @return void */
	private static function section_steps() {
		$steps = array(
			array( 'icon' => 'icon-speaker', 'title' => 'کالای واجد شرایط را انتخاب کنید', 'text' => 'نشان خرید اعتباری را نزدیک قیمت و دکمه‌ی افزودن به سبد بررسی کنید.' ),
			array( 'icon' => 'icon-cart', 'title' => 'کالا را به سبد خرید اضافه کنید', 'text' => 'هیچ مسیر جداگانه‌ای لازم نیست؛ درست مثل خرید معمولی ادامه دهید.' ),
			array( 'icon' => 'icon-arrow-left', 'title' => 'به صفحه‌ی تسویه‌حساب بروید', 'text' => 'اطلاعات گیرنده و نشانی تحویل را مثل همیشه کامل کنید.' ),
			array( 'icon' => 'icon-check', 'title' => 'روش پرداخت اعتباری را انتخاب کنید', 'text' => 'یکی از سرویس‌های فعال را انتخاب کنید و شرایط را پیش از تأیید ببینید.' ),
		);
		?>
		<section class="ts-bnpl-visual-how ts-bnpl-visual-section" id="ts-bnpl-visual-how" aria-labelledby="ts-bnpl-visual-how-title">
			<div class="ts-bnpl-visual-heading"><h2 id="ts-bnpl-visual-how-title"><?php esc_html_e( 'خرید اعتباری چطور انجام می‌شود؟', 'ts-bnpl' ); ?></h2><p><?php esc_html_e( 'کل مسیر همان خرید همیشگی است و فقط در گام آخر، روش پرداخت را تغییر می‌دهید.', 'ts-bnpl' ); ?></p></div>
			<ol class="ts-bnpl-visual-steps">
				<?php foreach ( $steps as $index => $step ) : ?>
					<li class="ts-bnpl-visual-steps__item"><span class="ts-bnpl-visual-steps__number"><?php echo esc_html( self::persian_digits( $index + 1 ) ); ?></span><i class="<?php echo esc_attr( $step['icon'] ); ?>" aria-hidden="true"></i><h3><?php echo esc_html( $step['title'] ); ?></h3><p><?php echo esc_html( $step['text'] ); ?></p></li>
				<?php endforeach; ?>
			</ol>
		</section>
		<?php
	}

	/** @return void */
	private static function section_eligibility( $section ) {
		$visual = TS_BNPL_Responsive_Media::render( $section['media'], array( 'class' => 'ts-bnpl-visual-split__image', 'sizes' => '(max-width: 767px) 100vw, 50vw' ) );
		$teaser = TS_BNPL_Display::landing_teaser_html( '#ts-bnpl-visual-products' );
		?>
		<section class="ts-bnpl-visual-eligibility ts-bnpl-visual-section ts-bnpl-visual-split<?php echo '' === $visual ? ' ts-bnpl-visual-split--text-only' : ''; ?>" aria-labelledby="ts-bnpl-visual-eligibility-title">
			<div class="ts-bnpl-visual-split__content"><h2 id="ts-bnpl-visual-eligibility-title"><?php echo esc_html( $section['title'] ); ?></h2><p><?php echo esc_html( $section['description'] ); ?></p><?php if ( '' !== $teaser ) : ?><div class="ts-bnpl ts-bnpl--standalone" dir="rtl"><?php echo $teaser; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- canonical component escapes its output. ?></div><?php endif; ?></div>
			<?php if ( '' !== $visual ) : ?><div class="ts-bnpl-visual-split__media"><?php echo $visual; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><?php endif; ?>
		</section>
		<?php
	}

	/** @return void */
	private static function section_products() {
		$ids  = TS_BNPL_Landing::get_eligible_product_ids();
		$shop = TS_BNPL_Landing::shop_url();
		?>
		<section class="ts-bnpl-visual-products ts-bnpl-visual-section" id="ts-bnpl-visual-products" aria-labelledby="ts-bnpl-visual-products-title">
			<div class="ts-bnpl-visual-heading ts-bnpl-visual-heading--action"><div><h2 id="ts-bnpl-visual-products-title"><?php esc_html_e( 'محصولات قابل خرید اعتباری', 'ts-bnpl' ); ?></h2><p><?php esc_html_e( 'این فهرست مستقیماً از کالاهای واجد شرایط فروشگاه به‌روز می‌شود.', 'ts-bnpl' ); ?></p></div><?php if ( $shop ) : ?><a href="<?php echo esc_url( $shop ); ?>"><?php esc_html_e( 'مشاهده همه', 'ts-bnpl' ); ?> ←</a><?php endif; ?></div>
			<?php if ( empty( $ids ) ) : ?>
				<p class="ts-bnpl-visual-empty"><?php esc_html_e( 'در حال حاضر کالایی برای خرید اعتباری فعال نیست. کمی بعد دوباره سر بزنید.', 'ts-bnpl' ); ?></p>
			<?php else : ?>
				<?php self::product_carousel( $ids ); ?>
			<?php endif; ?>
		</section>
		<?php
	}

	/** @return void */
	private static function product_carousel( $ids ) {
		$query = new WP_Query(
			array(
				'post_type'              => 'product',
				'post__in'               => array_slice( array_map( 'absint', $ids ), 0, 20 ),
				'posts_per_page'         => 20,
				'orderby'                => 'post__in',
				'post_status'            => 'publish',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);
		$card = TS_BNPL_Landing::card_template();

		if ( ! $query->have_posts() || '' === $card ) {
			wp_reset_postdata();
			echo '<p class="ts-bnpl-visual-empty">' . esc_html__( 'محصول واجد شرایطی برای نمایش پیدا نشد.', 'ts-bnpl' ) . '</p>';
			return;
		}

		$is_mobile = defined( 'IS_MOBILE' ) ? IS_MOBILE : wp_is_mobile();
		?>
		<div class="wbs-panel products-carousel-panel ts-bnpl-visual-products__carousel">
			<?php if ( ! $is_mobile ) : ?>
				<div class="products-carousel swiper" id="products-carousel-0"><div class="swiper-wrapper">
					<?php while ( $query->have_posts() ) : $query->the_post(); global $product; ?><div class="swiper-slide"><?php require $card; ?></div><?php endwhile; ?>
				</div></div>
				<button type="button" class="wbs-products-nav wbs-prev wbs-products-prev-0" aria-label="<?php esc_attr_e( 'محصول قبلی', 'ts-bnpl' ); ?>"><i class="icon-arrow-right" aria-hidden="true"></i></button>
				<button type="button" class="wbs-products-nav wbs-next wbs-products-next-0" aria-label="<?php esc_attr_e( 'محصول بعدی', 'ts-bnpl' ); ?>"><i class="icon-arrow-left" aria-hidden="true"></i></button>
			<?php else : ?>
				<div class="products-carousel"><?php while ( $query->have_posts() ) : $query->the_post(); global $product; ?><div class="item"><?php require $card; ?></div><?php endwhile; ?></div>
			<?php endif; ?>
		</div>
		<?php
		wp_reset_postdata();
	}

	/** @return void */
	private static function section_conditions( $section ) {
		$visual = TS_BNPL_Responsive_Media::render( $section['media'], array( 'class' => 'ts-bnpl-visual-split__image', 'sizes' => '(max-width: 767px) 100vw, 50vw' ) );
		?>
		<section class="ts-bnpl-visual-conditions ts-bnpl-visual-section ts-bnpl-visual-split ts-bnpl-visual-split--reverse<?php echo '' === $visual ? ' ts-bnpl-visual-split--text-only' : ''; ?>" aria-labelledby="ts-bnpl-visual-conditions-title">
			<?php if ( '' !== $visual ) : ?><div class="ts-bnpl-visual-split__media"><?php echo $visual; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><?php endif; ?>
			<div class="ts-bnpl-visual-split__content"><h2 id="ts-bnpl-visual-conditions-title"><?php echo esc_html( $section['title'] ); ?></h2><p class="ts-bnpl-visual-lede"><?php echo esc_html( $section['lead'] ); ?></p><p><?php echo esc_html( $section['description'] ); ?></p></div>
		</section>
		<?php
	}

	/** @return void */
	private static function section_faq() {
		$faqs = self::faqs();
		?>
		<section class="ts-bnpl-visual-faq ts-bnpl-visual-section ts-faq" aria-labelledby="ts-bnpl-visual-faq-title">
			<div class="ts-bnpl-visual-heading"><h2 id="ts-bnpl-visual-faq-title"><?php esc_html_e( 'پرسش‌های پرتکرار', 'ts-bnpl' ); ?></h2><p><?php esc_html_e( 'پاسخ کوتاه به سؤال‌های رایج خرید اعتباری', 'ts-bnpl' ); ?></p></div>
			<div class="ts-bnpl-visual-faq__items ts-faq__items">
				<?php if ( function_exists( 'ts_render_faq_items' ) ) : ?>
					<?php echo ts_render_faq_items( $faqs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- canonical theme renderer. ?>
				<?php else : ?>
					<?php foreach ( $faqs as $faq ) : ?><details><summary><?php echo esc_html( $faq['question'] ); ?></summary><p><?php echo esc_html( $faq['answer'] ); ?></p></details><?php endforeach; ?>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}

	/** @return void */
	private static function section_final_cta( $section ) {
		$visual = TS_BNPL_Responsive_Media::render( $section['media'], array( 'class' => 'ts-bnpl-visual-final__image', 'sizes' => '(max-width: 767px) 100vw, 40vw' ) );
		?>
		<section class="ts-bnpl-visual-final ts-bnpl-visual-section<?php echo '' === $visual ? ' ts-bnpl-visual-final--text-only' : ''; ?>" aria-labelledby="ts-bnpl-visual-final-title">
			<div class="ts-bnpl-visual-final__content"><h2 id="ts-bnpl-visual-final-title"><?php echo esc_html( $section['title'] ); ?></h2><p><?php echo esc_html( $section['description'] ); ?></p><?php self::button( $section['label'], $section['url'], false ); ?></div>
			<?php if ( '' !== $visual ) : ?><div class="ts-bnpl-visual-final__media"><?php echo $visual; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><?php endif; ?>
		</section>
		<?php
	}

	/** @return array<int,array<string,mixed>> */
	private static function valid_banners( $rows ) {
		$valid = array();
		if ( ! is_array( $rows ) ) {
			return $valid;
		}
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['media'] ) || ! TS_BNPL_Responsive_Media::is_renderable( $row['media'] ) ) {
				continue;
			}
			$media = TS_BNPL_Responsive_Media::normalize( $row['media'] );
			$url   = isset( $row['url'] ) ? (string) $row['url'] : '';
			if ( '' !== $url && '' === $media['alt'] ) {
				$media['alt'] = __( 'مشاهده پیشنهاد خرید اعتباری', 'ts-bnpl' );
			}
			$valid[] = array(
				'media' => $media,
				'url'   => $url,
			);
		}
		return $valid;
	}

	/** @return array<int,array<string,string>> */
	private static function faqs() {
		return array(
			array( 'question' => 'آیا همه‌ی کالاهای سایت را می‌شود اعتباری خرید؟', 'answer' => 'خیر. فقط کالاهایی که در صفحه‌شان نشان خرید اعتباری دارند. این فهرست به مرور به‌روز می‌شود.' ),
			array( 'question' => 'برای خرید اعتباری باید مسیر جداگانه‌ای بروم؟', 'answer' => 'نه. کالا را مثل همیشه به سبد خرید اضافه می‌کنید و فقط در مرحله‌ی پرداخت، به‌جای پرداخت نقدی یکی از روش‌های اعتباری را انتخاب می‌کنید.' ),
			array( 'question' => 'اگر سبد خرید من چند کالا داشته باشد چه می‌شود؟', 'answer' => 'برای استفاده از پرداخت اعتباری لازم است همه‌ی کالاهای داخل سبد واجد شرایط باشند. اگر کالایی در سبد این امکان را نداشته باشد، روش‌های اعتباری در مرحله‌ی پرداخت نمایش داده نمی‌شوند.' ),
			array( 'question' => 'شرایط و مبلغ نهایی را کجا می‌بینم؟', 'answer' => 'در مرحله‌ی پرداخت و پس از انتخاب روش اعتباری. تا پیش از تأیید نهایی، هیچ پرداختی انجام نمی‌شود.' ),
			array( 'question' => 'ارسال و گارانتی کالا فرقی می‌کند؟', 'answer' => 'خیر. کالا، اصالت، گارانتی، بسته‌بندی و روند ارسال دقیقاً مثل خرید نقدی است. تنها تفاوت، روش پرداخت است.' ),
			array( 'question' => 'اگر روش اعتباری در مرحله‌ی پرداخت نبود چه کنم؟', 'answer' => 'یعنی در آن لحظه برای سبد شما در دسترس نیست. می‌توانید سبد را بازبینی کنید یا خرید را به‌صورت نقدی ادامه دهید.' ),
		);
	}

	/** @return void */
	private static function button( $label, $url, $secondary ) {
		if ( '' === $label || '' === $url ) {
			return;
		}
		$class = 'wbs-button button-no-hover ts-bnpl-visual-button';
		if ( $secondary ) {
			$class .= ' button-simple ts-bnpl-visual-button--secondary';
		}
		?><a class="<?php echo esc_attr( $class ); ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a><?php
	}

	/** @return string */
	private static function persian_digits( $value ) {
		return str_replace( range( 0, 9 ), array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ), (string) $value );
	}

	/** @return void */
	private static function enqueue_faq_assets() {
		if ( ! function_exists( 'get_theme_file_uri' ) || ! function_exists( 'get_theme_file_path' ) ) {
			return;
		}
		$style_path = get_theme_file_path( '/assets/css/faq.css' );
		$script_path = get_theme_file_path( '/assets/js/faq.js' );
		$version = defined( 'THEME_VERSION' ) ? THEME_VERSION : TS_BNPL_VERSION;
		if ( file_exists( $style_path ) ) {
			wp_enqueue_style( 'ts-faq', get_theme_file_uri( '/assets/css/faq.css' ), array( 'amazing-theme-system' ), $version );
		}
		if ( file_exists( $script_path ) ) {
			wp_enqueue_script( 'ts-faq', get_theme_file_uri( '/assets/js/faq.js' ), array(), $version, true );
		}
	}
}
