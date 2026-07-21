const SW_VERSION = "casting-pwa-v1";
const BASE = new URL("./", self.location).pathname;

const PRECACHE = [
  BASE + "assets/css/style.css",
  BASE + "assets/js/main.js",
  BASE + "assets/img/icon-192.png",
  BASE + "assets/img/icon-512.png",
];

const OFFLINE_HTML = `<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>آفلاین | هفت رخ</title>
  <style>
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #0c0e12; color: #e8e4dc; font-family: sans-serif; padding: 1.5rem; text-align: center; }
    p { max-width: 22rem; line-height: 1.7; color: #9a958c; }
    h1 { font-size: 1.25rem; margin: 0 0 0.75rem; color: #e8b87a; }
  </style>
</head>
<body>
  <div>
    <h1>اتصال اینترنت نیست</h1>
    <p>برای استفاده از پورتال هفت رخ به اینترنت نیاز دارید. اتصال را بررسی کنید و دوباره تلاش کنید.</p>
  </div>
</body>
</html>`;

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(SW_VERSION).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting())
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((key) => key !== SW_VERSION).map((key) => caches.delete(key)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener("fetch", (event) => {
  if (event.request.method !== "GET") {
    return;
  }

  const url = new URL(event.request.url);

  if (event.request.mode === "navigate") {
    event.respondWith(
      fetch(event.request).catch(
        () =>
          new Response(OFFLINE_HTML, {
            headers: { "Content-Type": "text/html; charset=utf-8" },
          })
      )
    );
    return;
  }

  if (!url.pathname.includes("/assets/")) {
    return;
  }

  event.respondWith(
    caches.match(event.request, { ignoreSearch: true }).then((cached) => {
      const networkFetch = fetch(event.request)
        .then((response) => {
          if (response && response.ok) {
            caches.open(SW_VERSION).then((cache) => cache.put(event.request, response.clone()));
          }
          return response;
        })
        .catch(() => cached);

      return cached || networkFetch;
    })
  );
});
