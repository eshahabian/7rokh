(() => {
  'use strict';
  const form = document.getElementById('reporter-news-form');
  if (!form) return;
  const button = form.querySelector('button[type="submit"]');
  const status = document.getElementById('news-status');
  const progress = document.getElementById('news-progress');
  const photo = document.getElementById('news-photo');
  const video = document.getElementById('news-video');
  const key = 'casting-news-draft-' + form.dataset.user;
  const imageExt = /\.(jpe?g|png|gif|webp|heic|heif)$/i;
  const videoExt = /\.(mp4|webm|mov|mkv|avi|m4v|mpeg|mpg|wmv|3gp)$/i;
  const isImage = file => (file.type && file.type.startsWith('image/') && file.type !== 'image/svg+xml') || imageExt.test(file.name || '');
  const isVideo = file => (file.type && file.type.startsWith('video/')) || videoExt.test(file.name || '');
  let draft;
  try { draft = JSON.parse(localStorage.getItem(key) || 'null'); } catch (_) { /* storage unavailable */ }
  for (const field of ['title', 'lead', 'body']) {
    if (draft && draft[field]) form.elements[field].value = draft[field];
  }
  if (draft) status.textContent = 'پیش‌نویس بازیابی شد.';
  const persist = () => {
    draft = draft || {};
    for (const field of ['title', 'lead', 'body']) draft[field] = form.elements[field].value;
    try { localStorage.setItem(key, JSON.stringify(draft)); } catch (_) { /* current tab still resumes */ }
  };
  form.addEventListener('input', persist);
  const request = (action, extra = {}, chunk, onProgress) => new Promise((resolve, reject) => {
    const data = new FormData();
    data.set('_wpnonce', form.elements._wpnonce.value);
    data.set('id', draft.id);
    data.set('action', action);
    Object.entries(extra).forEach(([k, v]) => data.set(k, v));
    if (chunk) data.set('chunk', chunk, 'chunk.bin');
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'reporters.php');
    xhr.timeout = 120000;
    if (onProgress) xhr.upload.onprogress = e => { if (e.lengthComputable) onProgress(e.loaded / e.total); };
    xhr.onload = () => {
      let result;
      try { result = JSON.parse(xhr.responseText); } catch (_) { reject(new Error('پاسخ سرور دریافت نشد.')); return; }
      if (xhr.status === 409) { resolve(result); return; }
      if (xhr.status < 200 || xhr.status >= 300 || result.error) {
        const error = new Error(result.error || 'ارسال ناموفق بود.');
        error.status = xhr.status;
        reject(error);
      }
      else resolve(result);
    };
    xhr.onerror = xhr.ontimeout = () => reject(new Error('ارتباط قطع شد.'));
    xhr.send(data);
  });
  const fingerprintOf = async file => {
    const sample = new Blob([file.slice(0, 65536), file.slice(Math.max(0, file.size - 65536))]);
    const digest = await crypto.subtle.digest('SHA-256', await sample.arrayBuffer());
    return [file.name, file.size, file.lastModified, Array.from(new Uint8Array(digest)).join(',')];
  };
  let busy = false;
  window.addEventListener('beforeunload', e => { if (busy) { e.preventDefault(); e.returnValue = ''; } });
  const setLocked = locked => {
    button.disabled = locked;
    for (const field of ['title', 'lead', 'body']) form.elements[field].readOnly = locked;
    photo.disabled = locked;
    video.disabled = locked;
  };
  form.addEventListener('submit', async e => {
    e.preventDefault();
    if (busy || !form.reportValidity()) return;
    const photoFile = photo.files && photo.files[0];
    const videoFile = video.files && video.files[0];
    if (!photoFile || !isImage(photoFile)) {
      status.textContent = 'انتخاب یک عکس معتبر الزامی است.';
      return;
    }
    if (videoFile && !isVideo(videoFile)) {
      status.textContent = 'فایل ویدیو معتبر نیست. این بخش را خالی بگذارید یا یک ویدیو انتخاب کنید.';
      return;
    }
    busy = true;
    persist();
    let fingerprint = '';
    try {
      fingerprint = JSON.stringify([await fingerprintOf(photoFile), videoFile ? await fingerprintOf(videoFile) : null]);
    } catch (_) {
      busy = false;
      status.textContent = 'خواندن فایل ممکن نشد؛ دوباره انتخاب کنید.';
      return;
    }
    if (draft.fingerprint && draft.fingerprint !== fingerprint && draft.id) {
      status.textContent = 'برای ادامه ارسال قبلی، همان عکس و ویدیو را انتخاب کنید. برای خبر جدید ابتدا ارسال قبلی را کامل کنید.';
      busy = false;
      return;
    }
    if (!draft.id) draft.id = Array.from(crypto.getRandomValues(new Uint8Array(16)), n => n.toString(16).padStart(2, '0')).join('');
    draft.fingerprint = fingerprint;
    persist();
    setLocked(true);
    button.textContent = 'در حال ارسال';
    progress.hidden = false;
    const total = photoFile.size + (videoFile ? videoFile.size : 0);
    let sentBase = 0;
    const show = bytes => {
      const percent = total ? Math.min(100, Math.floor(bytes / total * 100)) : 100;
      progress.value = percent;
      status.textContent = 'در حال ارسال — ' + percent + '٪';
    };
    const uploadSlot = async (slot, file) => {
      let state = await request('start', { slot, name: file.name, size: String(file.size) });
      let failures = 0;
      while (!state.done && state.offset < file.size) {
        const offset = state.offset;
        const chunk = file.slice(offset, offset + 512 * 1024);
        show(sentBase + offset);
        try {
          state = await request('chunk', { slot, offset }, chunk, ratio => show(sentBase + offset + chunk.size * ratio));
          failures = 0;
        } catch (err) {
          status.textContent = 'آپلود متوقف شد؛ بخش‌های ذخیره‌شده محفوظ است. تلاش برای اتصال مجدد…';
          if (err.status && err.status < 500 && err.status !== 429) throw err;
          for (;;) {
            await new Promise(resolve => setTimeout(resolve, Math.min(10000, ++failures * 2000)));
            try { state = await request('status', { slot }); break; }
            catch (statusError) {
              if (statusError.status && statusError.status < 500 && statusError.status !== 429) throw statusError;
            }
          }
        }
      }
      sentBase += file.size;
    };
    try {
      await uploadSlot('photo', photoFile);
      if (videoFile) await uploadSlot('video', videoFile);
      await request('finish', {
        title: draft.title,
        lead: draft.lead,
        body: draft.body,
        has_video: videoFile ? '1' : '0',
      });
      progress.value = 100;
      status.textContent = 'خبر با موفقیت ارسال شد.';
      try { localStorage.removeItem(key); } catch (_) { /* optional persistence */ }
      draft = null;
      form.reset();
    } catch (err) {
      if (/معتبر نیست/.test(err.message) && draft) {
        draft.fingerprint = '';
        persist();
      }
      status.textContent = err.message + ' اطلاعات محفوظ است؛ برای ادامه، دوباره ارسال را بزنید. در صورت انقضای نشست، صفحه را تازه کنید.';
    } finally {
      busy = false;
      button.textContent = 'ارسال';
      setLocked(false);
    }
  });
})();
