import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';

const cssUrl = new URL('../assets/css/ts-bnpl-visual-landing.css', import.meta.url);
const css = fs.readFileSync(cssUrl, 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');
const desktopMedia = '@media (min-width: 992px)';
const mobileMedia = '@media (max-width: 767.98px)';

function splitTopLevel(value, delimiter) {
  const parts = [];
  let start = 0;
  let quote = '';
  let escaped = false;
  let parentheses = 0;
  let brackets = 0;

  for (let index = 0; index < value.length; index += 1) {
    const character = value[index];

    if (escaped) {
      escaped = false;
      continue;
    }
    if (character === '\\') {
      escaped = true;
      continue;
    }
    if (quote) {
      if (character === quote) quote = '';
      continue;
    }
    if (character === '"' || character === "'") {
      quote = character;
      continue;
    }
    if (character === '(') parentheses += 1;
    if (character === ')') parentheses = Math.max(0, parentheses - 1);
    if (character === '[') brackets += 1;
    if (character === ']') brackets = Math.max(0, brackets - 1);

    if (character === delimiter && parentheses === 0 && brackets === 0) {
      parts.push(value.slice(start, index));
      start = index + 1;
    }
  }

  parts.push(value.slice(start));
  return parts;
}

function findOpeningBrace(value, start) {
  let quote = '';
  let escaped = false;
  let parentheses = 0;
  let brackets = 0;

  for (let index = start; index < value.length; index += 1) {
    const character = value[index];

    if (escaped) {
      escaped = false;
      continue;
    }
    if (character === '\\') {
      escaped = true;
      continue;
    }
    if (quote) {
      if (character === quote) quote = '';
      continue;
    }
    if (character === '"' || character === "'") {
      quote = character;
      continue;
    }
    if (character === '(') parentheses += 1;
    if (character === ')') parentheses = Math.max(0, parentheses - 1);
    if (character === '[') brackets += 1;
    if (character === ']') brackets = Math.max(0, brackets - 1);
    if (character === '{' && parentheses === 0 && brackets === 0) return index;
  }

  return -1;
}

function findClosingBrace(value, openingBrace) {
  let depth = 1;
  let quote = '';
  let escaped = false;

  for (let index = openingBrace + 1; index < value.length; index += 1) {
    const character = value[index];

    if (escaped) {
      escaped = false;
      continue;
    }
    if (character === '\\') {
      escaped = true;
      continue;
    }
    if (quote) {
      if (character === quote) quote = '';
      continue;
    }
    if (character === '"' || character === "'") {
      quote = character;
      continue;
    }
    if (character === '{') depth += 1;
    if (character === '}') depth -= 1;
    if (depth === 0) return index;
  }

  throw new Error('Unclosed CSS block');
}

function parseDeclarations(block) {
  const declarations = {};

  for (const declaration of splitTopLevel(block, ';')) {
    const parts = splitTopLevel(declaration, ':');
    if (parts.length < 2) continue;

    const property = parts.shift().trim();
    if (!property) continue;
    declarations[property] = parts.join(':').trim();
  }

  return declarations;
}

function parseRuleList(source, atRules = []) {
  const rules = [];
  let cursor = 0;

  while (cursor < source.length) {
    const openingBrace = findOpeningBrace(source, cursor);
    if (openingBrace < 0) break;

    const prelude = source.slice(cursor, openingBrace).trim();
    const closingBrace = findClosingBrace(source, openingBrace);
    const block = source.slice(openingBrace + 1, closingBrace);

    if (prelude.startsWith('@')) {
      const normalizedAtRule = prelude.replace(/\s+/g, ' ');
      rules.push(...parseRuleList(block, [...atRules, normalizedAtRule]));
    } else if (prelude) {
      rules.push({
        selectors: splitTopLevel(prelude, ',').map((selector) => selector.trim()),
        declarations: parseDeclarations(block),
        atRules: [...atRules],
      });
    }

    cursor = closingBrace + 1;
  }

  return rules;
}

const rules = parseRuleList(css);

function rulesFor(selector) {
  return rules.filter((rule) => rule.selectors.includes(selector));
}

/*
 * `.wbs-slider-controls` is the theme's shared slider component. The plugin
 * mirrors it because neither source that carries it is guaranteed on this page:
 * `home.css` is front-page only, and the mobile archive build ships no copy at
 * all. Pin the mirror against both theme sources so a theme edit fails here.
 */
function loadTheme(relativePath) {
  const url = new URL(relativePath, import.meta.url);
  return fs.existsSync(url)
    ? parseRuleList(fs.readFileSync(url, 'utf8').replace(/\/\*[\s\S]*?\*\//g, ''))
    : null;
}

// The component itself, from the archive build that loads beside the landing.
const componentRules = loadTheme('../../../themes/amazing/lib/Archive/assets/scss/archiveModular.css');
// Its placement inside a banner (absolute pill plus notched corners).
const themeRules = loadTheme('../../../themes/amazing/lib/home/assets/scss/home.css');
const themeLoaded = componentRules && themeRules;

function declarationsIn(ruleList, selector) {
  const matched = ruleList.filter((rule) => rule.selectors.includes(selector));
  assert.ok(matched.length > 0, `theme rule missing: ${selector}`);
  return Object.assign({}, ...matched.map((rule) => rule.declarations));
}

function themeDeclarations(selector) {
  return declarationsIn(themeRules, selector);
}

function componentDeclarations(selector) {
  return declarationsIn(componentRules, selector);
}

test('banner controls mirror the homepage slider pill', { skip: themeLoaded ? false : 'theme not present' }, () => {
  const pill = componentDeclarations('body .wbs-slider-controls');
  const ours = Object.assign(
    {},
    ...rulesFor('.ts-bnpl-visual-banner__controls')
      .filter((rule) => rule.atRules.length === 0)
      .map((rule) => rule.declarations),
  );

  for (const property of ['display', 'align-items', 'justify-content', 'padding', 'width', 'height', 'border-radius', 'margin']) {
    assert.equal(ours[property], pill[property], `banner pill ${property} drifted from the homepage`);
  }
  assert.equal(ours.background, 'var(--body-bg, var(--ts-visual-surface))');
  assert.equal(ours.bottom, pill.bottom);

  // The two concave corners are pure magic numbers; drift here is invisible in review.
  const notch = (side) => themeDeclarations(
    `body.home .top-banner-panel .home-slider .home-slider-controls:not(.is-full):${side}`,
  );
  const ourNotch = (side) => Object.assign(
    {},
    ...rulesFor(`.ts-bnpl-visual-banner__controls::${side}`).map((rule) => rule.declarations),
  );
  for (const [side, edge] of [['before', 'right'], ['after', 'left']]) {
    assert.equal(ourNotch(side).top, notch(side).top, `notch ${side} top drifted`);
    assert.equal(ourNotch(side)[edge], notch(side)[edge], `notch ${side} ${edge} drifted`);
    assert.equal(ourNotch(side)['border-radius'], notch(side)['border-radius'], `notch ${side} radius drifted`);
  }
});

test('banner bullets and arrows mirror the homepage slider', { skip: themeLoaded ? false : 'theme not present' }, () => {
  const bullet = componentDeclarations('body .wbs-slider-controls .swiper-pagination-bullet');
  const ours = Object.assign(
    {},
    ...rulesFor('.ts-bnpl-visual-landing .ts-bnpl-visual-banner__pagination .swiper-pagination-bullet')
      .map((rule) => rule.declarations),
  );
  assert.equal(ours.width, bullet.width);
  assert.equal(ours.height, bullet.height);

  /*
   * A bullet is a bare <span>. The Swiper bundle stylesheet normally blockifies
   * it, but the theme page route can leave the handle on its slim build, which
   * has no pagination rules, so every dot collapses to zero size. These must be
   * declared here rather than inherited.
   */
  assert.equal(ours.display, 'inline-block', 'bullets must not rely on the Swiper stylesheet to blockify');
  assert.equal(ours['border-radius'], '50%');
  assert.equal(ours.margin, '0 4px', 'bullets must carry Swiper default gap themselves');

  /*
   * The current slide is legible only through opacity: --secondary-white and
   * --secondary are the same navy in the light theme, so colour alone carries
   * no information. Inactive must stay visibly dimmer than active.
   */
  const active = Object.assign(
    {},
    ...rulesFor('.ts-bnpl-visual-landing .ts-bnpl-visual-banner__pagination .swiper-pagination-bullet-active')
      .map((rule) => rule.declarations),
  );
  assert.ok(Number(ours.opacity) < Number(active.opacity), 'inactive bullets must be dimmer than the active one');

  // The theme component is outscoped on purpose, so its own rule cannot win.
  assert.equal(bullet.opacity, undefined);

  const arrowIcon = componentDeclarations('body .wbs-slider-controls .wbs-slider-btn i');
  const ourIcon = Object.assign({}, ...rulesFor('.ts-bnpl-visual-banner__control i').map((rule) => rule.declarations));
  assert.equal(ourIcon['font-size'], arrowIcon['font-size']);

  const prev = componentDeclarations('body .wbs-slider-controls .wbs-slider-btn.wbs-slider-prev');
  const next = componentDeclarations('body .wbs-slider-controls .wbs-slider-btn.wbs-slider-next');
  const ourPrev = Object.assign({}, ...rulesFor('.ts-bnpl-visual-banner__prev').map((rule) => rule.declarations));
  const ourNext = Object.assign({}, ...rulesFor('.ts-bnpl-visual-banner__next').map((rule) => rule.declarations));
  assert.equal(ourPrev['margin-left'], prev['margin-left']);
  assert.equal(ourNext['margin-right'], next['margin-right']);
});

test('banner box mirrors the homepage slider height floors', { skip: themeLoaded ? false : 'theme not present' }, () => {
  const floorsOf = (ruleList) => ruleList
    .filter((rule) => rule.declarations['min-height'])
    .map((rule) => rule.declarations['min-height']);

  const themeBox = themeRules.filter((rule) => rule.selectors.includes('body.home .top-banner-panel .home-slider'));
  const ourBox = rulesFor('.ts-bnpl-visual-banner');

  assert.deepEqual(floorsOf(ourBox), floorsOf(themeBox));

  const base = ourBox.find((rule) => rule.atRules.length === 0);
  assert.equal(base.declarations['border-radius'], 'var(--ts-visual-radius)');
  // The homepage slider carries no border or shadow; neither may this one.
  assert.equal(base.declarations.border, undefined);
  assert.equal(base.declarations['box-shadow'], undefined);
});

test('banner track preserves Swiper horizontal layout', () => {
  const matchingRules = rulesFor('.ts-bnpl-visual-banner__track');
  assert.ok(matchingRules.length > 0);
  assert.equal(matchingRules.at(-1).declarations.display, 'flex');
});

test('in-page destinations clear desktop and mobile sticky headers', () => {
  for (const selector of ['#ts-bnpl-visual-how', '#ts-bnpl-visual-products']) {
    const matchingRules = rulesFor(selector);
    assert.ok(matchingRules.some((rule) => rule.declarations['scroll-margin-top'] === 'var(--ts-visual-anchor-offset)'));
  }

  const rootRules = rulesFor('.ts-bnpl-visual-landing');
  const offsets = rootRules.map((rule) => rule.declarations['--ts-visual-anchor-offset']).filter(Boolean);
  assert.deepEqual(offsets, ['210px', '112px']);
  assert.ok(rootRules.some((rule) => rule.atRules.length === 0 && rule.declarations['--ts-visual-anchor-offset'] === '210px'));
  assert.ok(rootRules.some((rule) => rule.atRules.includes(mobileMedia) && rule.declarations['--ts-visual-anchor-offset'] === '112px'));
});

test('desktop text-only final CTA keeps a 60/40 split and bounded height', () => {
  const matchingRules = rulesFor('.ts-bnpl-visual-final:not(.ts-bnpl-visual-final--banner)');
  assert.ok(matchingRules.some((rule) => rule.atRules.includes(desktopMedia) && rule.declarations['grid-template-columns'] === 'minmax(0, 3fr) minmax(360px, 2fr)'));
  assert.ok(matchingRules.some((rule) => rule.atRules.includes(desktopMedia) && rule.declarations.height === 'clamp(360px, 28vw, 420px)'));

  // The banner variant must escape that split, otherwise the artwork is cropped into a column.
  assert.ok(rulesFor('.ts-bnpl-visual-final').every((rule) => !rule.atRules.includes(desktopMedia)));
});

test('final CTA banner mirrors the top banner ratios', () => {
  const bannerRules = rulesFor('.ts-bnpl-visual-final--banner');
  const base = bannerRules.find((rule) => rule.atRules.length === 0);
  assert.ok(base);
  assert.equal(base.declarations.display, 'block');
  assert.equal(base.declarations['aspect-ratio'], '1326 / 400');
  assert.equal(base.declarations['min-height'], '0');

  const topBase = rulesFor('.ts-bnpl-visual-banner').find((rule) => rule.atRules.length === 0);
  assert.equal(base.declarations['aspect-ratio'], topBase.declarations['aspect-ratio']);

  const phone = '@media (max-width: 650px)';
  assert.ok(bannerRules.some((rule) => rule.atRules.includes(phone) && rule.declarations['aspect-ratio'] === '4 / 3'));
});

test('final CTA banner fills its link wrapper', () => {
  const linkRules = rulesFor('.ts-bnpl-visual-final--banner .ts-bnpl-visual-final__link');
  assert.ok(linkRules.some((rule) => rule.declarations.height === '100%' && rule.declarations.width === '100%'));
});

test('mobile final CTA uses a horizontal artwork crop without a forced desktop height', () => {
  const mediaRules = rulesFor('.ts-bnpl-visual-final__media');
  assert.ok(mediaRules.some((rule) => rule.atRules.includes(mobileMedia) && rule.declarations['aspect-ratio'] === '4 / 3'));

  const mobileFinalRules = rulesFor('.ts-bnpl-visual-final')
    .filter((rule) => rule.atRules.includes(mobileMedia));
  assert.ok(mobileFinalRules.length > 0);
  assert.ok(mobileFinalRules.every((rule) => rule.declarations.height === undefined));
});
