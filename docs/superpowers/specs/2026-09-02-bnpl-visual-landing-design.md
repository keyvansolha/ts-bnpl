# BNPL Visual Landing Design

Date: 2026-09-02
Status: Approved

## Objective

Add an experimental fourth BNPL presentation mode, `visual_landing`, beside the existing `accordion`, `modal`, and `landing` modes. The new mode provides a configurable, image-led Persian landing page while preserving the existing modes, checkout behavior, eligibility rules, selected WordPress Page, and current Landing output.

The existing Landing remains the rollback path. Changing the mode back to `landing` must restore it immediately without code rollback, data migration, or template changes.

## Visual Direction

The two supplied screenshots guide hierarchy, image density, commercial rhythm, and section composition. They do not define the site's colors, fonts, header, components, or exact spacing. The implementation uses the Tehran Speaker theme's design tokens, YekanBakh typography, `.container-xxl`, buttons, cards, icons, FAQ, and responsive conventions.

The screenshots combine the lead artwork with the BNPL hero. This implementation intentionally does not: the approved source hierarchy requires a promotional banner first and a distinct BNPL hero second.

Only Visual Landing hides the theme breadcrumb and Page `<h1>`, so its promotional banner is visually the first section below the existing header. The legacy Landing and all ordinary pages keep their current chrome.

## Architecture

### Mode registration and routing

- Add `TS_BNPL_Display::MODE_VISUAL_LANDING = 'visual_landing'` without renaming existing keys.
- Append the value to the existing mode allowlist and show a fourth radio labeled `لندینگ تصویری (آزمایشی)`.
- Continue using the existing `landing_page` setting and selected published WordPress Page for both landing modes.
- Treat `landing` and `visual_landing` as navigation modes in the product teaser and modal-suppression logic.
- Preserve the existing fallback to `accordion` when no valid published landing Page is selected.
- Keep the existing Landing renderer active for the selected Page in every mode except `visual_landing`, matching today's direct-page behavior.
- Activate the Visual Landing renderer only when the selected Page is requested and the effective mode is `visual_landing`.
- Ensure exactly one landing renderer and one landing stylesheet run on that request.

The minimum legacy change is a narrow render predicate around the old Landing's body class, assets, and content filter. Its section markup and CSS are not redesigned.

### New modules

The plugin will add focused modules rather than a general page builder:

- Visual Landing settings/defaults and strict normalization.
- Dedicated Visual Landing admin page.
- Responsive attachment renderer for the four media variants.
- Provider registry/presentation resolver tied to actual WooCommerce gateway state.
- Visual Landing frontend renderer and section methods.
- Page-scoped frontend and admin assets.

The main plugin file only requires and initializes these modules.

### Storage

Use a separate non-autoloaded option:

`ts_bnpl_visual_landing_settings`

The current `ts_bnpl_settings` saver replaces its whole array, so storing Visual Landing data there would be lossy. The new option stores attachment IDs and normalized content, never generated media URLs. Defaults are merged at read time; no activation migration is required.

The schema contains:

- `schema_version`
- ordered `banners`
- `hero` content and responsive media
- ordered `providers`
- `eligibility` content and responsive media
- `conditions` content and responsive media
- `final_cta` content and responsive media

Steps, FAQ entries, eligibility rules, product-query behavior, and the rotating SVG remain canonical code-controlled content. The admin page is a controlled presentation manager, not a page builder.

## Frontend Composition

The rendered order is:

1. Existing site header
2. Promotional banner or banner carousel
3. BNPL hero with the existing rotating SVG
4. Active credit providers
5. Four-step purchase flow
6. Eligibility explanation
7. Dynamic eligible-product carousel
8. Pricing and conditions
9. FAQ
10. Final visual CTA
11. Existing site footer

The plugin output replaces editor body content in Visual Landing mode so no manual content can precede the banner. The legacy Landing continues appending after editor content as it does now.

### Promotional banner

Each ordered slide has:

- Desktop AVIF attachment
- Desktop WebP attachment
- Mobile AVIF attachment
- Mobile WebP attachment
- Alt text
- Optional destination URL

One valid slide renders as static linked or unlinked media without Swiper initialization, arrows, or pagination. Two or more valid slides use the theme's existing Swiper 11 bundle and homepage behavior as the baseline: one slide per view, RTL touch/swipe, rewind, scoped arrows and pagination, 1000 ms transition, and 3000 ms autoplay.

The Visual initializer improves the existing contract without changing homepage code:

- Controls are real named buttons.
- Selectors are scoped to this instance.
- Keyboard and focus behavior are supported.
- Autoplay pauses for focus/hover and is disabled under reduced motion.
- Invalid/incomplete slides are omitted instead of emitting broken media.

The first slide is the likely LCP image: it is not lazy-loaded, uses `fetchpriority="high"`, has explicit intrinsic dimensions, and is visible in initial HTML. Later slides are lazy-loaded and do not receive high priority.

### Hero

The hero is a split commercial composition, never a slider. It contains an eyebrow, heading, concise explanation, three benefits, primary and secondary CTAs, one configurable static visual, and the existing decorative rotating theme logo SVG. The SVG uses the same source and animation timing, including reduced-motion suppression.

If the hero photograph is absent, the copy and rotating SVG remain balanced and no broken/empty image frame is rendered.

### Providers

The public section only displays presentation entries that intersect with real registered, enabled, configured, operational installment gateways. An admin row cannot create payment availability.

Provider entries are keyed by immutable gateway ID and can configure display enablement, name, logo attachment, short description, URL, and order. Duplicate/free-form IDs are rejected. DigiPay uses `wbs_digipay`; its bundled logo is the fallback when no safe uploaded logo exists.

Test mode does not qualify a provider as publicly active because it intentionally makes real payment fail. Disabled, missing, unconfigured, or test-only providers remain visible to administrators with a warning but are omitted publicly.

Layouts remain static: one provider is featured deliberately, two are side-by-side, and three or more wrap in a responsive grid. Providers never become a carousel.

### Steps and eligibility

Reuse the current four factual steps. Desktop uses a horizontal numbered flow; mobile uses a vertical flow. Icons come from the existing theme icon system.

The eligibility section uses the current factual explanation and actual eligibility semantics. It combines HTML copy, the actual BNPL badge/state, and one configured static responsive visual. It never introduces a second slider.

### Eligible products

Reuse `TS_BNPL_Landing::get_eligible_product_ids()`, the `_bnpl_price > 0` source of truth, variation-to-parent behavior, filtered shop URL, canonical theme `simple-card.php`, existing product-carousel CSS, and Swiper bundle. Products remain dynamic and bounded as in the current Landing.

Desktop uses the established product carousel. Mobile retains the theme's horizontally scrollable product-card convention. No custom product card or carousel library is introduced.

### Conditions, FAQ, and final CTA

The conditions section keeps the current factual pricing copy and pairs it with one static responsive image. No rate, approval, credit-limit, installment-count, or fee claim is invented.

FAQ keeps the existing questions and answers but renders through the theme's canonical accessible FAQ component and assets. It uses two columns where space permits and one column on mobile.

The final CTA is a split static composition with HTML heading/copy/button and one responsive visual. It is not a slider.

## Responsive Media Contract

A shared renderer accepts four attachment IDs and alt text. It emits `<picture>` sources in this order:

1. Mobile AVIF at the mobile art-direction breakpoint
2. Mobile WebP at that breakpoint
3. Desktop AVIF
4. Desktop WebP
5. A valid WebP `<img>` fallback

Missing mobile sources fall back to desktop sources. Missing AVIF falls back to WebP. A slot without a safe `<img>` fallback is omitted. The renderer resolves current URLs, MIME types, and dimensions from WordPress attachment metadata at render time.

The top banner adopts the homepage's exact display standard:

- Desktop: 1326 × 400 px, ratio 3.315:1
- Mobile: 1300 × 975 px, ratio 4:3

The source and CSS art-direction switch are aligned at 650 px to avoid the current homepage's 650/768 mismatch.

To minimize designer exports, all four static split-section visuals share one standard:

- Desktop: 1000 × 1000 px, ratio 1:1
- Mobile: 1080 × 1080 px, ratio 1:1

This common standard applies to Hero, Eligibility, Conditions, and Final CTA. The final asset guide will use these dimensions. Per-file export targets are 180 KB recommended and 300 KB maximum for each top-banner variant, and 140 KB recommended and 220 KB maximum for each static split-section variant. Provider logos target 40 KB maximum.

Below-the-fold visuals use lazy loading and asynchronous decoding. Every rendered image has intrinsic dimensions/aspect-ratio reservation. Native `<picture>` selection prevents simultaneous desktop/mobile or AVIF/WebP downloads.

## Admin Experience

Register `لندینگ تصویری (آزمایشی)` as a second Products submenu beside the existing installment-price report. Keep capability `manage_woocommerce`; do not add a top-level menu.

The page uses WordPress nav tabs/cards for:

1. Top Banners
2. Hero
3. Providers
4. Eligibility
5. Conditions / Information
6. Final CTA

One form saves the normalized configuration atomically through `admin-post.php`. Each responsive media group reuses a single four-slot field component. WordPress Media Library stores attachment IDs.

Banner and provider rows support:

- Add/remove
- Drag-and-drop sorting
- Keyboard-accessible move-up/move-down controls
- Media replacement
- Clear status/warnings

Admin assets and `wp_enqueue_media()` load only on this submenu. Banner/provider counts and text lengths are bounded.

## Validation and Security

The save handler requires `manage_woocommerce`, a valid nonce, and `upload_files` when attachment choices change. It normalizes the complete payload before one atomic option update.

- Headings, labels, names, and alt text use bounded plain-text sanitization.
- Descriptions use bounded textarea sanitization; arbitrary HTML/CSS is not accepted.
- Links allow safe HTTP(S), relative paths, and local anchors only.
- Media values must be editable, non-trashed WordPress attachments with expected MIME types.
- AVIF fields accept only `image/avif`; WebP fields accept only `image/webp`.
- Uploaded provider logos accept safe raster formats. The bundled sanitized DigiPay SVG remains an internal fallback; arbitrary uploaded SVG is not trusted.
- Banner and provider arrays are reindexed and limited to 20 entries each; providers are deduplicated by immutable gateway ID.
- Unsupported/malformed whole payloads preserve the previous saved option and show an admin error.
- Every frontend value is escaped for its output context.

Deleted or stale attachments are skipped at render time, and the admin shows missing-asset warnings. Public output never emits a broken image URL.

## CSS, RTL, and Accessibility

All new selectors live beneath `.ts-bnpl-visual-landing` or the Visual-only body class. The existing `ts-bnpl-landing.css` remains separate.

Use theme semantic tokens, logical properties, established breakpoints, `.container-xxl`, `.wbs-button`, theme icons, cards, and typography. Layouts are RTL-first and tested for arrow direction, focus order, alignment, touch behavior, and horizontal overflow.

Semantic heading order, meaningful or intentionally empty alt text, visible `:focus-visible` styles, named carousel controls, keyboard navigation, touch targets, and reduced-motion behavior are required.

## Failure and Empty States

- Invalid/missing selected Page: product teaser falls back to Accordion, matching current behavior.
- No valid banners: omit the banner section; Hero becomes the first visible section.
- One banner: static markup, no controls/initializer.
- Missing visual: omit the media side and rebalance copy.
- No publicly active providers: omit provider cards and show neutral truthful copy; never advertise an inactive provider.
- No eligible products: reuse the current clean empty state.
- Theme component unavailable: use the same guarded empty/fallback behavior as the current Landing, without fatal errors.
- Malformed saved settings: use sanitized defaults while preserving the stored value for administrator correction.

## Backward Compatibility

Verification must prove:

- `accordion`, `modal`, and `landing` keys and stored values are unchanged.
- The legacy Landing section markup, copy, rotating SVG behavior, and stylesheet remain visually unchanged.
- Switching `visual_landing` to `landing` immediately selects the old renderer on the same Page.
- Visual settings live independently and are never required by old modes.
- Visual assets never enqueue on old modes or unrelated pages.
- Checkout, provider configuration, `_bnpl_price`, cart eligibility, and the filtered shop query are unchanged.
- Shared theme component changes are avoided; any unavoidable compatibility extension is regression-checked at existing call sites.

No data migration, external media, new third-party library, AI artwork, or unrelated refactor is part of this work.

## Verification Strategy

The repository currently lacks its referenced test files and its CLI PHP cannot bootstrap WordPress because the MySQL extension is unavailable. Implementation will add lightweight source-contract and pure-setting tests that run without a database, then use all available static and browser/runtime checks.

Minimum automated checks:

- Four-mode allowlist, storage round-trip, invalid-mode fallback, and landing-like teaser/modal routing.
- Exactly one renderer/asset set for legacy versus Visual mode.
- Legacy Landing markup/assets remain protected by regression contracts.
- Separate Visual option survives existing general-settings saves.
- Defaults, malformed values, empty-list preservation, limits, sanitization, attachment MIME validation, link validation, and provider deduplication.
- Provider enabled/configured/test-mode filtering.
- Zero/one/multiple banner rendering and source order.
- One slide has no controls; multiple slides receive scoped controls/init.
- LCP and lazy-loading attributes, intrinsic dimensions, and media fallbacks.
- Canonical eligibility query/card/carousel/FAQ integration contracts.
- PHP syntax and JavaScript syntax checks.

Manual/runtime checks, where the environment permits:

- Admin save/reload and Media Library add/remove/replace/reorder.
- All four modes and immediate Landing rollback.
- One, two, three, and deleted banner states.
- DigiPay-only and multiple-provider layouts.
- Desktop, laptop, tablet, and mobile in RTL.
- Keyboard, focus, reduced motion, swipe, network image selection, console errors, CLS/LCP, and horizontal overflow.
- Checkout and product-eligibility regression paths.

## Deliverables

- Fourth `visual_landing` presentation mode.
- Dedicated Visual Landing admin manager.
- Isolated frontend renderer and assets.
- Shared four-variant responsive media component.
- Configurable top banner, hero, provider presentation, eligibility, conditions, and final CTA.
- Existing rotating SVG, factual content, eligibility query, theme product card/carousel, FAQ, buttons, icons, containers, and design tokens reused.
- Practical `docs/bnpl-visual-landing-image-specs.md` containing exact exports, formats, file-size budgets, crop/safe-area guidance, naming, priority, and admin locations.
- Automated/static verification additions and recorded commands.
- Final implementation commit without AI attribution or co-author metadata.
