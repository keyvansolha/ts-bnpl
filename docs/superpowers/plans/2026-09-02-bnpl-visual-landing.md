# BNPL Visual Landing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an isolated, configurable fourth BNPL Visual Landing mode that reuses Tehran Speaker commerce components and can be switched back to the unchanged Landing mode immediately.

**Architecture:** Extend the existing mode router with `visual_landing`, keep its complex content in a separate non-autoloaded option, and activate a dedicated renderer/admin/media/provider stack only for the selected landing Page. Reuse the legacy Landing's public eligibility/shop APIs and the theme's cards, product carousel, FAQ, buttons, icons, Swiper bundle, containers, and rotating SVG without introducing a new library.

**Tech Stack:** WordPress 6.5+, WooCommerce 8+, PHP 7.4+, vanilla JavaScript, jQuery UI Sortable, WordPress Media Library, Swiper 11 supplied by the Amazing theme, CSS with existing theme tokens, Node `node:test`, standalone PHP test harness.

**Spec:** `docs/superpowers/specs/2026-09-02-bnpl-visual-landing-design.md`

## Global Constraints

- Preserve the immutable existing mode keys `accordion`, `modal`, and `landing`; the new key is exactly `visual_landing`.
- Use the existing selected `landing_page`; no migration or destructive database change.
- Store Visual configuration only in non-autoloaded `ts_bnpl_visual_landing_settings`.
- Keep checkout, `_bnpl_price`, cart eligibility, filtered shop behavior, and the legacy Landing's section output unchanged.
- Do not add a dependency, remote asset, AI artwork, stock image, or external image URL.
- Only the top promotional banner may be a marketing-image slider; Hero, Eligibility, Conditions, Providers, and Final CTA remain static.
- Top-banner exports are 1326×400 desktop and 1300×975 mobile; static split visuals are 1000×1000 desktop and 1080×1080 mobile.
- Every major image accepts desktop/mobile AVIF plus desktop/mobile WebP and uses native `<picture>` selection.
- All new CSS is scoped to `.ts-bnpl-visual-landing` or `body.ts-bnpl-visual-landing-page`.
- Use `manage_woocommerce`, nonce checks, attachment IDs, server-side MIME checks, contextual escaping, RTL, visible focus, and reduced-motion handling.
- The plugin must remain compatible with PHP 7.4; do not use typed properties, union types, enums, or PHP 8-only syntax.

---

### Task 1: Test Harness and Fourth-Mode Routing

**Files:**
- Create: `tests/bootstrap.php`
- Create: `tests/mode-routing.test.php`
- Create: `tests/source-contracts.test.mjs`
- Modify: `includes/class-ts-bnpl-display.php`
- Modify: `includes/class-ts-bnpl-report.php`
- Modify: `includes/class-ts-bnpl-landing.php`

**Interfaces:**
- Produces: `TS_BNPL_Display::MODE_VISUAL_LANDING`
- Produces: `TS_BNPL_Display::is_landing_mode( $mode = null ): bool`
- Produces: `TS_BNPL_Landing::should_render_legacy(): bool`
- Preserves: `TS_BNPL_Display::modes()`, `get_settings()`, `update_settings()`, `get_mode()`, `teaser_html()`

- [ ] **Step 1: Add a standalone WordPress-stub test bootstrap**

Define `ABSPATH`, in-memory `get_option()`/`update_option()`, filter passthrough, escaping helpers, translation helpers, and minimal URL/page stubs in `tests/bootstrap.php`. Load only the display class so tests do not bootstrap WordPress or require MySQL.

```php
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['ts_bnpl_test_options'] = array();
function get_option( $key, $default = false ) {
    return array_key_exists( $key, $GLOBALS['ts_bnpl_test_options'] )
        ? $GLOBALS['ts_bnpl_test_options'][ $key ]
        : $default;
}
function update_option( $key, $value ) {
    $GLOBALS['ts_bnpl_test_options'][ $key ] = $value;
    return true;
}
function apply_filters( $hook, $value ) { return $value; }
```

- [ ] **Step 2: Write failing routing tests**

Assert all four keys are allowed, `visual_landing` round-trips through settings, invalid values still become `accordion`, and both landing keys are recognized while `accordion`/`modal` are not.

```php
assert_same(
    array( 'accordion', 'modal', 'landing', 'visual_landing' ),
    TS_BNPL_Display::modes(),
    'mode allowlist'
);
TS_BNPL_Display::update_settings( 'visual_landing', 'test', 42 );
assert_same( 'visual_landing', TS_BNPL_Display::get_settings()['mode'], 'round trip' );
assert_true( TS_BNPL_Display::is_landing_mode( 'landing' ), 'legacy landing' );
assert_true( TS_BNPL_Display::is_landing_mode( 'visual_landing' ), 'visual landing' );
```

- [ ] **Step 3: Run the tests to verify failure**

Run: `php tests/mode-routing.test.php`

Expected: FAIL because `MODE_VISUAL_LANDING` and `is_landing_mode()` do not exist.

- [ ] **Step 4: Implement the fourth mode minimally**

Add the constant and helper, append to `modes()`, and use the helper for missing-page fallback, link teaser markup, variant classes, and footer-modal suppression. Add the fourth Persian radio to the existing report page. Validate saved Page status as `publish` in addition to `post_type === page`.

Guard legacy body class, CSS enqueue, and content injection with `should_render_legacy()`, defined as selected landing Page and effective mode not equal to `visual_landing`. Do not change legacy section methods or CSS.

- [ ] **Step 5: Add source regression contracts**

Use Node `node:test` to assert the old mode constants remain unchanged, the new radio exists, `ts-bnpl-landing.css` is still the legacy handle, old section headings remain present, and Visual mode is included in modal suppression.

- [ ] **Step 6: Run focused checks**

Run: `php tests/mode-routing.test.php`

Run: `node --test tests/source-contracts.test.mjs`

Run: `php -l includes/class-ts-bnpl-display.php && php -l includes/class-ts-bnpl-report.php && php -l includes/class-ts-bnpl-landing.php`

Expected: all PASS.

- [ ] **Step 7: Commit**

```bash
git add tests includes/class-ts-bnpl-display.php includes/class-ts-bnpl-report.php includes/class-ts-bnpl-landing.php
git commit -m "feat: register BNPL visual landing mode"
```

---

### Task 2: Settings, Media Validation, and Provider Resolution

**Files:**
- Create: `includes/class-ts-bnpl-visual-settings.php`
- Create: `includes/class-ts-bnpl-responsive-media.php`
- Create: `includes/class-ts-bnpl-providers.php`
- Create: `tests/visual-settings.test.php`
- Create: `tests/responsive-media.test.php`
- Create: `tests/providers.test.php`
- Modify: `ts-bnpl.php`

**Interfaces:**
- Produces: `TS_BNPL_Visual_Settings::OPTION = 'ts_bnpl_visual_landing_settings'`
- Produces: `TS_BNPL_Visual_Settings::defaults(): array`
- Produces: `TS_BNPL_Visual_Settings::get(): array`
- Produces: `TS_BNPL_Visual_Settings::sanitize( $raw ): array|WP_Error`
- Produces: `TS_BNPL_Visual_Settings::save( $raw ): array|WP_Error`
- Produces: `TS_BNPL_Responsive_Media::normalize( $raw ): array`
- Produces: `TS_BNPL_Responsive_Media::render( $media, $args = array() ): string`
- Produces: `TS_BNPL_Providers::choices(): array`
- Produces: `TS_BNPL_Providers::public_entries( $entries ): array`

- [ ] **Step 1: Write failing settings tests**

Cover read-time defaults, no database write on read, explicitly empty banner/provider arrays staying empty, unknown keys being removed, a 20-entry cap, provider-ID deduplication, text/link bounds, and malformed payload rejection preserving the old option.

```php
$defaults = TS_BNPL_Visual_Settings::get();
assert_same( 1, $defaults['schema_version'], 'schema version' );
assert_same( array(), $defaults['banners'], 'default banners' );
assert_same( array(), get_option( TS_BNPL_Visual_Settings::OPTION, array() ), 'read does not write' );
```

- [ ] **Step 2: Write failing media tests**

Stub attachment IDs/MIME/URLs/metadata. Assert exact MIME enforcement, mobile-to-desktop fallback, AVIF omission when absent, no output without a WebP `<img>` fallback, source order, explicit width/height, first-image priority, and below-fold lazy attributes.

- [ ] **Step 3: Write failing provider tests**

Stub registered gateways and assert enabled/configured DigiPay is returned, disabled/unconfigured/missing/test-mode gateways are excluded, presentation-disabled rows are excluded, duplicates are rejected, and bundled DigiPay identity remains `wbs_digipay`.

- [ ] **Step 4: Run tests to verify failure**

Run: `php tests/visual-settings.test.php && php tests/responsive-media.test.php && php tests/providers.test.php`

Expected: FAIL because the classes do not exist.

- [ ] **Step 5: Implement normalized settings and defaults**

Use plain arrays compatible with PHP 7.4. Default copy must come from current factual Landing content. Store only IDs. Preserve explicit empty lists. Add the option with autoload `no` on first save and call `update_option( ..., false )` later.

Use these media keys everywhere:

```php
array(
    'desktop_avif_id' => 0,
    'desktop_webp_id' => 0,
    'mobile_avif_id'  => 0,
    'mobile_webp_id'  => 0,
    'alt'             => '',
)
```

- [ ] **Step 6: Implement responsive media rendering**

Resolve attachment values at render time. Build the ordered `<source>` list and valid fallback `<img>`. Accept render args `class`, `loading`, `fetchpriority`, `sizes`, `mobile_media`, `decorative`, and `link_url`. Escape every attribute.

- [ ] **Step 7: Implement provider resolver**

Read WooCommerce gateways only when available. Match presentation entries by gateway ID, require registered/enabled/configured availability, explicitly reject `TS_BNPL_Checkout::is_test_mode()`, and provide the bundled DigiPay fallback logo only for `wbs_digipay`.

- [ ] **Step 8: Bootstrap and run focused checks**

Require the three new files before Visual admin/frontend initialization in `ts-bnpl.php`.

Run: `php tests/visual-settings.test.php && php tests/responsive-media.test.php && php tests/providers.test.php`

Run: `php -l includes/class-ts-bnpl-visual-settings.php && php -l includes/class-ts-bnpl-responsive-media.php && php -l includes/class-ts-bnpl-providers.php`

Expected: all PASS.

- [ ] **Step 9: Commit**

```bash
git add ts-bnpl.php includes tests
git commit -m "feat: add visual landing settings and media model"
```

---

### Task 3: Dedicated Visual Landing Admin Manager

**Files:**
- Create: `includes/class-ts-bnpl-visual-admin.php`
- Create: `assets/js/ts-bnpl-visual-admin.js`
- Create: `assets/css/ts-bnpl-visual-admin.css`
- Create: `tests/visual-admin-contract.test.mjs`
- Modify: `ts-bnpl.php`

**Interfaces:**
- Consumes: `TS_BNPL_Visual_Settings::{get,save,defaults}`
- Consumes: `TS_BNPL_Providers::choices()`
- Produces: Products submenu slug `ts-bnpl-visual-landing`
- Produces: admin-post action `ts_bnpl_save_visual_landing`
- Produces: repeaters using `data-ts-bnpl-banner-list` and `data-ts-bnpl-provider-list`

- [ ] **Step 1: Write failing admin source contracts**

Assert submenu registration under `edit.php?post_type=product`, `manage_woocommerce`, nonce/capability checks, page-scoped `wp_enqueue_media()`, `jquery-ui-sortable`, hidden attachment-ID fields, all six tabs/cards, add/remove/up/down controls, and admin-post redirect notices.

- [ ] **Step 2: Run the test to verify failure**

Run: `node --test tests/visual-admin-contract.test.mjs`

Expected: FAIL because admin class/assets do not exist.

- [ ] **Step 3: Implement admin registration, render, and save**

Register a Products submenu labeled `لندینگ تصویری (آزمایشی)`. Render one atomic form with tabs for banners, hero, providers, eligibility, conditions, and final CTA. Reuse helper methods for text fields and responsive media groups.

The save action performs capability and nonce checks, passes the unslashed nested payload to `TS_BNPL_Visual_Settings::save()`, and redirects with `ts-bnpl-visual-saved=1` or a stable error code.

- [ ] **Step 4: Implement page-scoped admin interactions**

Use `wp.media()` to select attachments, store IDs, show previews, and support replace/remove. Implement banner/provider add/remove, Sortable order, and explicit up/down buttons. Recalculate row numbers after every mutation. Do not place presentation controls for colors, dimensions, spacing, or CSS.

- [ ] **Step 5: Style with WordPress admin primitives**

Use `.nav-tab-wrapper`, `.card`, core buttons, clear RTL-safe grids, image previews, warnings, and focus styles. Scope everything under `.ts-bnpl-visual-admin`.

- [ ] **Step 6: Bootstrap and verify**

Initialize `TS_BNPL_Visual_Admin` from `ts-bnpl.php` after dependencies.

Run: `node --test tests/visual-admin-contract.test.mjs`

Run: `node --check assets/js/ts-bnpl-visual-admin.js`

Run: `php -l includes/class-ts-bnpl-visual-admin.php`

Expected: all PASS.

- [ ] **Step 7: Commit**

```bash
git add ts-bnpl.php includes/class-ts-bnpl-visual-admin.php assets tests/visual-admin-contract.test.mjs
git commit -m "feat: add visual landing admin manager"
```

---

### Task 4: Visual Landing Renderer and Canonical Site Components

**Files:**
- Create: `includes/class-ts-bnpl-visual-landing.php`
- Create: `tests/visual-render-contract.test.mjs`
- Modify: `ts-bnpl.php`

**Interfaces:**
- Consumes: `TS_BNPL_Display::MODE_VISUAL_LANDING`
- Consumes: `TS_BNPL_Visual_Settings::get()`
- Consumes: `TS_BNPL_Responsive_Media::render()`
- Consumes: `TS_BNPL_Providers::public_entries()`
- Consumes: `TS_BNPL_Landing::{get_page_id,get_url,get_eligible_product_ids,shop_url}`
- Produces: `TS_BNPL_Visual_Landing::is_active(): bool`
- Produces: `TS_BNPL_Visual_Landing::landing_html(): string`
- Produces: body class `ts-bnpl-visual-landing-page`

- [ ] **Step 1: Write failing renderer contracts**

Assert active-mode/page routing, editor-content replacement, Visual-only body class/assets, breadcrumb/title hiding selectors, required section order/IDs, rotating theme SVG, four current steps, current FAQ copy, current conditions copy, provider resolution, canonical eligible product query, `simple-card.php`, theme product-carousel asset handles, and no marketing sliders outside the top banner.

- [ ] **Step 2: Run the test to verify failure**

Run: `node --test tests/visual-render-contract.test.mjs`

Expected: FAIL because the renderer does not exist.

- [ ] **Step 3: Implement request routing and page chrome isolation**

Hook `the_content`, `body_class`, and `wp_enqueue_scripts`. Replace main-loop content only when the selected Page and effective mode are Visual. Return unchanged content everywhere else. Add the Visual body class used to hide breadcrumb/Page title only for this mode.

- [ ] **Step 4: Implement the required section order**

Build focused private render methods for banners, hero, providers, steps, eligibility, products, conditions, FAQ, and final CTA. Use semantic headings and IDs `ts-bnpl-visual-how` and `ts-bnpl-visual-products` for default CTAs.

Skip invalid media without broken markup. Render no slider controls for one banner and scoped controls for two or more.

- [ ] **Step 5: Reuse dynamic products and canonical cards**

Call the existing public eligibility query. Resolve the theme's `inc/template/components/product-cards/simple-card.php`, set global `$product`, and use the established desktop Swiper/mobile horizontal-list markup. Enqueue the same guarded theme card/carousel/Swiper handles used by the legacy Landing.

- [ ] **Step 6: Reuse canonical FAQ and rotating SVG**

Prefer theme `wbs_render_faq_card()`/FAQ assets when available; provide semantic native `<details>` fallback only when unavailable. Resolve the same Amazing theme `images/logo.svg`, retain decorative empty alt, 60-second rotation, and reduced-motion stop.

- [ ] **Step 7: Bootstrap and verify**

Initialize the class after the legacy Landing so public helper APIs are available.

Run: `node --test tests/visual-render-contract.test.mjs`

Run: `php -l includes/class-ts-bnpl-visual-landing.php`

Expected: all PASS.

- [ ] **Step 8: Commit**

```bash
git add ts-bnpl.php includes/class-ts-bnpl-visual-landing.php tests/visual-render-contract.test.mjs
git commit -m "feat: render isolated BNPL visual landing"
```

---

### Task 5: Visual Design, Carousel Behavior, RTL, and Accessibility

**Files:**
- Create: `assets/css/ts-bnpl-visual-landing.css`
- Create: `assets/js/ts-bnpl-visual-landing.js`
- Create: `tests/visual-assets.test.mjs`
- Modify: `includes/class-ts-bnpl-visual-landing.php`

**Interfaces:**
- Consumes: `.ts-bnpl-visual-banner[data-slide-count]`
- Consumes: scoped `.ts-bnpl-visual-banner__next`, `__prev`, and `__pagination`
- Produces: Visual-only responsive layouts and Swiper initialization

- [ ] **Step 1: Write failing asset contracts**

Assert CSS contains only Visual-scoped component selectors, 1326/400 and 4/3 banner aspect ratios, logical properties, provider 1/2/3+ layouts, desktop-horizontal/mobile-vertical steps, split-section stacking, FAQ columns, mobile product overflow, focus-visible, reduced motion, and no global `body.home`/legacy Landing selectors.

Assert JS initializes only sliders with at least two slides, scopes controls, uses rewind/autoplay/touch/RTL-compatible Swiper, stops autoplay for reduced motion, and pauses/resumes on hover/focus/visibility.

- [ ] **Step 2: Run the test to verify failure**

Run: `node --test tests/visual-assets.test.mjs`

Expected: FAIL because CSS/JS do not exist.

- [ ] **Step 3: Implement Visual Landing CSS**

Use theme semantic tokens with brand-token fallbacks, `.container-xxl` inheritance, YekanBakh, 20px commercial radii, restrained shadows, and section rhythm matching the references. Give images meaningful area rather than decorative thumbnails.

Hide the theme breadcrumb/Page heading only beneath `body.ts-bnpl-visual-landing-page`. Keep all other rules under `.ts-bnpl-visual-landing`.

- [ ] **Step 4: Implement scoped banner Swiper**

Initialize each Visual banner independently only when `data-slide-count >= 2`. Use one slide per view, `rewind: true`, 1000 ms speed, 3000 ms autoplay, clickable pagination, touch, keyboard, observer-safe controls, and pause/reduced-motion behavior. Never initialize Hero or other image sections.

- [ ] **Step 5: Enqueue and verify**

Enqueue Visual CSS only when active. Enqueue JS only when at least two valid banners exist, after the complete theme Swiper bundle.

Run: `node --test tests/visual-assets.test.mjs`

Run: `node --check assets/js/ts-bnpl-visual-landing.js`

Run: `git diff --check`

Expected: all PASS.

- [ ] **Step 6: Commit**

```bash
git add assets includes/class-ts-bnpl-visual-landing.php tests/visual-assets.test.mjs
git commit -m "feat: style accessible RTL visual landing"
```

---

### Task 6: Image Asset Guide and Maintainer Documentation

**Files:**
- Create: `docs/bnpl-visual-landing-image-specs.md`
- Modify: `README.md`
- Modify: `CHANGELOG.md`
- Modify: `ts-bnpl.php`
- Create: `tests/documentation-contract.test.mjs`

**Interfaces:**
- Documents: admin paths/field names defined in Tasks 2–3
- Documents: dimensions, ratios, formats, budgets, crops, safe areas, naming, loading, and required/optional status implemented in Tasks 4–5
- Updates: plugin version from `0.4.3` to `0.5.0`

- [ ] **Step 1: Write failing documentation contracts**

Assert the asset guide includes every requested column and exact slot: top banners, Hero, Eligibility, Conditions, Final CTA, and provider logo. Assert exact dimensions/ratios, AVIF/WebP requirements, recommended and maximum file sizes, crop, object position, text-safe areas, HTML-versus-baked text, loading priority, fold position, admin location, and filename examples.

- [ ] **Step 2: Run the test to verify failure**

Run: `node --test tests/documentation-contract.test.mjs`

Expected: FAIL because the asset guide does not exist.

- [ ] **Step 3: Write the practical designer-facing guide**

Document:

- Top Banner: 1326×400 desktop, 1300×975 mobile, AVIF+WebP, 180 KB target/300 KB max per file.
- Hero/Eligibility/Conditions/Final CTA: 1000×1000 desktop, 1080×1080 mobile, AVIF+WebP, 140 KB target/220 KB max per file.
- Provider logo: transparent PNG/WebP, 512×512 canvas, 40 KB max; bundled DigiPay SVG fallback.

Provide exact filenames, safe zones, subject placement, crop rules, and admin tabs/fields.

- [ ] **Step 4: Update README, changelog, and version**

Explain four modes, same-Page switching, Visual submenu location, provider source of truth, media fallbacks, image guide link, and verification commands. Add a `0.5.0` changelog entry and bump only the plugin header version.

- [ ] **Step 5: Verify and commit**

Run: `node --test tests/documentation-contract.test.mjs`

Run: `php -l ts-bnpl.php`

Expected: all PASS.

```bash
git add docs README.md CHANGELOG.md ts-bnpl.php tests/documentation-contract.test.mjs
git commit -m "docs: publish visual landing media requirements"
```

---

### Task 7: Full Regression Verification and Final Implementation Commit

**Files:**
- Modify only files required to fix issues discovered by verification.

**Interfaces:**
- Verifies every interface and constraint from Tasks 1–6.

- [ ] **Step 1: Run the complete automated suite**

Run: `php tests/mode-routing.test.php && php tests/visual-settings.test.php && php tests/responsive-media.test.php && php tests/providers.test.php`

Run: `node --test tests/*.test.mjs`

Expected: all PASS, zero skipped tests unless explicitly labeled runtime-only.

- [ ] **Step 2: Run syntax and whitespace checks**

Run: `find . -path './.git' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l`

Run: `find assets/js -name '*.js' -print0 | xargs -0 -n1 node --check`

Run: `git diff --check HEAD~6..HEAD`

Expected: all PASS.

- [ ] **Step 3: Verify backward-compatibility contracts manually from source**

Confirm:

- Existing keys/copy/section order/CSS file remain present.
- Accordion/Modal behavior paths are unchanged except landing-like helper use.
- Both landing modes link to the same selected Page and suppress the modal.
- Invalid Page still falls back to Accordion.
- Exactly one landing renderer activates.
- Visual option is separate and non-autoloaded.
- Visual CSS/JS only enqueue while active.
- Checkout and eligibility classes have no diff.
- No external media/library or destructive migration exists.

- [ ] **Step 4: Inspect the complete diff and repository state**

Run: `git status --short`

Run: `git diff --stat 55ee6fc..HEAD`

Run: `git diff 55ee6fc..HEAD -- includes/class-ts-bnpl-checkout.php includes/class-ts-bnpl-data.php`

Expected: intentional tracked changes only; no checkout/data diff.

- [ ] **Step 5: Fix and re-run**

For every failure, first reproduce it with the narrow test, make the smallest scoped correction, rerun the narrow test, then rerun Steps 1–4.

- [ ] **Step 6: Record final verified state**

If verification fixes produced uncommitted changes:

```bash
git add -A
git commit -m "feat: add experimental BNPL visual landing"
```

If prior focused commits already contain all verified work, create no empty commit. Record `git rev-parse HEAD` as the final implementation hash.

- [ ] **Step 7: Run the authorized shutdown script**

Only after the final commit and clean status:

Run: `/mnt/K1/git/Site/shutdown-in-5.sh`

Expected: `کار به پایان رسید — سیستم تا 5 دقیقه دیگر خاموش می‌شود.`
