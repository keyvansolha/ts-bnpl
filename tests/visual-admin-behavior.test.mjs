import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const scriptUrl = new URL('../assets/js/ts-bnpl-visual-admin.js', import.meta.url);

function makeRow(id, fieldName = 'ts_bnpl_visual[banners][__INDEX__][url]') {
  const number = { textContent: '' };
  const field = { name: fieldName };
  const row = {
    id,
    parentElement: null,
    previousElementSibling: null,
    nextElementSibling: null,
    querySelector(selector) { return selector === '[data-ts-bnpl-row-number]' ? number : null; },
    querySelectorAll(selector) { return selector === '[name]' ? [field] : []; },
    remove() { this.parentElement.removeChild(this); },
    number,
    field,
  };
  return row;
}

function makeList(kind) {
  return {
    kind,
    children: [],
    matches(selector) { return selector === `[data-ts-bnpl-${kind}-list]`; },
    querySelectorAll() { return this.children; },
    appendChild(row) {
      this.children.push(row);
      this.updateLinks();
    },
    removeChild(row) {
      this.children.splice(this.children.indexOf(row), 1);
      this.updateLinks();
    },
    insertBefore(row, reference) {
      const current = this.children.indexOf(row);
      if (current >= 0) this.children.splice(current, 1);
      const target = this.children.indexOf(reference);
      this.children.splice(target < 0 ? this.children.length : target, 0, row);
      this.updateLinks();
    },
    updateLinks() {
      this.children.forEach((row, index) => {
        row.parentElement = this;
        row.previousElementSibling = this.children[index - 1] || null;
        row.nextElementSibling = this.children[index + 1] || null;
      });
    },
  };
}

function clickTarget(action, row = null, mediaField = null) {
  return {
    closest(selector) {
      if (selector === '[data-ts-bnpl-row]') return row;
      if (selector === '[data-ts-bnpl-media-field]') return mediaField;
      return selector === action ? this : null;
    },
  };
}

function execute() {
  assert.equal(fs.existsSync(scriptUrl), true, 'production admin initializer exists');
  const source = fs.readFileSync(scriptUrl, 'utf8');
  const bannerList = makeList('banner');
  const providerList = makeList('provider');
  const listeners = new Map();
  const frames = [];
  const alerts = [];
  let nextRow = 1;

  const root = {
    addEventListener(name, callback) { listeners.set(name, callback); },
    querySelector(selector) {
      if (selector === '[data-ts-bnpl-banner-list]') return bannerList;
      if (selector === '[data-ts-bnpl-provider-list]') return providerList;
      if (selector === '[data-ts-bnpl-banner-template]') return { innerHTML: '<div>__INDEX__</div>' };
      if (selector === '[data-ts-bnpl-provider-template]') return { innerHTML: '<div>__INDEX__</div>' };
      return null;
    },
    querySelectorAll() { return []; },
  };

  const document = {
    querySelector() { return root; },
    createElement(tagName) {
      if (tagName === 'img') return { src: '', alt: '' };
      const holder = { firstElementChild: null };
      Object.defineProperty(holder, 'innerHTML', {
        set(value) {
          const match = value.match(/\[(\d+)\]/);
          holder.firstElementChild = makeRow(`added-${nextRow++}`, `ts_bnpl_visual[banners][${match ? match[1] : '__INDEX__'}][url]`);
        },
      });
      return holder;
    },
  };

  function jquery() {
    return { sortable() {} };
  }
  jquery.fn = { sortable: true };

  const wp = {
    media(options) {
      const callbacks = new Map();
      const frame = {
        options,
        attachment: { id: 0, mime: '', url: '', sizes: {} },
        on(name, callback) { callbacks.set(name, callback); },
        open() {},
        state() {
          return { get() { return { first() { return { toJSON() { return frame.attachment; } }; } }; } };
        },
        select() { callbacks.get('select')(); },
      };
      frames.push(frame);
      return frame;
    },
  };

  const window = {
    document,
    jQuery: jquery,
    wp,
    tsBnplVisualAdmin: { invalidMedia: 'invalid type' },
    alert(message) { alerts.push(message); },
    confirm() { return true; },
  };

  vm.runInNewContext(source, { window, document, console });
  return { root, bannerList, listeners, frames, alerts };
}

test('admin rows add, remove, reorder, and renumber through the real click handler', () => {
  const state = execute();
  const click = state.listeners.get('click');

  click({ target: clickTarget('[data-ts-bnpl-add-banner]') });
  click({ target: clickTarget('[data-ts-bnpl-add-banner]') });
  assert.equal(state.bannerList.children.length, 2);
  assert.equal(state.bannerList.children[0].field.name, 'ts_bnpl_visual[banners][0][url]');
  assert.equal(state.bannerList.children[1].field.name, 'ts_bnpl_visual[banners][1][url]');

  const first = state.bannerList.children[0];
  const second = state.bannerList.children[1];
  click({ target: clickTarget('[data-ts-bnpl-move-up]', second) });
  assert.deepEqual(state.bannerList.children.map((row) => row.id), [second.id, first.id]);

  click({ target: clickTarget('[data-ts-bnpl-remove-row]', second) });
  assert.equal(state.bannerList.children.length, 1);
  assert.equal(state.bannerList.children[0].number.textContent, '1');
});

test('media replacement scopes the picker MIME and rejects a mismatched selection', () => {
  const state = execute();
  const click = state.listeners.get('click');
  const idField = { value: '0' };
  const preview = { innerHTML: '', children: [], appendChild(image) { this.children.push(image); } };
  const mediaField = {
    getAttribute() { return 'desktop_avif_id'; },
    querySelector(selector) {
      if (selector === '[data-ts-bnpl-media-id]') return idField;
      if (selector === '[data-ts-bnpl-media-preview]') return preview;
      return null;
    },
  };

  click({ target: clickTarget('[data-ts-bnpl-select-media]', null, mediaField) });
  const frame = state.frames[0];
  assert.equal(frame.options.library.type, 'image/avif');

  frame.attachment = { id: 7, mime: 'image/webp', url: 'wrong.webp', sizes: {} };
  frame.select();
  assert.equal(idField.value, '0');
  assert.deepEqual(state.alerts, ['invalid type']);

  frame.attachment = { id: 8, mime: 'image/avif', url: 'right.avif', sizes: {} };
  frame.select();
  assert.equal(idField.value, '8');
  assert.equal(preview.children[0].src, 'right.avif');
});
