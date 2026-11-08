const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const vm = require('node:vm');
const { webcrypto } = require('node:crypto');

const source = readFileSync(require('node:path').join(__dirname, '../assets/js/reporter-news.js'), 'utf8');
function setup({ saved = null, file = null, loseAck = false, rejectFinish = false } = {}) {
  const handlers = {}, requests = [], storage = new Map();
  if (saved) storage.set('casting-news-draft-5', JSON.stringify(saved));
  const button = {}, status = {}, progress = {}, media = { files: file ? [file] : [] };
  const elements = { _wpnonce: { value: 'nonce' }, title: { value: 'headline' }, lead: { value: 'lead' }, body: { value: 'body' } };
  const form = { dataset: { user: '5' }, elements, querySelector: () => button, reportValidity: () => true,
    addEventListener: (event, fn) => { handlers[event] = fn; }, reset: () => {} };
  let offset = 0, lost = false;
  class XHR {
    constructor() { this.upload = {}; }
    open() {}
    send(data) {
      const action = data.get('action');
      requests.push({ action, offset: Number(data.get('offset')) });
      this.status = 200;
      if (action === 'chunk') {
        assert.equal(Number(data.get('offset')), offset);
        offset += data.get('chunk').size;
        if (loseAck && !lost) { lost = true; queueMicrotask(() => this.onerror()); return; }
      }
      this.responseText = JSON.stringify(action === 'finish' && rejectFinish ? { error: 'expired' } : { offset, done: action === 'finish' });
      queueMicrotask(() => this.onload());
    }
  }
  vm.runInNewContext(source, { document: { getElementById: id => ({ 'reporter-news-form': form, 'news-status': status, 'news-progress': progress, 'news-media': media }[id]) },
    window: { addEventListener() {} }, localStorage: { getItem: k => storage.get(k), setItem: (k, v) => storage.set(k, v), removeItem: k => storage.delete(k) },
    crypto: webcrypto, Blob, FormData, Uint8Array, XMLHttpRequest: XHR, setTimeout: fn => { fn(); }, console });
  return { submit: () => handlers.submit({ preventDefault() {} }), requests, storage, status, progress, button, elements };
}

test('submits text-only news and clears its draft', async () => {
  const app = setup();
  await app.submit();
  assert.deepEqual(app.requests.map(r => r.action), ['start', 'finish']);
  assert.equal(app.storage.size, 0);
  assert.equal(app.progress.value, 100);
  assert.match(app.status.textContent, /موفقیت/);
});
test('lost chunk acknowledgement resumes at server offset without duplicate bytes', async () => {
  const file = new Blob([new Uint8Array(700000)]);
  file.name = 'camera.raw'; file.lastModified = 123;
  const app = setup({ file, loseAck: true });
  await app.submit();
  assert.deepEqual(app.requests.filter(r => r.action === 'chunk').map(r => r.offset), [0, 524288]);
  assert.ok(app.requests.some(r => r.action === 'status'));
  assert.match(app.status.textContent, /موفقیت/);
});
test('failed finalization preserves restored text and upload identity for retry', async () => {
  const app = setup({ saved: { id: 'a'.repeat(32), fingerprint: 'text', title: 'restored', lead: 'lead', body: 'body' }, rejectFinish: true });
  await app.submit();
  assert.equal(app.elements.title.value, 'restored');
  assert.equal(JSON.parse(app.storage.get('casting-news-draft-5')).id, 'a'.repeat(32));
  assert.equal(app.button.disabled, false);
  assert.match(app.status.textContent, /expired/);
});
