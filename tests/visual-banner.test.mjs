import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const scriptUrl = new URL('../assets/js/ts-bnpl-visual-landing.js', import.meta.url);

function makeElement(slideCount) {
  const listeners = new Map();
  const controls = {
    next: { name: 'next' },
    prev: { name: 'prev' },
    pagination: { name: 'pagination' },
  };
  return {
    dataset: { slideCount: String(slideCount) },
    listeners,
    controls,
    querySelector(selector) {
      if (selector.endsWith('__next')) return controls.next;
      if (selector.endsWith('__prev')) return controls.prev;
      if (selector.endsWith('__pagination')) return controls.pagination;
      return null;
    },
    addEventListener(name, callback) { listeners.set(name, callback); },
    contains() { return false; },
  };
}

function execute({ reducedMotion = false } = {}) {
  assert.equal(fs.existsSync(scriptUrl), true, 'production change: banner initializer file must exist');
  const source = fs.readFileSync(scriptUrl, 'utf8');
  const single = makeElement(1);
  const multiple = makeElement(3);
  const documentListeners = new Map();
  const instances = [];
  const document = {
    readyState: 'complete',
    hidden: false,
    querySelectorAll() { return [single, multiple]; },
    addEventListener(name, callback) { documentListeners.set(name, callback); },
  };
  const window = {
    document,
    matchMedia() { return { matches: reducedMotion }; },
    Swiper: function Swiper(element, options) {
      const instance = {
        element,
        options,
        autoplay: {
          stops: 0,
          starts: 0,
          stop() { this.stops += 1; },
          start() { this.starts += 1; },
        },
      };
      instances.push(instance);
      return instance;
    },
  };
  vm.runInNewContext(source, { window, document, console });
  return { single, multiple, instances, document, documentListeners };
}

test('initializes only multi-slide banners with controls scoped to their element', () => {
  const result = execute();
  assert.equal(result.instances.length, 1);
  const instance = result.instances[0];
  assert.equal(instance.element, result.multiple);
  assert.equal(instance.options.rewind, true);
  assert.equal(instance.options.speed, 1000);
  assert.equal(instance.options.slidesPerView, 1);
  assert.equal(instance.options.keyboard.enabled, true);
  assert.equal(instance.options.navigation.nextEl, result.multiple.controls.next);
  assert.equal(instance.options.navigation.prevEl, result.multiple.controls.prev);
  assert.equal(instance.options.pagination.el, result.multiple.controls.pagination);
  assert.equal(instance.options.autoplay.delay, 3000);
});

test('pauses and resumes autoplay for hover, focus, and page visibility', () => {
  const result = execute();
  const instance = result.instances[0];
  result.multiple.listeners.get('mouseenter')();
  result.multiple.listeners.get('mouseleave')();
  result.multiple.listeners.get('focusin')();
  result.multiple.listeners.get('focusout')({ relatedTarget: null });
  assert.equal(instance.autoplay.stops, 2);
  assert.equal(instance.autoplay.starts, 2);

  result.document.hidden = true;
  result.documentListeners.get('visibilitychange')();
  result.document.hidden = false;
  result.documentListeners.get('visibilitychange')();
  assert.equal(instance.autoplay.stops, 3);
  assert.equal(instance.autoplay.starts, 3);
});

test('disables autoplay when reduced motion is requested', () => {
  const result = execute({ reducedMotion: true });
  assert.equal(result.instances.length, 1);
  assert.equal(result.instances[0].options.autoplay, false);
  assert.equal(result.multiple.listeners.size, 0);
});
