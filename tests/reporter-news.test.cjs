const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const { webcrypto } = require('node:crypto');

const source = readFileSync(require('node:path').join(__dirname, '../assets/js/reporter-news.js'), 'utf8');
function file(name, type, size) {
  const blob = new Blob([new Uint8Array(size)], { type });
  blob.name = name;
  blob.lastModified = 123;
  return blob;
}
function setup({ saved = null, photo = null, video = null, loseAck = false, rejectFinish = false } = {}) {
  const handlers = {}, requests = [], storage = new Map();
  if (saved) storage.set('casting-news-draft-5', JSON.stringify(saved));
  const button = {}, status = {}, progress = {};
  const photoInput = { files: photo ? [photo] : [], disabled: false };
  const videoInput = { files: video ? [video] : [], disabled: false };
  const elements = { _wpnonce: { value: 'nonce' }, title: { value: 'headline' }, lead: { value: 'lead' }, body: { value: 'body' } };
  const form = { dataset: { user: '5' }, elements, querySelector: () => button, reportValidity: () => true,
    addEventListener: (event, fn) => { handlers[event] = fn; }, reset: () => {} };
  const offsets = { photo: 0, video: 0 };
  let lost = false;
  class XHR {
    constructor() { this.upload = {}; }
    open() {}
    send(data) {
      const action = data.get('action');
      const slot = data.get('slot') || '';
      requests.push({ action, offset: Number(data.get('offset')), slot, hasVideo: data.get('has_video') });
      this.status = 200;
      if (action === 'chunk') {
        assert.equal(Number(data.get('offset')), offsets[slot]);
        offsets[slot] += data.get('chunk').size;
        if (loseAck && slot === 'photo' && !lost) { lost = true; queueMicrotask(() => this.onerror()); return; }
      }
      const offset = offsets[slot] || 0;
      this.responseText = JSON.stringify(action === 'finish' && rejectFinish ? { error: 'expired' } : { offset, done: action === 'finish' });
      queueMicrotask(() => this.onload());
    }
  }
  vm.runInNewContext(source, { document: { getElementById: id => ({ 'reporter-news-form': form, 'news-status': status, 'news-progress': progress, 'news-photo': photoInput, 'news-video': videoInput }[id]) },
    window: { addEventListener() {} }, localStorage: { getItem: k => storage.get(k), setItem: (k, v) => storage.set(k, v), removeItem: k => storage.delete(k) },
    crypto: webcrypto, Blob, FormData, Uint8Array, XMLHttpRequest: XHR, setTimeout: fn => { fn(); }, console });
  return { submit: () => handlers.submit({ preventDefault() {} }), requests, storage, status, progress, button, elements, photoInput, videoInput };
}

test('requires a photo and does not upload text alone', async () => {
  const app = setup();
  await app.submit();
  assert.deepEqual(app.requests, []);
  assert.match(app.status.textContent, /عکس/);
});
test('uploads the required photo and an optional video in separate slots', async () => {
  const app = setup({ photo: file('shot.jpg', 'image/jpeg', 1000), video: file('clip.mp4', 'video/mp4', 1000) });
  await app.submit();
  assert.deepEqual(app.requests.map(r => [r.action, r.slot]), [
    ['start', 'photo'], ['chunk', 'photo'], ['start', 'video'], ['chunk', 'video'], ['finish', ''],
  ]);
  assert.equal(app.requests.at(-1).hasVideo, '1');
  assert.equal(app.storage.size, 0);
  assert.match(app.status.textContent, /موفقیت/);
});
test('lost photo chunk acknowledgement resumes that slot without duplicate bytes', async () => {
  const app = setup({ photo: file('shot.jpg', 'image/jpeg', 700000), loseAck: true });
  await app.submit();
  assert.deepEqual(app.requests.filter(r => r.action === 'chunk' && r.slot === 'photo').map(r => r.offset), [0, 524288]);
  assert.ok(app.requests.some(r => r.action === 'status' && r.slot === 'photo'));
  assert.equal(app.requests.at(-1).hasVideo, '0');
  assert.match(app.status.textContent, /موفقیت/);
});
test('failed finalization preserves restored text and upload identity for retry', async () => {
  const app = setup({
    saved: { id: 'a'.repeat(32), title: 'restored', lead: 'lead', body: 'body' },
    photo: file('shot.jpg', 'image/jpeg', 1000),
    rejectFinish: true,
  });
  await app.submit();
  assert.equal(app.elements.title.value, 'restored');
  assert.equal(JSON.parse(app.storage.get('casting-news-draft-5')).id, 'a'.repeat(32));
  assert.equal(app.button.disabled, false);
  assert.equal(app.photoInput.disabled, false);
  assert.match(app.status.textContent, /expired/);
});
test('rejects a video file placed in the photo field', async () => {
  const app = setup({ photo: file('clip.mp4', 'video/mp4', 1000) });
  await app.submit();
  assert.deepEqual(app.requests, []);
  assert.match(app.status.textContent, /عکس/);
});
