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
