(() => {
  const THEME_KEY = "casting_theme";

  const castingIsPwaShell = () =>
    window.matchMedia("(display-mode: standalone)").matches
    || window.matchMedia("(display-mode: fullscreen)").matches
    || window.matchMedia("(display-mode: minimal-ui)").matches
    || Boolean(window.navigator.standalone);

  const castingIsNativeAppShell = () => {
    try {
      const cap = window.Capacitor;
      if (cap && typeof cap.isNativePlatform === "function" && cap.isNativePlatform()) {
        return true;
      }
      if (cap && typeof cap.getPlatform === "function") {
        const platform = String(cap.getPlatform() || "web").toLowerCase();
        if (platform === "android" || platform === "ios") return true;
      }
    } catch (_err) {
      /* ignore */
    }
    const ua = String(navigator.userAgent || "");
    return /Capacitor/i.test(ua) || /ir\.rokh7/i.test(ua);
  };

  const castingIsAppShell = () => castingIsPwaShell() || castingIsNativeAppShell();

  const castingIsSiteHref = (href) => {
    try {
      const u = new URL(href, window.location.href);
      if (u.protocol !== "http:" && u.protocol !== "https:") return false;
      if (u.origin === window.location.origin) return true;
      const host = u.hostname.replace(/^www\./i, "").toLowerCase();
      return host === "7rokh.com"
        || host.endsWith(".7rokh.com")
        || host === "7rokh.ir"
        || host.endsWith(".7rokh.ir");
    } catch (err) {
      return false;
    }
  };

  const castingApplyNativeScreenFit = () => {
    const root = document.documentElement;
    if (!castingIsNativeAppShell()) {
      root.classList.remove("is-native-app");
      root.style.removeProperty("font-size");
      root.style.removeProperty("--app-scale");
      root.style.removeProperty("--app-vh");
      root.style.removeProperty("--app-vw");
      return;
    }
    root.classList.add("is-native-app");
    const vv = window.visualViewport;
    let w = Math.round((vv && vv.width) || window.innerWidth || 390);
    let h = Math.round((vv && vv.height) || window.innerHeight || 800);
    if (w < 280) w = 280;
    if (w > 1400) w = 1400;
    let scale = w / 390;
    if (scale < 0.88) scale = 0.88;
    if (scale > 1.15) scale = 1.15;
    root.style.setProperty("--app-scale", String(Math.round(scale * 1000) / 1000));
    root.style.setProperty("--app-vw", `${w}px`);
    root.style.setProperty("--app-vh", `${h}px`);
  };

  castingApplyNativeScreenFit();
  window.addEventListener("resize", castingApplyNativeScreenFit);
  if (window.visualViewport) {
    window.visualViewport.addEventListener("resize", castingApplyNativeScreenFit);
  }

  const castingIsEditableField = (el) => {
    if (!(el instanceof HTMLElement)) return false;
    const tag = el.tagName;
    if (tag === "TEXTAREA") return true;
    if (tag === "INPUT") {
      const type = String(el.type || "text").toLowerCase();
      return !["button", "submit", "checkbox", "radio", "file", "hidden", "reset", "range", "color", "image"].includes(type);
    }
    return el.isContentEditable;
  };

  const castingSetNativeCaptureSecure = (secure) => {
    try {
      if (window.CastingApp && typeof window.CastingApp.setSecureCapture === "function") {
        window.CastingApp.setSecureCapture(!!secure);
      }
    } catch (_err) {
      /* ignore */
    }
  };

  if (castingIsNativeAppShell()) {
    castingSetNativeCaptureSecure(false);
    document.addEventListener("focusin", (event) => {
      if (castingIsEditableField(event.target)) {
        castingSetNativeCaptureSecure(false);
      }
    });
    document.addEventListener("touchstart", (event) => {
      const t = event.target;
      if (!(t instanceof HTMLElement)) return;
      if (castingIsEditableField(t) || t.closest("textarea, input:not([type='hidden']):not([type='button']):not([type='submit'])")) {
        castingSetNativeCaptureSecure(false);
      }
    }, { capture: true, passive: true });
  }

  let castingLastCopiedText = "";
  const rememberCopiedText = (text) => {
    const value = String(text || "");
    if (value) castingLastCopiedText = value;
  };

  const hideCastingPasteMenu = () => {
    const menu = document.getElementById("casting-paste-menu");
    const host = menu && menu.parentElement;
    menu?.remove();
    if (host instanceof HTMLElement && !host.querySelector("#casting-paste-menu")) {
      host.classList.remove("has-casting-paste");
    }
  };

  const readCastingClipboard = async () => {
    try {
      if (window.CastingApp && typeof window.CastingApp.getClipboardText === "function") {
        const nativeText = String(window.CastingApp.getClipboardText() || "");
        if (nativeText) {
          rememberCopiedText(nativeText);
          return nativeText;
        }
      }
    } catch (_err) {
      /* fall through */
    }
    try {
      if (navigator.clipboard && typeof navigator.clipboard.readText === "function") {
        const webText = await navigator.clipboard.readText();
        if (webText) {
          rememberCopiedText(String(webText));
          return String(webText);
        }
      }
    } catch (_err2) {
      /* fall through */
    }
    return castingLastCopiedText || "";
  };

  const insertCastingClipboard = (field, text) => {
    if (!(field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement)) return false;
    const value = String(text || "");
    if (!value) return false;
    const start = Number.isInteger(field.selectionStart) ? field.selectionStart : field.value.length;
    const end = Number.isInteger(field.selectionEnd) ? field.selectionEnd : start;
    field.value = field.value.slice(0, start) + value + field.value.slice(end);
    const pos = start + value.length;
    try {
      field.setSelectionRange(pos, pos);
    } catch (_err) {
      /* ignore */
    }
    field.dispatchEvent(new Event("input", { bubbles: true }));
    field.focus();
    return true;
  };

  let castingPasteBusy = false;
  const pasteIntoCastingField = async (field) => {
    if (castingPasteBusy) return false;
    if (!(field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement) || field.readOnly || field.disabled) {
      return false;
    }
    castingPasteBusy = true;
    try {
      const before = field.value;
      try {
        field.focus();
        document.execCommand("paste");
      } catch (_err) {
        /* fall through */
      }
      if (field.value !== before) {
        rememberCopiedText(field.value.slice(before.length) || castingLastCopiedText);
        return true;
      }
      const text = await readCastingClipboard();
      if (!text) {
        window.alert("متنی در حافظه نیست. اول متن را کپی کنید.");
        return false;
      }
      rememberCopiedText(text);
      return insertCastingClipboard(field, text);
    } finally {
      window.setTimeout(() => {
        castingPasteBusy = false;
      }, 400);
    }
  };

  const showCastingPasteMenu = (field, x, y) => {
    if (!(field instanceof HTMLElement)) return;
    hideCastingPasteMenu();
    const menu = document.createElement("div");
    menu.id = "casting-paste-menu";
    menu.className = "casting-paste-menu";
    menu.setAttribute("role", "menu");
    menu.dataset.openedAt = String(Date.now());
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "casting-paste-menu-btn";
    btn.textContent = "جاگذاری";
    const runPaste = async (event) => {
      event.preventDefault();
      event.stopPropagation();
      if (Date.now() - Number(menu.dataset.openedAt || 0) < 50) return;
      if (menu.dataset.done === "1") return;
      menu.dataset.done = "1";
      hideCastingPasteMenu();
      await pasteIntoCastingField(field);
    };
    btn.addEventListener("click", runPaste);
    btn.addEventListener("pointerup", runPaste);
    menu.appendChild(btn);

    const host = field.closest(".field") || field.parentElement;
    if (host instanceof HTMLElement) {
      host.classList.add("has-casting-paste");
      host.appendChild(menu);
    } else {
      document.body.appendChild(menu);
    }

    const box = menu.getBoundingClientRect();
    const fieldRect = field.getBoundingClientRect();
    const hostRect = host instanceof HTMLElement ? host.getBoundingClientRect() : fieldRect;
    const vv = window.visualViewport;
    const viewH = vv && vv.height ? vv.height : window.innerHeight;
    const viewW = vv && vv.width ? vv.width : window.innerWidth;
    const hasPoint = Number.isFinite(x) && Number.isFinite(y);
    const fieldInView = fieldRect.top >= 0 && fieldRect.top < viewH - 12;
    const anchorY = fieldInView ? fieldRect.top : (hasPoint ? y : 24);
    const anchorX = hasPoint ? x : (document.documentElement.getAttribute("dir") === "ltr" ? fieldRect.left + 16 : fieldRect.right - 16);

    if (host instanceof HTMLElement && menu.parentElement === host) {
      let top = (fieldInView ? fieldRect.top : anchorY) - hostRect.top - box.height - 6;
      let left = anchorX - hostRect.left - box.width / 2;
      if (top < -hostRect.top + 8) top = fieldRect.top - hostRect.top + 4;
      left = Math.max(0, Math.min(Math.max(0, hostRect.width - box.width), left));
      menu.style.position = "absolute";
      menu.style.top = top + "px";
      menu.style.left = left + "px";
      menu.style.right = "auto";
      menu.style.bottom = "auto";
      return;
    }

    let top = anchorY - box.height - 6;
    let left = anchorX - box.width / 2;
    if (top < 8) top = 8;
    top = Math.max(8, Math.min(viewH - box.height - 8, top));
    left = Math.max(8, Math.min(viewW - box.width - 8, left));
    menu.style.position = "fixed";
    menu.style.left = left + "px";
    menu.style.top = top + "px";
  };

  if (castingIsNativeAppShell()) {
    const PASTE_HOLD_MS = 350;
    const PASTE_MOVE_SLOP = 16;
    let pasteHoldTimer = 0;
    let pasteHoldField = null;
    let pasteHoldX = 0;
    let pasteHoldY = 0;
    let pasteHoldStartX = 0;
    let pasteHoldStartY = 0;

    const clearPasteHold = () => {
      if (pasteHoldTimer) {
        window.clearTimeout(pasteHoldTimer);
        pasteHoldTimer = 0;
      }
      pasteHoldField = null;
    };

    const eventPoint = (event) => {
      if (event.touches && event.touches[0]) return event.touches[0];
      if (event.changedTouches && event.changedTouches[0]) return event.changedTouches[0];
      return event;
    };

    document.addEventListener("pointerdown", (event) => {
      const t = event.target;
      if (!(t instanceof HTMLElement) || !castingIsEditableField(t) || t.readOnly || t.disabled) return;
      if (event.pointerType === "mouse" && event.button !== 0) return;
      const point = eventPoint(event);
      pasteHoldField = t;
      pasteHoldStartX = pasteHoldX = Number(point.clientX);
      pasteHoldStartY = pasteHoldY = Number(point.clientY);
      if (pasteHoldTimer) window.clearTimeout(pasteHoldTimer);
      pasteHoldTimer = window.setTimeout(() => {
        pasteHoldTimer = 0;
        const field = pasteHoldField;
        if (!(field instanceof HTMLElement)) return;
        showCastingPasteMenu(field, pasteHoldX, pasteHoldY);
      }, PASTE_HOLD_MS);
    }, { capture: true, passive: true });

    document.addEventListener("pointermove", (event) => {
      if (!pasteHoldField) return;
      const point = eventPoint(event);
      pasteHoldX = Number(point.clientX);
      pasteHoldY = Number(point.clientY);
      if (Math.hypot(pasteHoldX - pasteHoldStartX, pasteHoldY - pasteHoldStartY) > PASTE_MOVE_SLOP) {
        clearPasteHold();
      }
    }, { capture: true, passive: true });

    document.addEventListener("pointerup", clearPasteHold, { capture: true, passive: true });
    document.addEventListener("pointercancel", clearPasteHold, { capture: true, passive: true });

    document.addEventListener("contextmenu", (event) => {
      const t = event.target;
      if (!(t instanceof HTMLElement) || !castingIsEditableField(t)) return;
      event.preventDefault();
      event.stopPropagation();
    }, true);
    document.addEventListener("selectstart", (event) => {
      if (event.target instanceof HTMLElement && castingIsEditableField(event.target)) {
        event.preventDefault();
      }
    }, true);
    document.addEventListener("click", (event) => {
      const menu = document.getElementById("casting-paste-menu");
      if (!menu || !(event.target instanceof Node) || menu.contains(event.target)) return;
      const openedAt = Number(menu.dataset.openedAt || 0);
      if (Date.now() - openedAt < 400) return;
      hideCastingPasteMenu();
    });
  }

  document.addEventListener("click", async (event) => {
    const btn = event.target && event.target.closest ? event.target.closest("[data-chat-paste]") : null;
    if (!(btn instanceof HTMLElement)) return;
    event.preventDefault();
    const form = btn.closest("form") || document.querySelector("[data-chat-live-send]");
    const field = form instanceof HTMLElement
      ? form.querySelector("#message, textarea[name='message'], textarea:not([readonly])")
      : document.querySelector("#message");
    await pasteIntoCastingField(field);
  });

  if (castingIsAppShell()) {
    document.documentElement.classList.add("is-pwa");
  }

  // وب‌ویو موبایل: اولین لمس نباید فقط :hover بماند — کلیک همان لحظه اعمال شود
  const castingIsCoarsePointer = () =>
    castingIsNativeAppShell()
    || window.matchMedia("(hover: none)").matches
    || window.matchMedia("(pointer: coarse)").matches;

  if (castingIsCoarsePointer()) {
    document.addEventListener(
      "click",
      (e) => {
        const el = e.target && e.target.closest
          ? e.target.closest("a, button, [role='button'], [data-member-preview], label, summary")
          : null;
        if (el && typeof el.blur === "function") {
          window.requestAnimationFrame(() => el.blur());
        }
      },
      true
    );
  }

  // در سایت/اپ: لینک‌های ۷رخ در همان صفحه باز شوند؛ تب/صفحه جدید نه
  document.addEventListener(
    "click",
    (e) => {
      if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
        return;
      }
      const a = e.target.closest("a[href]");
      if (!a || a.hasAttribute("download")) return;
      const href = a.getAttribute("href") || "";
      if (!href || href.startsWith("#") || href.startsWith("mailto:") || href.startsWith("tel:") || href.startsWith("javascript:")) {
        return;
      }
      const target = (a.getAttribute("target") || "").toLowerCase();
      if (target !== "_blank" && target !== "_system") return;
      if (!castingIsSiteHref(href)) return;
      e.preventDefault();
      a.removeAttribute("target");
      window.location.assign(a.href);
    },
    true
  );

  const nativeOpen = window.open;
  window.open = function castingSiteWindowOpen(url, target, features) {
    const href = String(url || "");
    if (href && castingIsSiteHref(href)) {
      window.location.assign(href);
      return null;
    }
    if (typeof nativeOpen === "function") {
      return nativeOpen.call(window, url, target, features);
    }
    return null;
  };

  const applyTheme = (theme) => {
    const isDay = theme === "day";
    if (isDay) {
      document.documentElement.setAttribute("data-theme", "day");
    } else {
      document.documentElement.removeAttribute("data-theme");
    }
    document.querySelectorAll("[data-theme-pick]").forEach((btn) => {
      const pick = btn.getAttribute("data-theme-pick") || "night";
      btn.classList.toggle("is-active", pick === (isDay ? "day" : "night"));
    });
    try {
      localStorage.setItem(THEME_KEY, isDay ? "day" : "night");
    } catch (err) {}
  };

  document.querySelectorAll("[data-theme-pick]").forEach((btn) => {
    btn.addEventListener("click", () => {
      applyTheme(btn.getAttribute("data-theme-pick") || "night");
    });
  });

  let storedTheme = "day";
  try {
    storedTheme = localStorage.getItem(THEME_KEY) || "day";
  } catch (err) {}
  applyTheme(storedTheme === "night" ? "night" : "day");

  const brandifyTextNodes = (root) => {
    if (!root) return;
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
      acceptNode(node) {
        const value = node.nodeValue || "";
        if (!/۷\s*رخ|7\s*رخ/.test(value)) return NodeFilter.FILTER_REJECT;
        const parent = node.parentElement;
        if (!parent) return NodeFilter.FILTER_REJECT;
        if (parent.closest("script, style, textarea, code, pre, .brand-mark, [contenteditable]")) {
          return NodeFilter.FILTER_REJECT;
        }
        return NodeFilter.FILTER_ACCEPT;
      },
    });
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    nodes.forEach((textNode) => {
      const value = textNode.nodeValue || "";
      const parts = value.split(/(۷\s*رخ|7\s*رخ)/g);
      if (parts.length < 2) return;
      const frag = document.createDocumentFragment();
      parts.forEach((part) => {
        if (/^(۷\s*رخ|7\s*رخ)$/.test(part)) {
          const mark = document.createElement("span");
          mark.className = "brand-mark";
          mark.innerHTML = '<span class="brand-mark-7">۷</span> <span class="brand-mark-rokh">رخ</span>';
          frag.appendChild(mark);
        } else if (part) {
          frag.appendChild(document.createTextNode(part));
        }
      });
      textNode.parentNode?.replaceChild(frag, textNode);
    });
  };
  brandifyTextNodes(document.body);

  document.querySelectorAll("[data-password-toggle]").forEach((btn) => {
    const wrap = btn.closest(".password-field");
    const input = wrap?.querySelector("[data-password-input], input[type='password'], input[type='text']");
    if (!input) return;
    const iconShow = btn.querySelector(".password-toggle-icon--show");
    const iconHide = btn.querySelector(".password-toggle-icon--hide");

    btn.addEventListener("click", () => {
      const showing = input.type === "text";
      input.type = showing ? "password" : "text";
      const nowShowing = !showing;
      btn.setAttribute("aria-pressed", nowShowing ? "true" : "false");
      btn.setAttribute("aria-label", nowShowing ? "مخفی کردن رمز عبور" : "نمایش رمز عبور");
      btn.setAttribute("title", nowShowing ? "مخفی کردن رمز عبور" : "نمایش رمز عبور");
      if (iconShow) iconShow.hidden = nowShowing;
      if (iconHide) iconHide.hidden = !nowShowing;
    });
  });

  const REMEMBER_LOGIN_KEY = "casting_saved_login";
  document.querySelectorAll("form[data-remember-credentials]").forEach((form) => {
    const loginInput = form.querySelector('input[name="login"]');
    const passwordInput = form.querySelector('input[name="password"]');
    const checkbox = form.querySelector("[data-remember-credentials-check]");
    if (!loginInput || !passwordInput || !checkbox) return;

    try {
      const raw = localStorage.getItem(REMEMBER_LOGIN_KEY);
      if (raw) {
        const data = JSON.parse(raw);
        if (data && typeof data.login === "string") {
          if (!loginInput.value) loginInput.value = data.login;
          if (typeof data.password === "string" && !passwordInput.value) {
            passwordInput.value = data.password;
          }
          checkbox.checked = true;
        }
      }
    } catch (_) {
      /* ignore */
    }

    checkbox.addEventListener("change", () => {
      if (!checkbox.checked) {
        try {
          localStorage.removeItem(REMEMBER_LOGIN_KEY);
        } catch (_) {
          /* ignore */
        }
      }
    });

    form.addEventListener("submit", () => {
      try {
        if (checkbox.checked) {
          localStorage.setItem(
            REMEMBER_LOGIN_KEY,
            JSON.stringify({
              login: loginInput.value,
              password: passwordInput.value,
            })
          );
        } else {
          localStorage.removeItem(REMEMBER_LOGIN_KEY);
        }
      } catch (_) {
        /* ignore */
      }
    });
  });

  const forms = document.querySelectorAll("form[data-loading]");
  forms.forEach((form) => {
    form.addEventListener("submit", () => {
      const btn = form.querySelector('button[type="submit"]');
      if (!btn || btn.disabled) return;
      window.setTimeout(() => {
        btn.disabled = true;
        btn.dataset.originalText = btn.textContent || "";
        btn.textContent = "لطفاً صبر کنید…";
      }, 10);
    });
  });

  // تبدیل تقریبی شمسی به میلادی برای محاسبه سن در مرورگر
  const jalaliToGregorian = (jy, jm, jd) => {
    jy -= 979;
    jm -= 1;
    jd -= 1;
    let jDayNo = 365 * jy + Math.floor(jy / 33) * 8 + Math.floor(((jy % 33) + 3) / 4);
    for (let i = 0; i < jm; i += 1) jDayNo += i < 6 ? 31 : 30;
    jDayNo += jd;
    let gDayNo = jDayNo + 79;
    let gy = 1600 + 400 * Math.floor(gDayNo / 146097);
    gDayNo %= 146097;
    let leap = true;
    if (gDayNo >= 36525) {
      gDayNo -= 1;
      gy += 100 * Math.floor(gDayNo / 36524);
      gDayNo %= 36524;
      if (gDayNo >= 365) gDayNo += 1;
      else leap = false;
    }
    gy += 4 * Math.floor(gDayNo / 1461);
    gDayNo %= 1461;
    if (gDayNo >= 366) {
      leap = false;
      gDayNo -= 1;
      gy += Math.floor(gDayNo / 365);
      gDayNo %= 365;
    }
    const salA = [0, 31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    let gm = 0;
    let gd = gDayNo + 1;
    for (gm = 1; gm <= 12 && gd > salA[gm]; gm += 1) gd -= salA[gm];
    return { gy, gm, gd };
  };

  const jalaliLeap = (jy) => {
    const breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];
    let jp = breaks[0];
    let jump = 0;
    for (let i = 1; i < breaks.length; i += 1) {
      const jm = breaks[i];
      jump = jm - jp;
      if (jy < jm) break;
      jp = jm;
    }
    let n = jy - jp;
    let leap;
    if (n < jump) {
      if (jump - n < 6) n = n - jump + Math.floor((jump + 4) / 33) * 33;
      leap = (((n + 1) % 33) - 1) % 4;
      if (leap === -1) leap = 4;
    } else {
      leap = ((((jy + 1) % 33) - 1) % 4);
      if (leap === -1) leap = 4;
    }
    return leap === 0;
  };

  const monthDays = (jy, jm) => {
    if (jm <= 6) return 31;
    if (jm <= 11) return 30;
    return jalaliLeap(jy) ? 30 : 29;
  };

  const bindJalaliBox = (box, withAge) => {
    const yearEl = box.querySelector("[data-jalali-year]");
    const monthEl = box.querySelector("[data-jalali-month]");
    const dayEl = box.querySelector("[data-jalali-day]");
    if (!yearEl || !monthEl || !dayEl) return;

    const refreshDays = () => {
      const jy = Number(yearEl.value || 0);
      const jm = Number(monthEl.value || 0);
      const max = jy && jm ? monthDays(jy, jm) : 31;
      const current = Number(dayEl.value || 0);
      dayEl.innerHTML = '<option value="">روز</option>';
      for (let d = 1; d <= max; d += 1) {
        const opt = document.createElement("option");
        opt.value = String(d);
        opt.textContent = String(d);
        if (d === current && d <= max) opt.selected = true;
        dayEl.appendChild(opt);
      }
    };

    const calcAge = () => {
      if (!withAge) return;
      const ageOut = document.querySelector("[data-age-output]");
      if (!ageOut) return;
      const jy = Number(yearEl.value || 0);
      const jm = Number(monthEl.value || 0);
      const jd = Number(dayEl.value || 0);
      const hiddenAge = document.querySelector('input[name="age"]');
      const setAgeView = (age) => {
        if (ageOut.tagName === "SELECT") {
          if (!Number.isFinite(age) || age < 0) {
            ageOut.value = "";
            return;
          }
          const plus = Number(ageOut.getAttribute("data-age-plus") || 76);
          const maxExact = plus - 1;
          ageOut.value = age > maxExact ? String(plus) : String(age);
          return;
        }
        ageOut.value = age >= 0 ? age + " سال" : "";
      };
      if (!jy || !jm || !jd) {
        setAgeView(-1);
        if (hiddenAge) hiddenAge.value = "";
        return;
      }
      const { gy, gm, gd } = jalaliToGregorian(jy, jm, jd);
      const today = new Date();
      let age = today.getFullYear() - gy;
      const md = today.getMonth() + 1 - gm;
      if (md < 0 || (md === 0 && today.getDate() < gd)) age -= 1;
      setAgeView(age);
      if (hiddenAge) hiddenAge.value = age >= 0 ? String(age) : "";
    };

    yearEl.addEventListener("change", () => {
      refreshDays();
      calcAge();
    });
    monthEl.addEventListener("change", () => {
      refreshDays();
      calcAge();
    });
    dayEl.addEventListener("change", calcAge);
    refreshDays();
    calcAge();
  };

  document.querySelectorAll("[data-jalali-birth]").forEach((box) => bindJalaliBox(box, true));
  document.querySelectorAll("[data-jalali-date]").forEach((box) => bindJalaliBox(box, false));

  const growChatCompose = (el) => {
    if (!(el instanceof HTMLTextAreaElement) || !el.closest(".chat-compose")) return;
    if (!String(el.value || "").trim()) {
      el.style.height = "";
      return;
    }
    el.style.height = "auto";
    el.style.height = Math.min(Math.max(el.scrollHeight, 40), 96) + "px";
  };

  const scrollChatToComposer = () => {
    const threadEl = document.getElementById("chat-thread");
    if (threadEl) threadEl.scrollTop = threadEl.scrollHeight;
    const compose = document.querySelector(".chat-compose");
    if (compose && typeof compose.scrollIntoView === "function") {
      compose.scrollIntoView({ block: "end", inline: "nearest" });
    }
  };

  const thread = document.getElementById("chat-thread");
  if (thread) {
    thread.scrollTop = thread.scrollHeight;
  }
  document.querySelectorAll(".chat-compose textarea").forEach((el) => {
    growChatCompose(el);
    el.addEventListener("input", () => growChatCompose(el));
    el.addEventListener("focus", () => growChatCompose(el));
  });
  if (document.body.classList.contains("page-messages")) {
    scrollChatToComposer();
    window.requestAnimationFrame(scrollChatToComposer);
    window.setTimeout(scrollChatToComposer, 150);
    window.setTimeout(scrollChatToComposer, 450);
  }

  const bindRepeater = (rootSel, listSel, templateSel, addSel, removeSel, renameFn) => {
    const box = document.querySelector(rootSel);
    if (!box) return;
    const list = box.querySelector(listSel);
    const template = box.querySelector(templateSel);
    const addBtn = box.querySelector(addSel);

    const reindex = () => {
      [...list.querySelectorAll(".work-credit-row")].forEach((row, i) => renameFn(row, i));
    };

    addBtn?.addEventListener("click", () => {
      if (!template || !list) return;
      const html = template.innerHTML.split("__i__").join(String(list.children.length));
      list.insertAdjacentHTML("beforeend", html);
      reindex();
    });

    list?.addEventListener("click", (e) => {
      const btn = e.target.closest(removeSel);
      if (!btn) return;
      const rows = list.querySelectorAll(".work-credit-row");
      if (rows.length <= 1) {
        rows[0]?.querySelectorAll('input[type="text"], textarea').forEach((el) => {
          el.value = "";
        });
        return;
      }
      btn.closest(".work-credit-row")?.remove();
      reindex();
    });
  };

  bindRepeater(
    "[data-work-credits]",
    "[data-work-credits-list]",
    "[data-work-credit-template]",
    "[data-add-credit]",
    "[data-remove-credit]",
    (row, i) => {
      const select = row.querySelector("select");
      const input = row.querySelector('input[type="text"]');
      if (select) select.name = `work_credits[${i}][type]`;
      if (input) input.name = `work_credits[${i}][title]`;
    }
  );

  bindRepeater(
    "[data-artistic-works]",
    "[data-artistic-works-list]",
    "[data-artistic-work-template]",
    "[data-add-artistic-work]",
    "[data-remove-artistic-work]",
    (row, i) => {
      const select = row.querySelector("select");
      const input = row.querySelector('input[type="text"]');
      if (select) select.name = `artistic_works[${i}][type]`;
      if (input) input.name = `artistic_works[${i}][title]`;
    }
  );

  bindRepeater(
    "[data-education-items]",
    "[data-education-list]",
    "[data-education-template]",
    "[data-add-education]",
    "[data-remove-education]",
    (row, i) => {
      const select = row.querySelector("select");
      const input = row.querySelector('input[type="text"]');
      if (select) select.name = `education_items[${i}][degree]`;
      if (input) input.name = `education_items[${i}][university]`;
    }
  );

  bindRepeater(
    "[data-award-items]",
    "[data-award-list]",
    "[data-award-template]",
    "[data-add-award]",
    "[data-remove-award]",
    (row, i) => {
      const title = row.querySelector('input[name*="[title]"]') || row.querySelector('input[type="text"]:not(.award-year)');
      const year = row.querySelector("input.award-year") || row.querySelectorAll('input[type="text"]')[1];
      if (title) title.name = `award_items[${i}][title]`;
      if (year) year.name = `award_items[${i}][year]`;
    }
  );

  document.querySelectorAll("[data-location-fields]").forEach((box) => {
    let map = { cities: {} };
    try {
      map = JSON.parse(box.getAttribute("data-location-map") || "{}");
    } catch (err) {
      map = { cities: {} };
    }
    const provinceSel = box.querySelector("[data-location-province]");
    const citySel = box.querySelector("select[data-location-city]");
    const cityInput = box.querySelector("[data-location-city-input]");
    const cityList = box.querySelector("[data-location-city-list]");
    const freeCity = box.hasAttribute("data-location-city-free");
    const allowCityAll = box.hasAttribute("data-location-city-all");

    const fillSelect = (select, items, placeholder, selected, allowAll) => {
      if (!select) return;
      const keep = selected || "";
      select.innerHTML = "";
      const first = document.createElement("option");
      first.value = "";
      first.textContent = placeholder;
      select.appendChild(first);
      if (allowAll) {
        const allOpt = document.createElement("option");
        allOpt.value = "همه";
        allOpt.textContent = "همه";
        if (keep === "همه") allOpt.selected = true;
        select.appendChild(allOpt);
      }
      (items || []).forEach((name) => {
        const opt = document.createElement("option");
        opt.value = name;
        opt.textContent = name;
        if (keep === name) opt.selected = true;
        select.appendChild(opt);
      });
      if (keep && keep !== "همه" && !(items || []).includes(keep)) {
        const custom = document.createElement("option");
        custom.value = keep;
        custom.textContent = keep;
        custom.selected = true;
        select.appendChild(custom);
      }
    };

    const fillDatalist = (list, items) => {
      if (!list) return;
      list.innerHTML = "";
      (items || []).forEach((name) => {
        const opt = document.createElement("option");
        opt.value = name;
        list.appendChild(opt);
      });
    };

    const syncCities = (keepCity) => {
      const province = provinceSel?.value || "";
      const cities = province ? map.cities?.[province] || [] : [];
      // حالت قدیمی input+datalist (اگر جایی مانده باشد)
      if (freeCity && cityInput) {
        if (!province) {
          cityInput.disabled = true;
          cityInput.placeholder = "اول استان را انتخاب کنید";
          if (!keepCity) cityInput.value = "";
          fillDatalist(cityList, []);
        } else {
          cityInput.disabled = false;
          cityInput.placeholder = "انتخاب شهر…";
          fillDatalist(cityList, cities);
          if (typeof keepCity === "string" && keepCity !== "") {
            cityInput.value = keepCity;
          }
        }
        return;
      }
      if (!citySel) return;
      if (!province) {
        citySel.disabled = true;
        fillSelect(
          citySel,
          [],
          allowCityAll ? "اول استان" : "اول استان را انتخاب کنید",
          "",
          false
        );
      } else {
        citySel.disabled = false;
        fillSelect(
          citySel,
          cities,
          allowCityAll ? "همه" : "انتخاب شهر…",
          keepCity,
          allowCityAll
        );
      }
    };

    provinceSel?.addEventListener("change", () => syncCities(""));
    syncCities(cityInput?.value || citySel?.value || "");
  });

  bindRepeater(
    "[data-language-items]",
    "[data-language-list]",
    "[data-language-template]",
    "[data-add-language]",
    "[data-remove-language]",
    (row, i) => {
      const input = row.querySelector('input[type="text"]');
      const select = row.querySelector("select");
      if (input) input.name = `language_items[${i}][name]`;
      if (select) select.name = `language_items[${i}][level]`;
    }
  );

  document.querySelectorAll("[data-skill-items]").forEach((box) => {
    const list = box.querySelector("[data-skill-list]");
    const template = box.querySelector("[data-skill-template]");
    const addBtn = box.querySelector("[data-add-skill]");

    const syncNote = (row) => {
      const select = row.querySelector("[data-skill-select]");
      const note = row.querySelector("[data-skill-note]");
      if (!select || !note) return;
      const isOther = select.value === "other";
      row.classList.toggle("is-other", isOther);
      note.disabled = !isOther;
      note.placeholder = "چه هنری دارید؟";
      if (!isOther) note.value = "";
    };

    const reindex = () => {
      [...list.querySelectorAll(".skill-row")].forEach((row, i) => {
        const select = row.querySelector("[data-skill-select]");
        const note = row.querySelector("[data-skill-note]");
        if (select) select.name = `skill_items[${i}][skill]`;
        if (note) note.name = `skill_items[${i}][note]`;
      });
    };

    list?.addEventListener("change", (e) => {
      const select = e.target.closest("[data-skill-select]");
      if (!select) return;
      syncNote(select.closest(".skill-row"));
    });

    addBtn?.addEventListener("click", () => {
      if (!template || !list) return;
      const html = template.innerHTML.split("__i__").join(String(list.children.length));
      list.insertAdjacentHTML("beforeend", html);
      reindex();
    });

    list?.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-remove-skill]");
      if (!btn) return;
      const rows = list.querySelectorAll(".skill-row");
      if (rows.length <= 1) {
        const row = rows[0];
        const select = row?.querySelector("[data-skill-select]");
        if (select) select.value = "";
        if (row) syncNote(row);
        return;
      }
      btn.closest(".skill-row")?.remove();
      reindex();
    });

    list?.querySelectorAll(".skill-row").forEach((row) => syncNote(row));
  });

  document.querySelectorAll("[data-accent-field]").forEach((box) => {
    const select = box.querySelector("[data-accent-select]");
    const note = box.querySelector("[data-accent-other]");
    const row = box.querySelector(".accent-other-row");
    if (!select || !note || !row) return;

    const syncAccentOther = () => {
      const isOther = select.value === "other";
      row.classList.toggle("is-other", isOther);
      note.disabled = !isOther;
      if (!isOther) note.value = "";
    };

    select.addEventListener("change", syncAccentOther);
    syncAccentOther();
  });

  document.querySelectorAll("[data-health-field]").forEach((box) => {
    const radios = box.querySelectorAll("[data-health-well]");
    const wrap = box.querySelector("[data-health-detail-wrap]");
    const detail = box.querySelector("[data-health-detail]");

    const syncHealth = () => {
      const unhealthy = box.querySelector('[data-health-well][value="unhealthy"]:checked');
      const active = !!unhealthy;
      wrap?.classList.toggle("is-active", active);
      if (detail) {
        detail.disabled = !active;
        if (!active) detail.value = "";
      }
    };

    radios.forEach((radio) => radio.addEventListener("change", syncHealth));
    syncHealth();
  });

  document.querySelectorAll("[data-artistic-membership]").forEach((box) => {
    const hasRadios = box.querySelectorAll("[data-artistic-has]");
    const orgsPanel = box.querySelector("[data-artistic-orgs-panel]");
    const list = box.querySelector("[data-artistic-org-list]");
    const template = box.querySelector("[data-artistic-org-template]");
    const addBtn = box.querySelector("[data-add-artistic-org]");

    const syncPanels = () => {
      const hasYes = box.querySelector('[data-artistic-has][value="yes"]:checked');
      if (orgsPanel) orgsPanel.hidden = !hasYes;
      list?.querySelectorAll("select, input, button").forEach((el) => {
        el.disabled = !hasYes;
      });
    };

    const syncOther = (row) => {
      const select = row.querySelector("[data-artistic-org-select]");
      const other = row.querySelector("[data-artistic-org-other]");
      if (!select || !other) return;
      const isOther = select.value === "other";
      row.classList.toggle("is-other", isOther);
      const hasYes = box.querySelector('[data-artistic-has][value="yes"]:checked');
      other.disabled = !isOther || !hasYes;
      if (!isOther) other.value = "";
    };

    const reindex = () => {
      [...(list?.querySelectorAll(".artistic-org-row") || [])].forEach((row, i) => {
        const select = row.querySelector("[data-artistic-org-select]");
        const other = row.querySelector("[data-artistic-org-other]");
        if (select) select.name = `artistic_org_items[${i}][org]`;
        if (other) other.name = `artistic_org_items[${i}][other]`;
      });
    };

    list?.addEventListener("change", (e) => {
      const select = e.target.closest("[data-artistic-org-select]");
      if (!select) return;
      syncOther(select.closest(".artistic-org-row"));
    });

    addBtn?.addEventListener("click", () => {
      if (!template || !list) return;
      const html = template.innerHTML.split("__i__").join(String(list.children.length));
      list.insertAdjacentHTML("beforeend", html);
      reindex();
      const last = list.lastElementChild;
      if (last) syncOther(last);
    });

    list?.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-remove-artistic-org]");
      if (!btn) return;
      const rows = list.querySelectorAll(".artistic-org-row");
      if (rows.length <= 1) {
        const row = rows[0];
        const select = row?.querySelector("[data-artistic-org-select]");
        if (select) select.value = "";
        if (row) syncOther(row);
        return;
      }
      btn.closest(".artistic-org-row")?.remove();
      reindex();
    });

    hasRadios.forEach((radio) => radio.addEventListener("change", syncPanels));
    list?.querySelectorAll(".artistic-org-row").forEach(syncOther);
    syncPanels();
  });

  document.querySelectorAll("[data-activity-items]").forEach((box) => {
    let map = {};
    try {
      map = JSON.parse(box.getAttribute("data-activity-map") || "{}");
    } catch (err) {
      map = {};
    }
    const list = box.querySelector("[data-activity-list]");
    const template = box.querySelector("[data-activity-template]");
    const addBtn = box.querySelector("[data-add-activity]");

    const fillSpecialty = (row, keepValue) => {
      const catSel = row.querySelector("[data-activity-category]");
      const specSel = row.querySelector("[data-activity-specialty]");
      if (!catSel || !specSel) return;
      const cat = catSel.value;
      const prev = keepValue ? specSel.value : "";
      specSel.innerHTML = "";
      if (!cat || !map[cat]) {
        specSel.disabled = true;
        const opt = document.createElement("option");
        opt.value = "";
        opt.textContent = "اول تخصص هنری را انتخاب کنید";
        specSel.appendChild(opt);
        return;
      }
      const keys = Object.keys(map[cat]);
      const isNoneCategory = cat === "none";
      specSel.disabled = false;
      if (!isNoneCategory) {
        const placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = "انتخاب تخصص…";
        specSel.appendChild(placeholder);
      }
      keys.forEach((key) => {
        const opt = document.createElement("option");
        opt.value = key;
        opt.textContent = map[cat][key];
        if (prev === key || (isNoneCategory && !prev && key === "activity_none")) opt.selected = true;
        specSel.appendChild(opt);
      });
      if (isNoneCategory && !specSel.value && keys.includes("activity_none")) {
        specSel.value = "activity_none";
      }
    };

    const reindex = () => {
      [...list.querySelectorAll(".activity-row")].forEach((row, i) => {
        const cat = row.querySelector("[data-activity-category]");
        const spec = row.querySelector("[data-activity-specialty]");
        if (cat) cat.name = `activity_items[${i}][category]`;
        if (spec) spec.name = `activity_items[${i}][specialty]`;
      });
    };

    list?.addEventListener("change", (e) => {
      const cat = e.target.closest("[data-activity-category]");
      if (!cat) return;
      fillSpecialty(cat.closest(".activity-row"), false);
    });

    addBtn?.addEventListener("click", () => {
      if (!template || !list) return;
      const html = template.innerHTML.split("__i__").join(String(list.children.length));
      list.insertAdjacentHTML("beforeend", html);
      reindex();
    });

    list?.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-remove-activity]");
      if (!btn) return;
      const rows = list.querySelectorAll(".activity-row");
      if (rows.length <= 1) {
        const row = rows[0];
        const cat = row?.querySelector("[data-activity-category]");
        if (cat) cat.value = "";
        if (row) fillSpecialty(row, false);
        return;
      }
      btn.closest(".activity-row")?.remove();
      reindex();
    });

    if (box.hasAttribute("data-activities-required")) {
      const form = box.closest("form");
      form?.addEventListener("submit", (e) => {
        const any = [...box.querySelectorAll("[data-activity-specialty]")].some((sel) => sel.value);
        if (!any) {
          e.preventDefault();
          window.alert("حداقل یک تخصص از نوع فعالیت انتخاب کنید.");
        }
      });
    }
  });

  document.querySelectorAll("[data-activity-search]").forEach((box) => {
    let map = {};
    try {
      map = JSON.parse(box.getAttribute("data-activity-map") || "{}");
    } catch (err) {
      map = {};
    }
    const catSel = box.querySelector("[data-activity-category]");
    const specSel = box.querySelector("[data-activity-specialty]");
    if (!catSel || !specSel) return;

    const fillSpecialty = (keepValue) => {
      const cat = catSel.value;
      const prev = keepValue ? specSel.value : "";
      specSel.innerHTML = "";
      if (!cat || !map[cat]) {
        specSel.disabled = true;
        const opt = document.createElement("option");
        opt.value = "";
        opt.textContent = "اول تخصص هنری را انتخاب کنید";
        specSel.appendChild(opt);
        return;
      }
      specSel.disabled = false;
      const placeholder = document.createElement("option");
      placeholder.value = "";
      placeholder.textContent = "همه";
      specSel.appendChild(placeholder);
      Object.keys(map[cat]).forEach((key) => {
        const opt = document.createElement("option");
        opt.value = key;
        opt.textContent = map[cat][key];
        if (prev === key) opt.selected = true;
        specSel.appendChild(opt);
      });
      if (cat === "none" && !specSel.value && map[cat].activity_none) {
        specSel.value = "activity_none";
      }
    };

    catSel.addEventListener("change", () => fillSpecialty(false));
    fillSpecialty(true);
  });

  const openPanelHashTarget = () => {
    if (window.location.hash !== "#edit-profile") return;
    const details = document.getElementById("edit-profile");
    if (details instanceof HTMLDetailsElement) {
      details.open = true;
    }
  };

  openPanelHashTarget();
  window.addEventListener("hashchange", openPanelHashTarget);

  const formatPremiumRemaining = (seconds, short = false) => {
    if (seconds <= 0) return short ? "تمام" : "اعتبار ویژه تمام شد";
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    if (short) {
      if (days > 0) return `${days} روز`;
      if (hours > 0) return `${hours} ساعت`;
      return `${minutes} دقیقه`;
    }
    if (days > 0) return `${days} روز و ${hours} ساعت`;
    if (hours > 0) return `${hours} ساعت و ${minutes} دقیقه`;
    return `${minutes} دقیقه`;
  };

  const tickPremiumCountdowns = () => {
    const now = Math.floor(Date.now() / 1000);
    document.querySelectorAll("[data-premium-until-ts]").forEach((box) => {
      const until = Number.parseInt(box.getAttribute("data-premium-until-ts") || "0", 10);
      const out = box.querySelector("[data-premium-countdown]");
      if (!until || !out) return;
      const short = box.classList.contains("nav-premium-countdown");
      out.textContent = formatPremiumRemaining(until - now, short);
    });
  };

  tickPremiumCountdowns();
  window.setInterval(tickPremiumCountdowns, 60000);

  document.querySelectorAll("[data-faq-accordion]").forEach((box) => {
    const items = box.querySelectorAll(".faq-item");
    items.forEach((item) => {
      item.addEventListener("toggle", () => {
        if (!item.open) return;
        items.forEach((other) => {
          if (other !== item) other.open = false;
        });
      });
    });
  });

  const memberSearchForm = document.querySelector("[data-member-search-form]");
  const memberSearchResults = document.querySelector("[data-member-search-results]");
  const nameSearchInput = document.querySelector("[data-name-search-input]");
  const nameSearchField = document.querySelector("[data-name-search-field]");
  const nameSearchClear = document.querySelector("[data-name-search-clear]");

  if (memberSearchForm && memberSearchResults) {
    let resultsTimer = 0;
    let suggestTimer = 0;
    let resultsAbort = null;
    let suggestAbort = null;
    let predictedFull = "";
    const searchQuickChips = [...memberSearchForm.querySelectorAll("[data-search-chip]")];

    const formHasActiveSearch = () => {
      const params = new URLSearchParams(new FormData(memberSearchForm));
      for (const [key, value] of params.entries()) {
        const trimmed = String(value).trim();
        if (trimmed === "") continue;
        if (key === "city" && trimmed === "همه") continue;
        return true;
      }
      return false;
    };

    const setFieldValue = (name, value) => {
      const field = memberSearchForm.querySelector(`[name="${name}"]`);
      if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) {
        return;
      }
      field.disabled = false;
      field.value = value;
      field.dispatchEvent(new Event("change", { bubbles: true }));
      field.dispatchEvent(new Event("input", { bubbles: true }));
    };

    const clearSearchForm = () => {
      memberSearchForm.querySelectorAll("input, select, textarea").forEach((el) => {
        if (!(el instanceof HTMLInputElement || el instanceof HTMLSelectElement || el instanceof HTMLTextAreaElement)) return;
        if (el.type === "hidden" || el.type === "submit" || el.type === "button") return;
        if (el.type === "checkbox" || el.type === "radio") {
          el.checked = false;
          return;
        }
        el.value = "";
        el.dispatchEvent(new Event("change", { bubbles: true }));
      });
    };

    const chipMatches = (chip) => {
      let spec = {};
      try {
        spec = JSON.parse(chip.getAttribute("data-search-chip") || "{}");
      } catch (err) {
        spec = {};
      }
      if (spec.clear === "1" || spec.clear === 1) {
        return !formHasActiveSearch();
      }
      return Object.keys(spec).every((key) => {
        const field = memberSearchForm.querySelector(`[name="${key}"]`);
        if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) {
          return false;
        }
        return String(field.value || "") === String(spec[key]);
      });
    };

    const syncSearchChips = () => {
      searchQuickChips.forEach((chip) => {
        const active = chipMatches(chip);
        chip.classList.toggle("is-active", active);
        chip.setAttribute("aria-pressed", active ? "true" : "false");
      });
    };

    const applySearchChip = (chip) => {
      let spec = {};
      try {
        spec = JSON.parse(chip.getAttribute("data-search-chip") || "{}");
      } catch (err) {
        spec = {};
      }
      const isActive = chip.classList.contains("is-active");
      if (spec.clear === "1" || spec.clear === 1) {
        clearSearchForm();
        refreshResults();
        syncSearchChips();
        return;
      }
      if (isActive) {
        Object.keys(spec).forEach((key) => setFieldValue(key, ""));
      } else {
        if (Object.prototype.hasOwnProperty.call(spec, "province")) {
          setFieldValue("province", String(spec.province || ""));
          window.setTimeout(() => {
            if (Object.prototype.hasOwnProperty.call(spec, "city")) {
              setFieldValue("city", String(spec.city || ""));
            }
            Object.keys(spec).forEach((key) => {
              if (key === "province" || key === "city") return;
              setFieldValue(key, String(spec[key] ?? ""));
            });
            refreshResults();
            syncSearchChips();
          }, 0);
          return;
        }
        Object.keys(spec).forEach((key) => setFieldValue(key, String(spec[key] ?? "")));
      }
      refreshResults();
      syncSearchChips();
    };

    const clearPrediction = () => {
      predictedFull = "";
    };

    const syncClearButton = () => {
      if (!nameSearchClear || !nameSearchInput) return;
      nameSearchClear.hidden = (nameSearchInput.value || "") === "";
    };

    const buildFormQuery = (forAjax = true) => {
      const params = new URLSearchParams(new FormData(memberSearchForm));
      if (forAjax) {
        params.set("ajax", "1");
      } else {
        params.delete("ajax");
      }
      params.delete("page");
      return params.toString();
    };

    const refreshResults = () => {
      window.clearTimeout(resultsTimer);
      resultsAbort?.abort();
      resultsTimer = window.setTimeout(async () => {
        const controller = new AbortController();
        resultsAbort = controller;
        const resultsEl = document.querySelector("[data-member-search-results]") || memberSearchResults;
        resultsEl.classList.add("is-loading");
        try {
          const res = await fetch(`${memberSearchForm.getAttribute("action") || "search-users.php"}?${buildFormQuery(true)}`, {
            signal: controller.signal,
            headers: { "X-Requested-With": "XMLHttpRequest" },
          });
          if (!res.ok) return;
          resultsEl.innerHTML = await res.text();
          const query = buildFormQuery(false);
          window.history.replaceState({}, "", query ? `search-users.php?${query}` : "search-users.php");
          syncSearchChips();
        } catch (err) {
          if (err?.name !== "AbortError") {
            /* ignore */
          }
        } finally {
          resultsEl.classList.remove("is-loading");
        }
      }, 280);
    };

    searchQuickChips.forEach((chip) => {
      chip.addEventListener("click", () => applySearchChip(chip));
    });

    const nameSearchSuggest = document.querySelector("[data-name-search-suggest]");

    const hideNameSuggest = () => {
      if (!(nameSearchSuggest instanceof HTMLElement)) return;
      nameSearchSuggest.hidden = true;
      nameSearchSuggest.innerHTML = "";
    };

    const renderNameSuggest = (items, query) => {
      if (!(nameSearchSuggest instanceof HTMLElement)) return;
      const q = String(query || "").trim();
      if (!q || !items.length) {
        hideNameSuggest();
        return;
      }
      nameSearchSuggest.innerHTML = "";
      items.forEach((item) => {
        const id = Number(item.id || 0);
        if (!id) return;
        const li = document.createElement("li");
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "name-search-suggest-item";
        btn.setAttribute("data-member-preview", String(id));
        const nameEl = document.createElement("strong");
        nameEl.textContent = String(item.name || "کاربر");
        btn.appendChild(nameEl);
        const login = String(item.login || "").trim();
        if (login && login !== String(item.name || "")) {
          const loginEl = document.createElement("span");
          loginEl.textContent = login;
          btn.appendChild(loginEl);
        }
        li.appendChild(btn);
        nameSearchSuggest.appendChild(li);
      });
      nameSearchSuggest.hidden = nameSearchSuggest.childElementCount === 0;
    };

    const pickPrediction = (items, query) => {
      const q = query.trim().toLocaleLowerCase("fa");
      if (!q) return "";
      for (const item of items) {
        for (const candidate of [item.name, item.login]) {
          const text = String(candidate || "");
          if (text.toLocaleLowerCase("fa").includes(q)) return text;
        }
      }
      return "";
    };

    const fetchPrediction = () => {
      if (!nameSearchInput) return;
      window.clearTimeout(suggestTimer);
      suggestAbort?.abort();
      const query = (nameSearchInput.value || "").trim();
      if (query.length < 2) {
        clearPrediction();
        hideNameSuggest();
        return;
      }
      suggestTimer = window.setTimeout(async () => {
        const controller = new AbortController();
        suggestAbort = controller;
        try {
          const params = new URLSearchParams({ q: query });
          const res = await fetch(`search-members-suggest.php?${params.toString()}`, {
            signal: controller.signal,
            headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
          });
          if (!res.ok) {
            hideNameSuggest();
            return;
          }
          const data = await res.json();
          const items = Array.isArray(data.items) ? data.items : [];
          predictedFull = pickPrediction(items, query);
          renderNameSuggest(items, query);
        } catch (err) {
          if (err?.name !== "AbortError") {
            clearPrediction();
            hideNameSuggest();
          }
        }
      }, 200);
    };

    const acceptPrediction = () => {
      if (!nameSearchInput || !predictedFull) return false;
      nameSearchInput.value = predictedFull;
      clearPrediction();
      hideNameSuggest();
      syncClearButton();
      refreshResults();
      return true;
    };

    if (nameSearchInput) {
      nameSearchField?.addEventListener("click", (e) => {
        if (e.target.closest("[data-name-search-clear], [data-name-search-suggest]")) return;
        nameSearchInput.focus();
      });

      nameSearchSuggest?.addEventListener("click", (e) => {
        const btn = e.target.closest(".name-search-suggest-item");
        if (!(btn instanceof HTMLElement)) return;
        const picked = btn.querySelector("strong")?.textContent || "";
        if (picked) nameSearchInput.value = picked;
        clearPrediction();
        hideNameSuggest();
        syncClearButton();
        refreshResults();
      });

      nameSearchClear?.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        nameSearchInput.value = "";
        clearPrediction();
        hideNameSuggest();
        syncClearButton();
        refreshResults();
        nameSearchInput.focus();
      });

      nameSearchInput.addEventListener("input", () => {
        syncClearButton();
        fetchPrediction();
        refreshResults();
      });

      nameSearchInput.addEventListener("keydown", (e) => {
        if (e.key === "Escape") {
          hideNameSuggest();
          return;
        }
        if (!predictedFull) return;
        if (e.key === "Tab" || e.key === "ArrowRight" || e.key === "Enter") {
          const atEnd = nameSearchInput.selectionStart === nameSearchInput.value.length
            && nameSearchInput.selectionEnd === nameSearchInput.value.length;
          if (e.key === "Enter" || e.key === "Tab" || atEnd) {
            if (e.key !== "Enter") e.preventDefault();
            acceptPrediction();
          }
        }
      });

      document.addEventListener("click", (e) => {
        if (!(e.target instanceof Node) || !nameSearchField?.contains(e.target)) {
          hideNameSuggest();
        }
      });

      syncClearButton();
      if ((nameSearchInput.value || "").trim().length >= 2) {
        fetchPrediction();
      }
    }

    const savedSearchesRoot = document.querySelector("[data-saved-searches]");
    const savedCompose = savedSearchesRoot?.querySelector("[data-saved-search-compose]");
    const savedNameInput = savedSearchesRoot?.querySelector("[data-saved-search-name]");
    const savedList = savedSearchesRoot?.querySelector("[data-saved-searches-list]");
    const savedNonce = memberSearchForm.getAttribute("data-saved-search-nonce") || "";

    const syncSavedSearchOpenButtons = () => {
      const canSave = formHasActiveSearch();
      document.querySelectorAll("[data-saved-search-open]").forEach((btn) => {
        if (!(btn instanceof HTMLButtonElement)) return;
        btn.disabled = !canSave;
        btn.title = canSave ? "ذخیره فیلترهای فعلی" : "اول یک فیلتر انتخاب کنید";
      });
    };

    memberSearchForm.addEventListener("change", () => {
      syncSearchChips();
      syncSavedSearchOpenButtons();
      refreshResults();
    });
    memberSearchForm.addEventListener("input", (e) => {
      const el = e.target;
      if (!(el instanceof HTMLElement)) return;
      if (el.matches("input:not([type=hidden]):not([type=checkbox]):not([type=radio]), textarea")) {
        syncSearchChips();
        syncSavedSearchOpenButtons();
        refreshResults();
      }
    });
    memberSearchForm.addEventListener("submit", (e) => {
      e.preventDefault();
      refreshResults();
    });
    syncSearchChips();

    const renderSavedSearches = (searches) => {
      if (!savedList) return;
      const items = Array.isArray(searches) ? searches : [];
      if (items.length === 0) {
        savedList.innerHTML = '<p class="saved-searches-empty meta" data-saved-searches-empty>هنوز جستجویی ذخیره نکرده‌اید.</p>';
        return;
      }
      savedList.innerHTML = items.map((item) => {
        const id = String(item.id || "");
        const name = String(item.name || "جستجو");
        const href = String(item.href || "search-users.php");
        return `<div class="saved-search-chip" data-saved-search-item="${id.replace(/"/g, "&quot;")}">
          <a class="saved-search-chip-link" href="${href.replace(/"/g, "&quot;")}">${name.replace(/</g, "&lt;")}</a>
          <button type="button" class="saved-search-chip-delete" data-saved-search-delete="${id.replace(/"/g, "&quot;")}" aria-label="حذف ${name.replace(/"/g, "&quot;")}">×</button>
        </div>`;
      }).join("");
    };

    const postSavedSearch = async (payload) => {
      const body = new FormData();
      body.set("saved_search_ajax", "1");
      body.set("_wpnonce", savedNonce);
      Object.entries(payload).forEach(([key, value]) => body.set(key, String(value)));
      if (payload.saved_search_action === "save") {
        const params = new URLSearchParams(new FormData(memberSearchForm));
        params.forEach((value, key) => {
          body.append(`filters[${key}]`, value);
        });
      }
      const res = await fetch("search-users.php", {
        method: "POST",
        body,
        headers: { "X-Requested-With": "XMLHttpRequest" },
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data?.ok) {
        window.alert(data?.error || "ذخیره جستجو انجام نشد.");
        return null;
      }
      return data;
    };

    document.querySelectorAll("[data-saved-search-open]").forEach((btn) => {
      btn.addEventListener("click", () => {
        if (!(btn instanceof HTMLButtonElement) || btn.disabled) return;
        if (!savedCompose || !savedNameInput) return;
        savedCompose.hidden = false;
        if (!savedNameInput.value.trim()) {
          savedNameInput.placeholder = "مثلاً بازیگر زن تهران ۲۵–۳۵";
        }
        savedNameInput.focus();
      });
    });

    savedSearchesRoot?.querySelector("[data-saved-search-cancel]")?.addEventListener("click", () => {
      if (savedCompose) savedCompose.hidden = true;
    });

    savedSearchesRoot?.querySelector("[data-saved-search-save]")?.addEventListener("click", async () => {
      if (!formHasActiveSearch()) {
        window.alert("اول حداقل یک فیلتر انتخاب کنید.");
        return;
      }
      const data = await postSavedSearch({
        saved_search_action: "save",
        saved_search_name: savedNameInput?.value || "",
      });
      if (!data) return;
      renderSavedSearches(data.searches);
      if (savedCompose) savedCompose.hidden = true;
      if (savedNameInput) savedNameInput.value = "";
    });

    savedSearchesRoot?.addEventListener("click", async (e) => {
      const del = e.target.closest("[data-saved-search-delete]");
      if (!del) return;
      e.preventDefault();
      const id = del.getAttribute("data-saved-search-delete") || "";
      if (!id) return;
      if (!window.confirm("این جستجوی ذخیره‌شده حذف شود؟")) return;
      const data = await postSavedSearch({
        saved_search_action: "delete",
        saved_search_id: id,
      });
      if (!data) return;
      renderSavedSearches(data.searches);
    });

    syncSavedSearchOpenButtons();
  }

  document.querySelectorAll("[data-password-confirm-field]").forEach((field) => {
    const form = field.closest("form");
    const pass = form?.querySelector("[data-password-source]");
    const pass2 = field.querySelector("[data-password-confirm]");
    const msg = field.querySelector("[data-password-mismatch-msg]");
    if (!pass || !pass2 || !msg) return;

    const syncPasswordMatch = () => {
      const mismatch = pass2.value.length > 0 && pass.value !== pass2.value;
      msg.hidden = !mismatch;
      field.classList.toggle("is-invalid", mismatch);
      pass2.setAttribute("aria-invalid", mismatch ? "true" : "false");
      return !mismatch;
    };

    pass.addEventListener("input", syncPasswordMatch);
    pass2.addEventListener("input", syncPasswordMatch);
    pass2.addEventListener("blur", syncPasswordMatch);

    if (form) {
      form.addEventListener("submit", (e) => {
        if (pass.value !== pass2.value) {
          e.preventDefault();
          field.classList.add("is-invalid");
          pass2.setAttribute("aria-invalid", "true");
          msg.hidden = false;
          pass2.focus();
        }
      });
    }

    if (!msg.hidden) {
      field.classList.add("is-invalid");
    }
  });

  document.querySelectorAll("[data-talent-profile-toggle]").forEach((form) => {
    const activityBox = form.querySelector("[data-activity-items]");
    if (!activityBox) return;

    const hasActingSpecialty = () => {
      for (const row of activityBox.querySelectorAll(".activity-row")) {
        const cat = row.querySelector("[data-activity-category]")?.value || "";
        const spec = row.querySelector("[data-activity-specialty]")?.value || "";
        if (spec && cat === "acting") return true;
        if (cat === "none" || spec === "activity_none") return true;
      }
      return false;
    };

    const hasDirectingSpecialty = () => {
      for (const row of activityBox.querySelectorAll(".activity-row")) {
        const cat = row.querySelector("[data-activity-category]")?.value || "";
        const spec = row.querySelector("[data-activity-specialty]")?.value || "";
        if (spec && cat === "directing") return true;
      }
      return false;
    };

    const syncTalentProfileFields = () => {
      const hideTalentFields = !hasActingSpecialty();
      const enableArtisticWorks = hasDirectingSpecialty();

      form.querySelectorAll("[data-talent-profile-field]").forEach((wrap) => {
        wrap.hidden = hideTalentFields;
        wrap.classList.remove("is-talent-muted");
        wrap.querySelectorAll("input, select, textarea, button").forEach((el) => {
          if (hideTalentFields) {
            if (el.required) {
              el.dataset.talentWasRequired = "1";
              el.required = false;
            }
            el.disabled = true;
            return;
          }
          if (el.dataset.talentWasRequired === "1") {
            el.required = true;
            delete el.dataset.talentWasRequired;
          }
          el.disabled = false;
        });
      });

      form.querySelectorAll("[data-health-field]").forEach((box) => {
        if (hideTalentFields) return;
        const unhealthy = box.querySelector('[data-health-well][value="unhealthy"]')?.checked;
        const detail = box.querySelector("[data-health-detail]");
        if (detail) {
          detail.disabled = !unhealthy;
        }
      });

      form.querySelectorAll("[data-accent-field]").forEach((box) => {
        if (hideTalentFields) return;
        const isOther = box.querySelector("[data-accent-select]")?.value === "other";
        const other = box.querySelector("[data-accent-other]");
        if (other) {
          other.disabled = !isOther;
        }
      });

      form.querySelectorAll(".skill-row").forEach((row) => {
        if (hideTalentFields) return;
        const isOther = row.querySelector("[data-skill-select]")?.value === "other";
        const note = row.querySelector("[data-skill-note]");
        if (note) {
          note.disabled = !isOther;
        }
      });

      const nonTalentPhoto = form.querySelector("[data-non-talent-profile-photo]");
      if (nonTalentPhoto) {
        nonTalentPhoto.hidden = !hideTalentFields;
        nonTalentPhoto.querySelectorAll("input, select, textarea, button").forEach((el) => {
          if (hideTalentFields) {
            if (el.dataset.talentWasRequired === "1" || el.hasAttribute("data-profile-photo-single")) {
              el.required = true;
            }
            el.disabled = false;
          } else {
            if (el.required) {
              el.dataset.talentWasRequired = "1";
              el.required = false;
            }
            el.disabled = true;
          }
        });
      }

      form.querySelectorAll("[data-talent-required-mark]").forEach((mark) => {
        mark.hidden = hideTalentFields;
      });

      form.querySelectorAll("[data-director-profile-field]").forEach((wrap) => {
        wrap.hidden = !enableArtisticWorks;
        wrap.classList.remove("is-talent-muted");
        wrap.querySelectorAll("input, select, textarea, button").forEach((el) => {
          el.disabled = !enableArtisticWorks;
        });
      });

      const submitBtn = form.querySelector("[data-register-submit]");
      const rulesCheckbox = form.querySelector("[data-rules-consent-checkbox]");
      if (submitBtn) {
        submitBtn.disabled = !(rulesCheckbox && rulesCheckbox.checked);
      }
    };

    const rulesCheckbox = form.querySelector("[data-rules-consent-checkbox]");
    if (rulesCheckbox) {
      rulesCheckbox.addEventListener("change", syncTalentProfileFields);
    }

    activityBox.addEventListener("change", syncTalentProfileFields);
    activityBox.addEventListener("click", (e) => {
      if (e.target.closest("[data-add-activity], [data-remove-activity]")) {
        window.setTimeout(syncTalentProfileFields, 0);
      }
    });
    syncTalentProfileFields();
  });

  document.querySelectorAll("form[data-register-form]").forEach((form) => {
    const syncRegisterSubmit = () => {
      const submitBtn = form.querySelector("[data-register-submit]");
      if (!(submitBtn instanceof HTMLButtonElement)) return;
      const accountStep = form.querySelector(".register-step-account");
      const accountLocked =
        accountStep instanceof HTMLFieldSetElement
          ? accountStep.disabled || accountStep.classList.contains("is-locked")
          : false;
      const rulesCheckbox = form.querySelector("[data-rules-consent-checkbox]");
      const rulesOk =
        !(rulesCheckbox instanceof HTMLInputElement) || rulesCheckbox.checked;
      submitBtn.disabled = accountLocked || !rulesOk;
    };

    const rulesCheckbox = form.querySelector("[data-rules-consent-checkbox]");
    if (rulesCheckbox instanceof HTMLInputElement) {
      rulesCheckbox.addEventListener("change", syncRegisterSubmit);
    }
    syncRegisterSubmit();

    const focusRegisterField = (target) => {
      if (!target) return;
      const el =
        typeof target === "string"
          ? form.querySelector("#" + CSS.escape(target)) ||
            form.querySelector(`[name="${CSS.escape(target)}"]`) ||
            form.querySelector(`input[name="${CSS.escape(target)}"]`)
          : target;
      if (!el) return;
      const scrollTarget = el.closest(".field, fieldset, .jalali-birth, .portrait-upload-card") || el;
      scrollTarget.scrollIntoView({ behavior: "smooth", block: "center" });
      window.setTimeout(() => {
        try {
          el.focus({ preventScroll: true });
        } catch (_err) {}
      }, 150);
    };

    form.addEventListener(
      "invalid",
      (event) => {
        const first = event.target;
        if (!(first instanceof HTMLElement)) return;
        const wrap = first.closest(".field, fieldset.field, .jalali-birth, .portrait-upload-card");
        if (wrap) {
          wrap.classList.add("is-invalid");
          const hint = wrap.querySelector("[data-field-req-hint]");
          if (hint) hint.hidden = false;
        }
        if (first instanceof HTMLInputElement || first instanceof HTMLSelectElement || first instanceof HTMLTextAreaElement) {
          if (first.validity.valueMissing) {
            first.setCustomValidity("این گزینه ستاره‌دار الزامی است.");
          } else if (first.validity.tooShort || first.validity.patternMismatch || first.validity.typeMismatch) {
            first.setCustomValidity(first.title || "مقدار این فیلد درست نیست.");
          }
          first.addEventListener(
            "input",
            () => {
              first.setCustomValidity("");
              wrap?.classList.remove("is-invalid");
              const hint = wrap?.querySelector("[data-field-req-hint]");
              if (hint) hint.hidden = true;
            },
            { once: true }
          );
        }
        if (form.dataset.registerInvalidHandled === "1") return;
        form.dataset.registerInvalidHandled = "1";
        window.setTimeout(() => {
          delete form.dataset.registerInvalidHandled;
        }, 300);
        focusRegisterField(first);
      },
      true
    );

    const markInvalidKeys = (keys) => {
      keys.forEach((key) => {
        if (!key) return;
        const el =
          form.querySelector("#" + CSS.escape(key)) ||
          form.querySelector(`[name="${CSS.escape(key)}"]`) ||
          form.querySelector(`input[name="${CSS.escape(key)}"]`);
        const wrap =
          el?.closest(".field, fieldset.field, .jalali-birth, .portrait-upload-card") ||
          form.querySelector(`[data-field-key="${CSS.escape(key)}"]`);
        if (wrap) {
          wrap.classList.add("is-invalid");
          const hint = wrap.querySelector("[data-field-req-hint]");
          if (hint) hint.hidden = false;
        }
        if (key.startsWith("photo_") || key === "photo_medium_single") {
          form.querySelector("#profile-photos-actor")?.classList.add("is-invalid");
          form.querySelector("#profile-photo-single")?.classList.add("is-invalid");
        }
        if (key === "birth_jd" || key === "birth_jm" || key === "birth_jy") {
          form.querySelector("[data-jalali-birth]")?.classList.add("is-invalid");
        }
        if (key === "province" || key === "city") {
          el?.closest(".form-grid, .field")?.classList.add("is-invalid");
          form.querySelector("#province")?.closest(".field")?.classList.add("is-invalid");
          form.querySelector("#city")?.closest(".field")?.classList.add("is-invalid");
        }
        if (key === "health_well") {
          form.querySelector("[data-health-field]")?.classList.add("is-invalid");
        }
        if (key === "activities") {
          form.querySelector("[data-activity-items]")?.closest(".field, fieldset")?.classList.add("is-invalid");
        }
      });
    };

    const invalidAttr = form.getAttribute("data-invalid-fields") || "";
    if (invalidAttr) {
      markInvalidKeys(invalidAttr.split(",").map((s) => s.trim()).filter(Boolean));
    }

    const focusId = form.getAttribute("data-focus-field");
    if (focusId) {
      window.setTimeout(() => focusRegisterField(focusId), 80);
    }

    const cfg = window.CASTING_REGISTER || {};
    const draftKey = cfg.draftKey || "casting_register_draft_v1";
    const skipDraftNames = new Set(["password", "password2", "otp_code", "_wpnonce", "casting_submit"]);

    const collectDraft = () => {
      const data = {};
      const fd = new FormData(form);
      fd.forEach((value, key) => {
        if (skipDraftNames.has(key) || key.startsWith("photo_") || key === "video") return;
        if (typeof value !== "string") return;
        if (Object.prototype.hasOwnProperty.call(data, key)) {
          const prev = data[key];
          data[key] = Array.isArray(prev) ? prev.concat(value) : [prev, value];
        } else {
          data[key] = value;
        }
      });
      form.querySelectorAll("input[type='checkbox']").forEach((el) => {
        if (!el.name || skipDraftNames.has(el.name)) return;
        if (el.checked) data[el.name] = el.value || "1";
        else if (!(el.name in data)) data[el.name] = "";
      });
      return data;
    };

    const saveDraftLocal = () => {
      try {
        localStorage.setItem(draftKey, JSON.stringify(collectDraft()));
      } catch (_err) {}
    };

    const restoreDraftLocal = () => {
      let raw = null;
      try {
        raw = localStorage.getItem(draftKey);
      } catch (_err) {
        return;
      }
      if (!raw) return;
      let data;
      try {
        data = JSON.parse(raw);
      } catch (_err) {
        return;
      }
      if (!data || typeof data !== "object") return;
      Object.keys(data).forEach((key) => {
        if (skipDraftNames.has(key)) return;
        const val = data[key];
        const fields = form.querySelectorAll(`[name="${CSS.escape(key)}"]`);
        if (!fields.length) return;
        fields.forEach((el) => {
          if (el instanceof HTMLInputElement && (el.type === "password" || el.type === "file")) return;
          if (el instanceof HTMLInputElement && (el.type === "radio" || el.type === "checkbox")) {
            const checked = Array.isArray(val) ? val.includes(el.value) : String(val) === String(el.value);
            if (el.type === "checkbox" && !Array.isArray(val) && (val === "1" || val === "on" || val === true)) {
              el.checked = true;
            } else {
              el.checked = checked;
            }
            return;
          }
          if (el instanceof HTMLSelectElement || el instanceof HTMLTextAreaElement || el instanceof HTMLInputElement) {
            if (el.value === "" || el.value == null) {
              el.value = Array.isArray(val) ? String(val[0] ?? "") : String(val ?? "");
            }
          }
        });
      });
      form.querySelector("[data-location-province]")?.dispatchEvent(new Event("change", { bubbles: true }));
      const cityVal = typeof data.city === "string" ? data.city : "";
      const cityEl = form.querySelector("[data-location-city-input], select[data-location-city]");
      if (cityEl && cityVal && !cityEl.value) cityEl.value = cityVal;
      form.querySelector("[data-rules-consent-checkbox]")?.dispatchEvent(new Event("change", { bubbles: true }));
    };

    let draftTimer = 0;
    form.addEventListener("input", () => {
      window.clearTimeout(draftTimer);
      draftTimer = window.setTimeout(saveDraftLocal, 400);
    });
    form.addEventListener("change", () => {
      window.clearTimeout(draftTimer);
      draftTimer = window.setTimeout(saveDraftLocal, 200);
    });
    restoreDraftLocal();

    const applyPendingPreviews = (payload) => {
      const portraits = payload?.portraits || {};
      Object.keys(portraits).forEach((slot) => {
        const url = portraits[slot]?.url || portraits[slot]?.full || "";
        if (!url) return;
        const input = form.querySelector(`#photo_${CSS.escape(slot)}, input[name="photo_${CSS.escape(slot)}"]`);
        const card = input?.closest(".portrait-upload-card, [data-file-preview-card], .field") || null;
        const img = card?.querySelector("[data-file-preview-img], img");
        const empty = card?.querySelector("[data-file-preview-empty]");
        const frame = card?.querySelector("[data-file-preview-frame]");
        if (img) {
          img.src = url;
          img.hidden = false;
        } else if (frame) {
          const created = document.createElement("img");
          created.src = url;
          created.setAttribute("data-file-preview-img", "");
          created.alt = "";
          frame.prepend(created);
        }
        if (empty) empty.hidden = true;
        if (frame) frame.hidden = false;
      });
      const videoUrl = payload?.video?.url || "";
      if (videoUrl) {
        const video = form.querySelector("[data-file-preview-video]");
        const frame = form.querySelector(".field[data-file-preview-card] [data-file-preview-frame], [data-file-preview-frame]");
        if (video) {
          video.src = videoUrl;
          video.hidden = false;
        }
        if (frame) frame.hidden = false;
      }
    };

    const uploadPendingFile = async (input) => {
      if (!(input instanceof HTMLInputElement) || !input.files?.length || !cfg.uploadUrl || !cfg.nonce) return;
      const body = new FormData();
      body.append("_wpnonce", cfg.nonce);
      body.append(input.name, input.files[0]);
      const statusHost =
        input.closest(".field, fieldset, .portrait-upload-card")?.querySelector("[data-pending-upload-status]") || null;
      let statusEl = statusHost;
      if (!statusEl) {
        statusEl = document.createElement("p");
        statusEl.className = "field-hint";
        statusEl.setAttribute("data-pending-upload-status", "1");
        input.insertAdjacentElement("afterend", statusEl);
      }
      statusEl.textContent = "در حال ذخیره موقت فایل…";
      try {
        const res = await fetch(cfg.uploadUrl, { method: "POST", body, credentials: "same-origin" });
        const data = await res.json().catch(() => null);
        if (!data?.ok) {
          statusEl.textContent = data?.error || "ذخیره موقت فایل ناموفق بود.";
          return;
        }
        applyPendingPreviews(data);
        statusEl.textContent = "فایل ذخیره شد و با رفرش هم می‌ماند.";
        input.required = false;
        input.removeAttribute("required");
        saveDraftLocal();
      } catch (_err) {
        statusEl.textContent = "اتصال قطع شد؛ بعد از برگشت اینترنت دوباره همان فایل را انتخاب کنید.";
      }
    };

    form.querySelectorAll('input[type="file"][name^="photo_"], input[type="file"][name="video"]').forEach((input) => {
      input.addEventListener("change", () => {
        uploadPendingFile(input);
      });
    });

    form.addEventListener("submit", () => {
      try {
        localStorage.removeItem(draftKey);
      } catch (_err) {}
    });
  });

  const scrollTopBtn = document.querySelector("[data-scroll-top]");
  if (scrollTopBtn) {
    const getScrollY = () =>
      window.scrollY || document.documentElement.scrollTop || document.body.scrollTop || 0;

    const syncScrollTop = () => {
      scrollTopBtn.classList.toggle("is-visible", getScrollY() > 180);
    };

    window.addEventListener("scroll", syncScrollTop, { passive: true });
    document.addEventListener("scroll", syncScrollTop, { passive: true });
    window.addEventListener("resize", syncScrollTop);
    syncScrollTop();
    scrollTopBtn.addEventListener("click", () => {
      window.scrollTo({ top: 0, behavior: "smooth" });
      document.documentElement.scrollTo({ top: 0, behavior: "smooth" });
    });
  }

  if ("serviceWorker" in navigator && window.CASTING_PWA && window.CASTING_PWA.swUrl && !castingIsNativeAppShell()) {
    window.addEventListener("load", () => {
      navigator.serviceWorker
        .register(window.CASTING_PWA.swUrl, { scope: window.CASTING_PWA.scope || undefined })
        .catch(() => {});
    });
  }

  let portraitLightboxEl = null;
  let portraitLightboxImg = null;

  const closePortraitLightbox = () => {
    if (!portraitLightboxEl) return;
    portraitLightboxEl.classList.remove("is-open");
    document.body.style.overflow = "";
    window.setTimeout(() => {
      if (portraitLightboxEl && !portraitLightboxEl.classList.contains("is-open") && portraitLightboxImg) {
        portraitLightboxImg.removeAttribute("src");
      }
    }, 200);
  };

  const openPortraitLightbox = (src, alt) => {
    if (!src) return;
    if (!portraitLightboxEl) {
      portraitLightboxEl = document.createElement("button");
      portraitLightboxEl.type = "button";
      portraitLightboxEl.className = "portrait-lightbox";
      portraitLightboxEl.setAttribute("aria-label", "بستن تصویر");
      const frame = document.createElement("div");
      frame.className = "portrait-lightbox-frame media-protect";
      frame.setAttribute("data-media-protect", "");
      portraitLightboxImg = document.createElement("img");
      portraitLightboxImg.draggable = false;
      frame.appendChild(portraitLightboxImg);
      const wm = document.createElement("div");
      wm.className = "media-watermark";
      wm.setAttribute("aria-hidden", "true");
      const label = (window.CASTING_MEDIA_PROTECT && window.CASTING_MEDIA_PROTECT.watermark) || "";
      for (let i = 0; i < 3; i += 1) {
        const span = document.createElement("span");
        span.textContent = label;
        wm.appendChild(span);
      }
      frame.appendChild(wm);
      portraitLightboxEl.appendChild(frame);
      portraitLightboxEl.addEventListener("click", closePortraitLightbox);
      document.body.appendChild(portraitLightboxEl);
    }
    portraitLightboxImg.src = src;
    portraitLightboxImg.alt = alt || "";
    portraitLightboxEl.classList.add("is-open");
    document.body.style.overflow = "hidden";
  };

  document.addEventListener("click", (event) => {
    const trigger = event.target.closest("[data-portrait-lightbox]");
    if (!trigger) return;
    event.preventDefault();
    openPortraitLightbox(
      trigger.getAttribute("data-portrait-lightbox"),
      trigger.closest(".profile-portrait-thumb")?.querySelector("img")?.alt || trigger.querySelector("img")?.alt || ""
    );
  });

  document.addEventListener("click", (event) => {
    const back = event.target.closest("[data-panel-back]");
    if (!back || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }
    const ref = document.referrer;
    if (!ref) return;
    try {
      const prev = new URL(ref);
      if (prev.origin === window.location.origin && prev.href !== window.location.href) {
        event.preventDefault();
        window.history.back();
      }
    } catch (err) {
      /* fallback: follow href */
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") closePortraitLightbox();
  });

  const rulesLightbox = document.querySelector("[data-rules-lightbox]");
  if (rulesLightbox && rulesLightbox.parentElement !== document.body) {
    document.body.appendChild(rulesLightbox);
  }
  const rulesLightboxPanel = rulesLightbox?.querySelector(".rules-lightbox-panel");

  const closeRulesLightbox = () => {
    if (!rulesLightbox) return;
    rulesLightbox.classList.remove("is-open");
    rulesLightbox.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";
  };

  const openRulesLightbox = () => {
    if (!rulesLightbox) return;
    rulesLightbox.classList.add("is-open");
    rulesLightbox.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
    rulesLightboxPanel?.scrollTo(0, 0);
  };

  document.addEventListener("click", (event) => {
    const openRules = event.target.closest("[data-rules-lightbox-open]");
    if (openRules) {
      event.preventDefault();
      event.stopPropagation();
      openRulesLightbox();
      return;
    }
    if (rulesLightbox?.classList.contains("is-open") && !event.target.closest(".rules-lightbox-panel")) {
      closeRulesLightbox();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") closeRulesLightbox();
  });

  const memberPreviewLightbox = document.querySelector("[data-member-preview-lightbox]");
  if (memberPreviewLightbox && memberPreviewLightbox.parentElement !== document.body) {
    document.body.appendChild(memberPreviewLightbox);
  }
  const memberPreviewBody = memberPreviewLightbox?.querySelector("[data-member-preview-body]");
  const memberPreviewPanel = memberPreviewLightbox?.querySelector(".member-preview-panel");
  const memberPreviewNonce = memberPreviewLightbox?.dataset.memberPreviewNonce || "";
  let memberPreviewLoading = false;

  const closeMemberPreview = () => {
    if (!memberPreviewLightbox) return;
    memberPreviewLightbox.classList.remove("is-open");
    memberPreviewLightbox.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";
  };

  const openMemberPreview = async (memberId) => {
    if (!memberPreviewLightbox || !memberPreviewBody || memberPreviewLoading) return;
    const id = parseInt(String(memberId || "0"), 10);
    if (!id) return;
    memberPreviewLoading = true;
    memberPreviewBody.innerHTML = '<p class="meta">در حال بارگذاری…</p>';
    memberPreviewLightbox.classList.add("is-open");
    memberPreviewLightbox.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
    try {
      const res = await fetch(`member-preview.php?ajax=1&id=${encodeURIComponent(String(id))}`, {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
      });
      const raw = await res.text();
      let data = null;
      try {
        data = JSON.parse(raw);
      } catch (_parseErr) {
        data = null;
      }
      if (!res.ok || !data?.ok || !data.html) {
        const msg =
          (data && data.error) ||
          (res.status === 403 ? "دسترسی به این پروفایل مجاز نیست." : "بارگذاری پروفایل ناموفق بود.");
        memberPreviewBody.innerHTML = `<p class="empty-state">${msg}</p>`;
      } else {
        memberPreviewBody.innerHTML = data.html;
        const previewForm = memberPreviewBody.querySelector("[data-preview-add-project]");
        if (previewForm) fillPreviewRoles(previewForm);
      }
    } catch (_err) {
      memberPreviewBody.innerHTML = '<p class="empty-state">خطا در بارگذاری پروفایل.</p>';
    } finally {
      memberPreviewLoading = false;
      memberPreviewPanel?.scrollTo(0, 0);
    }
  };

  const postMemberPreviewAction = async (memberId, action, extra = {}) => {
    const body = new FormData();
    body.append("_wpnonce", memberPreviewNonce);
    body.append("member_id", String(memberId));
    body.append("action", action);
    Object.keys(extra).forEach((key) => {
      if (extra[key] !== undefined && extra[key] !== null) {
        body.append(key, String(extra[key]));
      }
    });
    const res = await fetch("member-preview.php", {
      method: "POST",
      credentials: "same-origin",
      body,
      headers: { Accept: "application/json" },
    });
    return res.json();
  };

  const fillPreviewRoles = (form) => {
    if (!form) return;
    const projectSelect = form.querySelector("[data-preview-project-select]");
    const roleSelect = form.querySelector("[data-preview-role-select]");
    if (!projectSelect || !roleSelect) return;
    let rolesMap = {};
    try {
      rolesMap = JSON.parse(form.getAttribute("data-preview-roles") || "{}") || {};
    } catch (_err) {
      rolesMap = {};
    }
    const projectId = String(projectSelect.value || "");
    const roles = Array.isArray(rolesMap[projectId]) ? rolesMap[projectId] : [];
    roleSelect.innerHTML = "";
    const placeholder = document.createElement("option");
    placeholder.value = "";
    placeholder.textContent = roles.length ? "انتخاب نقش" : "نقشی نیست؛ پایین نقش جدید بنویسید";
    roleSelect.appendChild(placeholder);
    roles.forEach((role) => {
      const opt = document.createElement("option");
      opt.value = String(role.id || "");
      opt.textContent = String(role.title || "");
      roleSelect.appendChild(opt);
    });
  };

  document.addEventListener("change", (event) => {
    const projectSelect = event.target.closest("[data-preview-project-select]");
    if (!projectSelect || !memberPreviewBody?.contains(projectSelect)) return;
    fillPreviewRoles(projectSelect.closest("[data-preview-add-project]"));
  });

  document.addEventListener("submit", async (event) => {
    const form = event.target.closest("[data-preview-add-project]");
    if (!form || !memberPreviewBody?.contains(form)) return;
    event.preventDefault();
    event.stopPropagation();
    const memberId = form.getAttribute("data-member-id");
    if (!memberId) return;
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.disabled = true;
    const extra = {
      project_id: form.querySelector('[name="project_id"]')?.value || "",
      role_id: form.querySelector('[name="role_id"]')?.value || "",
      role_title: form.querySelector('[name="role_title"]')?.value || "",
      project_title: form.querySelector('[name="project_title"]')?.value || "",
      project_type: form.querySelector('[name="project_type"]')?.value || "",
    };
    try {
      const data = await postMemberPreviewAction(memberId, "add_to_project", extra);
      if (!data?.ok) {
        window.alert(data?.error || "افزودن به پروژه ناموفق بود.");
        return;
      }
      window.alert(data.message || "به پروژه اضافه شد.");
      await openMemberPreview(memberId);
    } catch (_err) {
      window.alert("خطا در افزودن به پروژه.");
    } finally {
      if (submitBtn) submitBtn.disabled = false;
    }
  });

  document.addEventListener("click", async (event) => {
    const actionBtn = event.target.closest("[data-member-preview-action]");
    if (actionBtn && memberPreviewBody?.contains(actionBtn)) {
      event.preventDefault();
      event.stopPropagation();
      const memberId = actionBtn.getAttribute("data-member-id");
      const action = actionBtn.getAttribute("data-member-preview-action");
      if (!memberId || !action || actionBtn.disabled) return;
      if (action === "invite_sms" && !window.confirm("پیامک دعوت به همکاری با متن ازپیش‌تعریف‌شده به موبایل ثبت‌شده این کاربر ارسال شود؟")) {
        return;
      }
      actionBtn.disabled = true;
      try {
        const data = await postMemberPreviewAction(memberId, action);
        if (!data?.ok) {
          window.alert(data?.error || "عملیات ناموفق بود.");
          return;
        }
        if (data.redirect) {
          window.location.href = data.redirect;
          return;
        }
        if (action === "favorite" || action === "follow") {
          await openMemberPreview(memberId);
        } else if (data.message) {
          window.alert(data.message);
        }
      } catch (_err) {
        window.alert("خطا در انجام عملیات.");
      } finally {
        actionBtn.disabled = false;
      }
      return;
    }

    if (event.target.closest("[data-follow-toggle]")) {
      return;
    }

    const openPreview = event.target.closest("[data-member-preview]");
    if (openPreview) {
      if (openPreview.closest("a[href]") && !openPreview.matches("[data-member-preview]")) {
        return;
      }
      event.preventDefault();
      event.stopPropagation();
      await openMemberPreview(openPreview.getAttribute("data-member-preview"));
      return;
    }

    if (event.target.closest("[data-member-preview-close]")) {
      event.preventDefault();
      closeMemberPreview();
      return;
    }

    if (memberPreviewLightbox?.classList.contains("is-open") && !event.target.closest(".member-preview-panel")) {
      closeMemberPreview();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") closeMemberPreview();
  });

  const adminMemberPanel = document.querySelector("[data-admin-member-panel]");
  if (adminMemberPanel) {
    window.requestAnimationFrame(() => {
      adminMemberPanel.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  }

  const deskProjectSelect = document.querySelector("[data-desk-project-select]");
  const deskRoleSelect = document.querySelector("[data-desk-role-select]");
  const deskRolesMap = window.CASTING_DESK_ROLES || {};
  const fillDeskRoles = () => {
    if (!deskProjectSelect || !deskRoleSelect) return;
    const projectId = String(deskProjectSelect.value || "");
    const roles = Array.isArray(deskRolesMap[projectId]) ? deskRolesMap[projectId] : [];
    deskRoleSelect.innerHTML = "";
    const placeholder = document.createElement("option");
    placeholder.value = "";
    placeholder.textContent = roles.length ? "انتخاب نقش" : "نقشی برای این پروژه نیست";
    deskRoleSelect.appendChild(placeholder);
    roles.forEach((role) => {
      const opt = document.createElement("option");
      opt.value = String(role.id || "");
      opt.textContent = String(role.title || "");
      deskRoleSelect.appendChild(opt);
    });
  };
  if (deskProjectSelect && deskRoleSelect) {
    deskProjectSelect.addEventListener("change", fillDeskRoles);
    fillDeskRoles();
  }

  const assignmentForm = document.querySelector("[data-assignment-form]");
  const assignmentTypeSelect = assignmentForm?.querySelector("[data-assignment-type-select]");
  if (assignmentForm && assignmentTypeSelect) {
    const hintRead = assignmentForm.querySelector("[data-assignment-hint-read]");
    const hintScene = assignmentForm.querySelector("[data-assignment-hint-scene]");
    const extras = assignmentForm.querySelector("[data-assignment-extras]");
    const extraText = assignmentForm.querySelector("[data-assignment-extra-text]");
    const extraAudio = assignmentForm.querySelector("[data-assignment-extra-audio]");

    const syncAssignmentForm = () => {
      const type = String(assignmentTypeSelect.value || "");
      const isRead = type === "read_text";
      const isScene = type === "perform_scene";
      const showExtras = isRead || isScene;

      if (hintRead) hintRead.hidden = !isRead;
      if (hintScene) hintScene.hidden = !isScene;
      if (extras) extras.hidden = !showExtras;
      if (extraText) extraText.hidden = !showExtras;
      if (extraAudio) extraAudio.hidden = !isScene;
    };

    assignmentTypeSelect.addEventListener("change", syncAssignmentForm);
    syncAssignmentForm();
  }

  const siteNavToggle = document.querySelector("[data-site-nav-toggle]");
  const siteNav = document.querySelector("[data-site-nav]");
  if (siteNavToggle && siteNav) {
    const siteNavQuery = window.matchMedia("(max-width: 720px)");
    const setSiteNavOpen = (open) => {
      if (open && !siteNavQuery.matches) {
        return;
      }
      document.body.classList.toggle("site-nav-open", open);
      siteNavToggle.setAttribute("aria-expanded", open ? "true" : "false");
      siteNavToggle.setAttribute("aria-label", open ? "بستن منو" : "باز کردن منو");
    };

    siteNavToggle.addEventListener("click", () => {
      if (!siteNavQuery.matches) {
        return;
      }
      const open = siteNavToggle.getAttribute("aria-expanded") !== "true";
      setSiteNavOpen(open);
    });

    siteNav.querySelectorAll("a").forEach((link) => {
      link.addEventListener("click", () => setSiteNavOpen(false));
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && siteNavToggle.getAttribute("aria-expanded") === "true") {
        setSiteNavOpen(false);
      }
    });

    const onSiteNavViewportChange = () => {
      if (!siteNavQuery.matches) {
        setSiteNavOpen(false);
      }
    };
    if (typeof siteNavQuery.addEventListener === "function") {
      siteNavQuery.addEventListener("change", onSiteNavViewportChange);
    } else if (typeof siteNavQuery.addListener === "function") {
      siteNavQuery.addListener(onSiteNavViewportChange);
    }
  }

  const panelToggle = document.querySelector("[data-panel-menu-toggle]");
  const panelDrawer = document.getElementById("panel-drawer");
  const panelBackdrop = document.querySelector(".panel-drawer-backdrop");
  if (panelToggle && panelDrawer) {
    const mobileMenuQuery = window.matchMedia("(max-width: 960px)");
    const setPanelMenuOpen = (open) => {
      if (open && !mobileMenuQuery.matches) {
        return;
      }
      document.body.classList.toggle("panel-menu-open", open);
      panelToggle.setAttribute("aria-expanded", open ? "true" : "false");
      panelToggle.setAttribute("aria-label", open ? "بستن منوی پنل" : "باز کردن منوی پنل");
      if (panelBackdrop) {
        if (open) {
          panelBackdrop.removeAttribute("hidden");
        } else {
          panelBackdrop.setAttribute("hidden", "");
        }
      }
    };

    panelToggle.addEventListener("click", () => {
      if (!mobileMenuQuery.matches) {
        return;
      }
      const open = panelToggle.getAttribute("aria-expanded") !== "true";
      setPanelMenuOpen(open);
    });

    document.querySelectorAll("[data-panel-drawer-close]").forEach((el) => {
      el.addEventListener("click", () => setPanelMenuOpen(false));
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && panelToggle.getAttribute("aria-expanded") === "true") {
        setPanelMenuOpen(false);
      }
    });

    const onViewportChange = () => {
      if (!mobileMenuQuery.matches) {
        setPanelMenuOpen(false);
      }
    };
    if (typeof mobileMenuQuery.addEventListener === "function") {
      mobileMenuQuery.addEventListener("change", onViewportChange);
    } else if (typeof mobileMenuQuery.addListener === "function") {
      mobileMenuQuery.addListener(onViewportChange);
    }
  }

  const promoSliders = Array.from(document.querySelectorAll("[data-promo-slider]"));
  promoSliders.forEach((promoSlider) => {
    const slides = Array.from(promoSlider.querySelectorAll(".panel-promo-slide"));
    const dots = Array.from(promoSlider.querySelectorAll("[data-promo-dot]"));
    let index = Math.max(0, slides.findIndex((slide) => slide.classList.contains("is-active")));
    if (index < 0) index = 0;
    let timer = null;

    const showSlide = (next) => {
      if (!slides.length) return;
      index = ((next % slides.length) + slides.length) % slides.length;
      slides.forEach((slide, i) => {
        slide.classList.toggle("is-active", i === index);
      });
      dots.forEach((dot, i) => {
        const active = i === index;
        dot.classList.toggle("is-active", active);
        dot.setAttribute("aria-selected", active ? "true" : "false");
      });
    };

    const startTimer = () => {
      window.clearInterval(timer);
      if (slides.length < 2) return;
      timer = window.setInterval(() => showSlide(index + 1), 5000);
    };

    dots.forEach((dot) => {
      dot.addEventListener("click", () => {
        const next = Number(dot.getAttribute("data-promo-dot") || "0");
        showSlide(next);
        startTimer();
      });
    });

    promoSlider.addEventListener("mouseenter", () => window.clearInterval(timer));
    promoSlider.addEventListener("mouseleave", startTimer);
    startTimer();
  });

  document.querySelectorAll("[data-show-more]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const targetId = btn.getAttribute("data-show-more") || "";
      const more = targetId !== "" ? document.getElementById(targetId) : null;
      if (!more) return;
      more.hidden = false;
      const foot = btn.closest(".panel-ads-foot");
      if (foot) foot.hidden = true;
    });
  });

  // جدول دسترسی پیام — روشن/خاموش فوری
  const msgAccessPage = document.querySelector("[data-msg-access-page]");
  if (msgAccessPage) {
    const nonce = msgAccessPage.getAttribute("data-toggle-nonce") || "";
    const errBox = msgAccessPage.querySelector(".msg-access-ajax-error");
    const okBox = msgAccessPage.querySelector(".msg-access-ajax-ok");

    const setToggleUi = (btn, on, field) => {
      btn.classList.toggle("is-on", !!on);
      btn.classList.toggle("is-off", !on);
      btn.setAttribute("aria-pressed", on ? "true" : "false");
      const label = btn.querySelector(".msg-toggle-label");
      if (label) {
        if (field === "require_project") {
          label.textContent = on ? "فعال" : "غیرفعال";
        } else {
          label.textContent = on ? "روشن" : "خاموش";
        }
      }
    };

    const flash = (ok, text) => {
      if (errBox) {
        errBox.hidden = !!ok;
        errBox.textContent = ok ? "" : text;
      }
      if (okBox) {
        okBox.hidden = !ok;
        okBox.textContent = ok ? text : "";
        if (ok) {
          window.setTimeout(() => {
            okBox.hidden = true;
          }, 2200);
        }
      }
    };

    msgAccessPage.querySelectorAll("[data-msg-toggle]").forEach((btn) => {
      btn.addEventListener("click", async () => {
        if (btn.disabled || btn.getAttribute("aria-busy") === "true") return;
        const row = btn.closest("tr");
        if (!row) return;
        const from = row.getAttribute("data-from") || "";
        const to = row.getAttribute("data-to") || "";
        const field = btn.getAttribute("data-msg-toggle") || "enabled";
        const currentlyOn = btn.classList.contains("is-on");
        const nextOn = !currentlyOn;

        // به‌روزرسانی فوری ظاهر دکمه
        setToggleUi(btn, nextOn, field);
        if (field === "enabled") {
          const reqBtn = row.querySelector('[data-msg-toggle="require_project"]');
          if (reqBtn) {
            reqBtn.disabled = !nextOn;
            if (!nextOn) setToggleUi(reqBtn, false, "require_project");
          }
        }

        btn.setAttribute("aria-busy", "true");
        const body = new URLSearchParams();
        body.set("_wpnonce", nonce);
        body.set("from", from);
        body.set("to", to);
        body.set("field", field);
        body.set("force", nextOn ? "1" : "0");

        try {
          const res = await fetch("admin-message-access.php?ajax=1", {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
              Accept: "application/json",
            },
            body: body.toString(),
            credentials: "same-origin",
            cache: "no-store",
          });
          const raw = await res.text();
          let data = null;
          try {
            data = JSON.parse(raw);
          } catch (parseErr) {
            setToggleUi(btn, currentlyOn, field);
            flash(false, "پاسخ سرور نامعتبر بود. صفحه را تازه کنید.");
            return;
          }
          if (!data || !data.ok) {
            setToggleUi(btn, currentlyOn, field);
            if (field === "enabled") {
              const reqBtn = row.querySelector('[data-msg-toggle="require_project"]');
              if (reqBtn) reqBtn.disabled = !currentlyOn;
            }
            flash(false, (data && data.error) || "ذخیره ناموفق بود.");
            return;
          }
          if (field === "enabled") {
            const enabled = data.enabled === true || data.enabled === 1 || data.enabled === "1";
            setToggleUi(btn, enabled, "enabled");
            const reqBtn = row.querySelector('[data-msg-toggle="require_project"]');
            if (reqBtn) {
              reqBtn.disabled = !enabled;
              if (!enabled) setToggleUi(reqBtn, false, "require_project");
              else if (typeof data.require_project !== "undefined") {
                setToggleUi(reqBtn, !!data.require_project, "require_project");
              }
            }
            flash(true, data.message || (enabled ? "دسترسی روشن شد." : "دسترسی خاموش شد."));
          } else {
            const reqOn = data.require_project === true || data.require_project === 1 || data.require_project === "1";
            setToggleUi(btn, reqOn, "require_project");
            flash(true, data.message || (reqOn ? "محدودیت پروژه فعال شد." : "محدودیت پروژه برداشته شد."));
          }
        } catch (e) {
          setToggleUi(btn, currentlyOn, field);
          flash(false, "خطا در ارتباط با سرور.");
        } finally {
          btn.removeAttribute("aria-busy");
        }
      });
    });
  }
  // Follow / unfollow (panel cards, profile, search cards)
  document.addEventListener("click", async (event) => {
    const btn = event.target.closest("[data-follow-toggle]");
    if (!btn) return;
    event.preventDefault();
    event.stopPropagation();
    if (btn.disabled || btn.getAttribute("data-follow-locked") === "1") {
      window.alert("صفحه رسمی مدیران — دنبال کردن الزامی است و قابل لغو نیست.");
      return;
    }
    const cfg = window.CASTING_FOLLOW || {};
    const userId = btn.getAttribute("data-follow-toggle");
    if (!userId || !cfg.url || !cfg.nonce) return;
    btn.disabled = true;
    try {
      const body = new URLSearchParams();
      body.set("_wpnonce", cfg.nonce);
      body.set("user_id", userId);
      const res = await fetch(cfg.url, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: body.toString(),
        credentials: "same-origin",
      });
      const data = await res.json();
      if (!data?.ok) {
        window.alert(data?.error || "عملیات ناموفق بود.");
        return;
      }
      const following = !!data.following;
      const locked = !!data.locked;
      document.querySelectorAll(`[data-follow-toggle="${userId}"]`).forEach((el) => {
        el.setAttribute("data-following", following ? "1" : "0");
        el.setAttribute("data-follow-locked", locked ? "1" : "0");
        el.setAttribute("aria-pressed", following ? "true" : "false");
        el.textContent = locked ? "دنبال‌شده" : following ? "دنبال نکردن" : "دنبال کردن";
        el.classList.toggle("btn-primary", !following && !locked);
        el.classList.toggle("is-following", following);
        el.classList.toggle("is-follow-locked", locked);
        el.classList.toggle("btn-ghost", following || locked);
        el.disabled = locked;
        if (locked) {
          el.setAttribute("title", "صفحه رسمی مدیران — دنبال کردن الزامی است");
        } else {
          el.removeAttribute("title");
        }
      });
    } catch (_err) {
      window.alert("خطا در انجام عملیات.");
    } finally {
      if (btn.getAttribute("data-follow-locked") !== "1") {
        btn.disabled = false;
      }
    }
  });

  // Media like / comment / view
  const mediaEngageCfg = () => window.CASTING_MEDIA_ENGAGE || {};
  const viewedMediaIds = new Set();

  const updateViewCount = (mediaId, count) => {
    const id = String(mediaId || "");
    document.querySelectorAll("[data-media-engage]").forEach((wrap) => {
      if (wrap.getAttribute("data-media-engage") !== id) return;
      const el = wrap.querySelector("[data-view-count]");
      if (el) el.textContent = String(count);
    });
  };

  const recordMediaView = (mediaId) => {
    const id = String(mediaId || "");
    if (!id || viewedMediaIds.has(id)) return;
    if (!window.CASTING_SESSION?.active) return;
    const cfg = mediaEngageCfg();
    if (!cfg.url || !cfg.nonce) return;
    viewedMediaIds.add(id);
    const body = new URLSearchParams();
    body.set("_wpnonce", cfg.nonce);
    body.set("engage_action", "view");
    body.set("media_id", id);
    fetch(cfg.url, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
      body: body.toString(),
      credentials: "same-origin",
      keepalive: true,
    })
      .then((res) => res.json())
      .then((data) => {
        if (data?.ok && data.count != null) {
          updateViewCount(id, data.count);
        }
      })
      .catch(() => {});
  };

  const mediaIdFromEngageRoot = (root) => {
    if (!root) return "";
    const wrap = root.matches?.("[data-media-engage]")
      ? root
      : root.querySelector?.("[data-media-engage]");
    return wrap?.getAttribute("data-media-engage") || "";
  };

  if ("IntersectionObserver" in window && window.CASTING_SESSION?.active) {
    const pendingViewTimers = new Map();
    const viewObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        const id = mediaIdFromEngageRoot(entry.target);
        if (!id || viewedMediaIds.has(id)) {
          if (id) viewObserver.unobserve(entry.target);
          return;
        }
        if (entry.isIntersecting && entry.intersectionRatio >= 0.4) {
          if (pendingViewTimers.has(id)) return;
          pendingViewTimers.set(id, window.setTimeout(() => {
            pendingViewTimers.delete(id);
            recordMediaView(id);
            viewObserver.unobserve(entry.target);
          }, 650));
        } else {
          const timer = pendingViewTimers.get(id);
          if (timer) {
            window.clearTimeout(timer);
            pendingViewTimers.delete(id);
          }
        }
      });
    }, { threshold: [0.4] });

    document.querySelectorAll("[data-media-engage]").forEach((wrap) => {
      const root = wrap.closest(".home-feed-post, .ig-profile-cell, .profile-media-item") || wrap;
      viewObserver.observe(root);
    });
  }

  const buildCommentLi = (comment) => {
    const li = document.createElement("li");
    const parentId = Number(comment?.parent_id || 0);
    li.className = parentId > 0 ? "media-comment is-reply" : "media-comment";
    li.setAttribute("data-comment-id", String(comment?.id || ""));

    const main = document.createElement("div");
    main.className = "media-comment-main";

    const text = document.createElement("p");
    text.className = "media-comment-text";
    const strong = document.createElement("strong");
    strong.textContent = comment?.name || "کاربر";
    const span = document.createElement("span");
    span.textContent = comment?.body || "";
    text.appendChild(strong);
    text.appendChild(document.createTextNode(" "));
    text.appendChild(span);

    const actions = document.createElement("div");
    actions.className = "media-comment-actions";
    const likeBtn = document.createElement("button");
    likeBtn.type = "button";
    likeBtn.className = "media-comment-like";
    likeBtn.setAttribute("data-comment-like", String(comment?.id || ""));
    likeBtn.setAttribute("aria-pressed", "false");
    likeBtn.title = "پسند کامنت";
    const likeIcon = document.createElement("span");
    likeIcon.setAttribute("aria-hidden", "true");
    likeIcon.textContent = "♡";
    const likeCount = document.createElement("span");
    likeCount.setAttribute("data-comment-like-count", "");
    likeCount.textContent = String(comment?.likes || 0);
    likeBtn.appendChild(likeIcon);
    likeBtn.appendChild(likeCount);
    actions.appendChild(likeBtn);

    if (window.CASTING_SESSION?.active) {
      const replyBtn = document.createElement("button");
      replyBtn.type = "button";
      replyBtn.className = "media-comment-reply";
      replyBtn.setAttribute("data-comment-reply", String(comment?.id || ""));
      replyBtn.setAttribute("data-reply-name", comment?.name || "کاربر");
      replyBtn.textContent = "پاسخ";
      actions.appendChild(replyBtn);
    }

    main.appendChild(text);
    main.appendChild(actions);
    li.appendChild(main);

    if (parentId <= 0) {
      const replies = document.createElement("ul");
      replies.className = "media-comment-replies";
      li.appendChild(replies);
    }
    return li;
  };

  const clearCommentReply = (form) => {
    if (!form) return;
    const hidden = form.querySelector('input[name="parent_id"]');
    const input = form.querySelector('input[name="body"]');
    const hint = form.querySelector("[data-reply-hint]");
    const hintText = form.querySelector("[data-reply-hint-text]");
    if (hidden) hidden.value = "0";
    if (input) input.placeholder = "کامنت بنویسید…";
    if (hint) hint.hidden = true;
    if (hintText) hintText.textContent = "";
  };

  const setCommentReply = (form, parentId, name) => {
    if (!form) return;
    const hidden = form.querySelector('input[name="parent_id"]');
    const input = form.querySelector('input[name="body"]');
    const hint = form.querySelector("[data-reply-hint]");
    const hintText = form.querySelector("[data-reply-hint-text]");
    if (hidden) hidden.value = String(parentId || 0);
    if (parentId && name) {
      if (input) {
        input.placeholder = `پاسخ به ${name}…`;
        input.focus();
      }
      if (hint) hint.hidden = false;
      if (hintText) hintText.textContent = `پاسخ به ${name}`;
    } else {
      clearCommentReply(form);
    }
  };

  const refreshCommentPreview = (wrap) => {
    if (!wrap) return;
    const preview = wrap.querySelector("[data-media-comments]");
    const full = wrap.querySelector("[data-media-comments-full]");
    if (!preview || !full) return;
    const items = Array.from(full.querySelectorAll(":scope > li"));
    preview.innerHTML = "";
    items.slice(0, 2).forEach((li) => {
      const clone = li.cloneNode(true);
      clone.querySelectorAll(".media-comment-replies").forEach((el) => el.remove());
      preview.appendChild(clone);
    });
    const countEl = wrap.querySelector("[data-comment-count]");
    const count = Number(countEl?.textContent || items.length || 0);
    const needMore = count > 2 || items.length > 2;
    let moreBtn = wrap.querySelector(":scope > [data-post-expand]");
    if (needMore && !moreBtn) {
      moreBtn = document.createElement("button");
      moreBtn.type = "button";
      moreBtn.className = "link-button media-engage-more";
      moreBtn.setAttribute("data-post-expand", "");
      moreBtn.textContent = "بیشتر…";
      const form = wrap.querySelector(".media-engage-form");
      if (form) wrap.insertBefore(moreBtn, form);
      else wrap.appendChild(moreBtn);
    } else if (moreBtn) {
      moreBtn.hidden = !needMore;
    }
  };

  const expandCommentThread = (wrap) => {
    if (!wrap) return;
    const preview = wrap.querySelector("[data-media-comments]");
    const full = wrap.querySelector("[data-media-comments-full]");
    if (full && preview) preview.innerHTML = full.innerHTML;
  };

  let postLightboxSource = null;
  let postLightboxEngage = null;
  const postLightbox = document.querySelector("[data-post-lightbox]");
  if (postLightbox && postLightbox.parentElement !== document.body) {
    document.body.appendChild(postLightbox);
  }
  const postLightboxPanel = postLightbox?.querySelector(".post-lightbox-panel");
  const postLightboxBody = postLightbox?.querySelector("[data-post-lightbox-body]");

  let homeFeedZoomPost = null;
  let homeFeedZoomPlaceholder = null;
  let homeFeedZoomBackdrop = document.querySelector("[data-home-feed-zoom-backdrop]");
  if (!homeFeedZoomBackdrop) {
    homeFeedZoomBackdrop = document.createElement("div");
    homeFeedZoomBackdrop.className = "home-feed-zoom-backdrop";
    homeFeedZoomBackdrop.setAttribute("data-home-feed-zoom-backdrop", "");
    homeFeedZoomBackdrop.setAttribute("aria-hidden", "true");
    document.body.appendChild(homeFeedZoomBackdrop);
  }

  const unlockBodyScroll = () => {
    document.body.style.overflow = "";
    document.body.style.paddingInlineEnd = "";
  };

  const lockBodyScroll = () => {
    const sb = window.innerWidth - document.documentElement.clientWidth;
    document.body.style.overflow = "hidden";
    if (sb > 0) {
      document.body.style.paddingInlineEnd = `${sb}px`;
    }
  };

  const closeHomeFeedZoom = () => {
    if (!homeFeedZoomPost) return;
    const engage = homeFeedZoomPost.querySelector("[data-media-engage]");
    homeFeedZoomPost.classList.remove("is-zoomed");
    homeFeedZoomPost.style.position = "";
    homeFeedZoomPost.style.top = "";
    homeFeedZoomPost.style.left = "";
    homeFeedZoomPost.style.width = "";
    homeFeedZoomPost.style.maxWidth = "";
    homeFeedZoomPost.style.zIndex = "";
    homeFeedZoomPost.style.maxHeight = "";
    homeFeedZoomPost.style.overflow = "";
    if (homeFeedZoomPlaceholder && homeFeedZoomPlaceholder.parentNode) {
      homeFeedZoomPlaceholder.parentNode.insertBefore(homeFeedZoomPost, homeFeedZoomPlaceholder);
      homeFeedZoomPlaceholder.remove();
    }
    homeFeedZoomPlaceholder = null;
    homeFeedZoomPost = null;
    homeFeedZoomBackdrop.classList.remove("is-open");
    homeFeedZoomBackdrop.setAttribute("aria-hidden", "true");
    unlockBodyScroll();
    refreshCommentPreview(engage);
  };

  const openHomeFeedZoom = (post) => {
    if (!post) return;
    closeHomeFeedZoom();
    if (postLightbox?.classList.contains("is-open")) {
      closePostLightbox();
    }

    const rect = post.getBoundingClientRect();
    const placeholder = document.createElement("div");
    placeholder.className = "home-feed-zoom-placeholder";
    placeholder.style.width = `${rect.width}px`;
    placeholder.style.height = `${rect.height}px`;
    post.parentNode.insertBefore(placeholder, post);

    const targetW = Math.min(window.innerWidth - 24, Math.max(rect.width * 1.55, 320));
    let left = rect.left + rect.width / 2 - targetW / 2;
    left = Math.max(12, Math.min(left, window.innerWidth - targetW - 12));
    let top = rect.top;
    top = Math.max(12, Math.min(top, window.innerHeight - 120));

    homeFeedZoomPlaceholder = placeholder;
    homeFeedZoomPost = post;
    post.classList.add("is-zoomed");
    recordMediaView(mediaIdFromEngageRoot(post));
    expandCommentThread(post.querySelector("[data-media-engage]"));
    post.style.position = "fixed";
    post.style.top = `${top}px`;
    post.style.left = `${left}px`;
    post.style.width = `${targetW}px`;
    post.style.maxWidth = "calc(100vw - 24px)";
    post.style.zIndex = "10001";
    document.body.appendChild(post);

    homeFeedZoomBackdrop.classList.add("is-open");
    homeFeedZoomBackdrop.setAttribute("aria-hidden", "false");
    lockBodyScroll();

    requestAnimationFrame(() => {
      if (!homeFeedZoomPost) return;
      const ph = homeFeedZoomPost.getBoundingClientRect().height;
      if (top + ph > window.innerHeight - 12) {
        top = Math.max(12, window.innerHeight - ph - 12);
        homeFeedZoomPost.style.top = `${top}px`;
      }
    });
  };

  const closePostLightbox = () => {
    if (!postLightbox || !postLightbox.classList.contains("is-open")) return;
    if (postLightboxEngage && postLightboxSource) {
      postLightboxSource.appendChild(postLightboxEngage);
      refreshCommentPreview(postLightboxEngage);
    }
    postLightboxEngage = null;
    postLightboxSource = null;
    if (postLightboxBody) postLightboxBody.innerHTML = "";
    postLightbox.classList.remove("is-open");
    postLightbox.setAttribute("aria-hidden", "true");
    unlockBodyScroll();
  };

  const openPostLightbox = (trigger) => {
    if (!postLightbox || !postLightboxBody) return;
    const root = trigger.closest(".ig-profile-cell, .profile-media-item, .home-feed-post");
    if (!root) return;

    // خانه: بزرگ‌نمایی در همان موقعیت پست
    if (root.classList.contains("home-feed-post")) {
      closePostLightbox();
      openHomeFeedZoom(root);
      return;
    }

    closeHomeFeedZoom();
    closePostLightbox();

    const mediaHost =
      root.querySelector(".home-feed-post-media .media-protect") ||
      root.querySelector(".profile-media-open .media-protect") ||
      root.querySelector(".media-protect") ||
      root.querySelector(".home-feed-post-media") ||
      root.querySelector(".profile-media-open") ||
      root.querySelector(":scope > a") ||
      root.querySelector("img, video");
    const captionEl =
      root.querySelector(".ig-profile-cell-meta > p") ||
      root.querySelector(".home-feed-caption") ||
      root.querySelector(".profile-media-caption-text") ||
      root.querySelector(".profile-media-caption p");
    const engage = root.querySelector("[data-media-engage]");

    postLightboxBody.innerHTML = "";
    const mediaWrap = document.createElement("div");
    mediaWrap.className = "post-lightbox-media";
    if (mediaHost) {
      if (mediaHost.classList?.contains("media-protect") || mediaHost.querySelector?.(".media-protect")) {
        const protect = mediaHost.classList.contains("media-protect")
          ? mediaHost
          : mediaHost.querySelector(".media-protect");
        mediaWrap.appendChild(protect.cloneNode(true));
      } else {
        const cloneSource = mediaHost.matches("img, video") ? mediaHost : mediaHost.querySelector("img, video");
        if (cloneSource) {
          const protect = document.createElement("div");
          protect.className = "media-protect";
          protect.setAttribute("data-media-protect", "");
          if (cloneSource.tagName === "VIDEO") {
            protect.classList.add("media-protect--video");
            protect.setAttribute("data-video-protect", "");
            const clone = document.createElement("video");
            clone.className = "media-protect-source";
            const src = cloneSource.currentSrc || cloneSource.getAttribute("src") || "";
            if (src) clone.src = src;
            const poster = cloneSource.getAttribute("poster") || "";
            if (poster) clone.setAttribute("poster", poster);
            clone.setAttribute("playsinline", "");
            clone.setAttribute("webkit-playsinline", "");
            clone.setAttribute("preload", "metadata");
            clone.setAttribute("controlslist", "nodownload noplaybackrate noremoteplayback");
            clone.setAttribute("disablepictureinpicture", "");
            clone.draggable = false;
            clone.oncontextmenu = () => false;
            protect.appendChild(clone);

            const canvas = document.createElement("canvas");
            canvas.className = "media-protect-canvas";
            canvas.setAttribute("aria-hidden", "true");
            protect.appendChild(canvas);

            const wm = document.createElement("div");
            wm.className = "media-watermark";
            wm.setAttribute("aria-hidden", "true");
            const label = (window.CASTING_MEDIA_PROTECT && window.CASTING_MEDIA_PROTECT.watermark) || "";
            for (let i = 0; i < 3; i += 1) {
              const span = document.createElement("span");
              span.textContent = label;
              wm.appendChild(span);
            }
            protect.appendChild(wm);

            const playBtn = document.createElement("button");
            playBtn.type = "button";
            playBtn.className = "media-protect-play";
            playBtn.setAttribute("data-video-play", "");
            playBtn.setAttribute("aria-label", "پخش ویدیو");
            playBtn.textContent = "▶";
            protect.appendChild(playBtn);

            const controls = document.createElement("div");
            controls.className = "media-protect-controls";
            controls.hidden = true;
            const toggleBtn = document.createElement("button");
            toggleBtn.type = "button";
            toggleBtn.className = "media-protect-toggle";
            toggleBtn.setAttribute("data-video-toggle", "");
            toggleBtn.setAttribute("aria-label", "توقف/پخش");
            toggleBtn.textContent = "❚❚";
            const seek = document.createElement("input");
            seek.className = "media-protect-seek";
            seek.type = "range";
            seek.min = "0";
            seek.max = "1000";
            seek.value = "0";
            seek.step = "1";
            seek.setAttribute("data-video-seek", "");
            seek.setAttribute("aria-label", "زمان");
            controls.appendChild(toggleBtn);
            controls.appendChild(seek);
            protect.appendChild(controls);
          } else {
            const clone = cloneSource.cloneNode(true);
            clone.draggable = false;
            protect.appendChild(clone);
            const wm = document.createElement("div");
            wm.className = "media-watermark";
            wm.setAttribute("aria-hidden", "true");
            const label = (window.CASTING_MEDIA_PROTECT && window.CASTING_MEDIA_PROTECT.watermark) || "";
            for (let i = 0; i < 3; i += 1) {
              const span = document.createElement("span");
              span.textContent = label;
              wm.appendChild(span);
            }
            protect.appendChild(wm);
          }
          mediaWrap.appendChild(protect);
        }
      }
    }
    postLightboxBody.appendChild(mediaWrap);

    if (captionEl && (captionEl.textContent || "").trim()) {
      const caption = document.createElement("p");
      caption.className = "post-lightbox-caption";
      caption.innerHTML = captionEl.innerHTML;
      postLightboxBody.appendChild(caption);
    }

    if (engage) {
      postLightboxSource = engage.parentElement;
      postLightboxEngage = engage;
      const full = engage.querySelector("[data-media-comments-full]");
      const preview = engage.querySelector("[data-media-comments]");
      if (full && preview) {
        preview.innerHTML = full.innerHTML;
      }
      postLightboxBody.appendChild(engage);
    }

    postLightbox.classList.add("is-open");
    postLightbox.setAttribute("aria-hidden", "false");
    lockBodyScroll();
    postLightboxPanel?.scrollTo(0, 0);
    recordMediaView(mediaIdFromEngageRoot(root));
  };

  document.addEventListener("click", (event) => {
    if (event.target.closest(".ig-profile-cell-delete, .ig-profile-pending-delete")) {
      return;
    }

    if (event.target.closest("[data-home-feed-zoom-backdrop]")) {
      closeHomeFeedZoom();
      return;
    }

    // کلیک روی مدیای فید خانه → بزرگ‌نمایی در همان جا (به‌جز کنترل‌های ویدیو)
    const feedMedia = event.target.closest(".home-feed-post-media");
    if (feedMedia && !event.target.closest(
      "[data-video-play], [data-video-toggle], [data-video-seek], .media-protect-controls, .media-protect-play"
    )) {
      const post = feedMedia.closest(".home-feed-post");
      if (post && !post.classList.contains("is-zoomed")) {
        event.preventDefault();
        openHomeFeedZoom(post);
        return;
      }
    }

    const expand = event.target.closest("[data-post-expand]");
    if (expand) {
      event.preventDefault();
      openPostLightbox(expand);
      return;
    }
    if (event.target.closest("[data-post-lightbox-close]")) {
      event.preventDefault();
      closePostLightbox();
      return;
    }
    if (postLightbox?.classList.contains("is-open") && !event.target.closest(".post-lightbox-panel")) {
      closePostLightbox();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeHomeFeedZoom();
      closePostLightbox();
    }
  });

  document.addEventListener("click", async (event) => {
    const commentLikeBtn = event.target.closest("[data-comment-like]");
    if (commentLikeBtn && !commentLikeBtn.disabled) {
      event.preventDefault();
      const cfg = mediaEngageCfg();
      const commentId = commentLikeBtn.getAttribute("data-comment-like");
      const wrap = commentLikeBtn.closest("[data-media-engage]");
      const mediaId = wrap?.getAttribute("data-media-engage") || "";
      if (!commentId || !cfg.url || !cfg.nonce) return;
      commentLikeBtn.disabled = true;
      try {
        const body = new URLSearchParams();
        body.set("_wpnonce", cfg.nonce);
        body.set("engage_action", "comment_like");
        body.set("media_id", mediaId);
        body.set("comment_id", commentId);
        const res = await fetch(cfg.url, {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
          body: body.toString(),
          credentials: "same-origin",
        });
        const data = await res.json();
        if (!data?.ok) {
          window.alert(data?.error || "لایک کامنت ثبت نشد.");
          return;
        }
        const liked = !!data.liked;
        document.querySelectorAll(`[data-comment-like="${commentId}"]`).forEach((btn) => {
          btn.classList.toggle("is-liked", liked);
          btn.setAttribute("aria-pressed", liked ? "true" : "false");
          const icon = btn.querySelector("span[aria-hidden]");
          if (icon) icon.textContent = liked ? "♥" : "♡";
          const countEl = btn.querySelector("[data-comment-like-count]");
          if (countEl) countEl.textContent = String(data.count ?? 0);
        });
      } catch (_err) {
        window.alert("خطا در ارتباط با سرور.");
      } finally {
        document.querySelectorAll(`[data-comment-like="${commentId}"]`).forEach((btn) => {
          btn.disabled = false;
        });
      }
      return;
    }

    const replyBtn = event.target.closest("[data-comment-reply]");
    if (replyBtn) {
      event.preventDefault();
      const wrap = replyBtn.closest("[data-media-engage]");
      const form = wrap?.querySelector("[data-media-comment-form]");
      setCommentReply(
        form,
        replyBtn.getAttribute("data-comment-reply"),
        replyBtn.getAttribute("data-reply-name") || "کاربر"
      );
      return;
    }

    const cancelReply = event.target.closest("[data-reply-cancel]");
    if (cancelReply) {
      event.preventDefault();
      clearCommentReply(cancelReply.closest("[data-media-comment-form]"));
    }
  });

  document.addEventListener("click", async (event) => {
    const likeBtn = event.target.closest("[data-media-like]");
    if (!likeBtn || likeBtn.disabled) return;
    event.preventDefault();
    const cfg = mediaEngageCfg();
    const mediaId = likeBtn.getAttribute("data-media-like");
    if (!mediaId || !cfg.url || !cfg.nonce) return;
    likeBtn.disabled = true;
    try {
      const body = new URLSearchParams();
      body.set("_wpnonce", cfg.nonce);
      body.set("engage_action", "like");
      body.set("media_id", mediaId);
      const res = await fetch(cfg.url, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: body.toString(),
        credentials: "same-origin",
      });
      const data = await res.json();
      if (!data?.ok) {
        window.alert(data?.error || "لایک ثبت نشد.");
        return;
      }
      const liked = !!data.liked;
      const wrap = likeBtn.closest("[data-media-engage]");
      likeBtn.classList.toggle("is-liked", liked);
      likeBtn.setAttribute("aria-pressed", liked ? "true" : "false");
      const icon = likeBtn.querySelector("span[aria-hidden]");
      if (icon) icon.textContent = liked ? "♥" : "♡";
      const countEl = wrap?.querySelector("[data-like-count]") || likeBtn.querySelector("[data-like-count]");
      if (countEl) countEl.textContent = String(data.count ?? 0);
    } catch (_err) {
      window.alert("خطا در ارتباط با سرور.");
    } finally {
      likeBtn.disabled = false;
    }
  });

  document.addEventListener("click", async (event) => {
    const saveBtn = event.target.closest("[data-media-save]");
    if (!saveBtn || saveBtn.disabled) return;
    event.preventDefault();
    const cfg = mediaEngageCfg();
    const mediaId = saveBtn.getAttribute("data-media-save");
    if (!mediaId || !cfg.url || !cfg.nonce) return;
    saveBtn.disabled = true;
    try {
      const body = new URLSearchParams();
      body.set("_wpnonce", cfg.nonce);
      body.set("engage_action", "save");
      body.set("media_id", mediaId);
      const res = await fetch(cfg.url, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: body.toString(),
        credentials: "same-origin",
      });
      const data = await res.json();
      if (!data?.ok) {
        window.alert(data?.error || "ذخیره انجام نشد.");
        return;
      }
      const saved = !!data.saved;
      saveBtn.classList.toggle("is-saved", saved);
      saveBtn.setAttribute("aria-pressed", saved ? "true" : "false");
      saveBtn.title = saved ? "حذف از ذخیره‌شده‌ها" : "ذخیره در پروفایل";
      const icon = saveBtn.querySelector("span[aria-hidden]");
      if (icon) icon.textContent = saved ? "🔖" : "📑";
      const label = saveBtn.querySelector(".media-engage-label");
      if (label) label.textContent = saved ? "ذخیره شد" : "ذخیره";
    } catch (_err) {
      window.alert("خطا در ارتباط با سرور.");
    } finally {
      saveBtn.disabled = false;
    }
  });

  document.addEventListener("contextmenu", (event) => {
    const t = event.target;
    if (!(t instanceof Element)) return;
    if (t.closest("textarea, input, [contenteditable='true']")) return;
    if (t.closest("[data-media-protect], .media-protect, .portrait-lightbox, .post-lightbox-media")) {
      event.preventDefault();
    }
  });
  document.addEventListener("dragstart", (event) => {
    if (event.target.closest("[data-media-protect], .media-protect img, .media-protect video")) {
      event.preventDefault();
    }
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "PrintScreen") {
      document.body.classList.add("media-protect-capture-flash");
      window.setTimeout(() => document.body.classList.remove("media-protect-capture-flash"), 800);
    }
  });
  document.addEventListener("visibilitychange", () => {
    if (document.documentElement.classList.contains("is-native-app")) {
      document.documentElement.classList.remove("media-protect-obscured");
      return;
    }
    if (castingIsEditableField(document.activeElement)) {
      document.documentElement.classList.remove("media-protect-obscured");
      return;
    }
    document.documentElement.classList.toggle(
      "media-protect-obscured",
      document.hidden || document.visibilityState === "hidden"
    );
  });
  window.addEventListener("blur", () => {
    if (document.documentElement.classList.contains("is-native-app")) return;
    if (castingIsEditableField(document.activeElement)) return;
    if (window.CASTING_MEDIA_PROTECT?.isMobile) {
      document.documentElement.classList.add("media-protect-obscured");
    }
  });
  window.addEventListener("focus", () => {
    document.documentElement.classList.remove("media-protect-obscured");
  });

  const mediaUsesNativeAspect = (root) => {
    if (!root) return false;
    if (root.closest(".ig-profile-cell--thumb")) return false;
    if (root.closest(".profile-video-player, .media-protect--intro")) return false;
    if (root.closest(".profile-media-grid") && !root.closest(".post-lightbox-media")) return false;
    return true;
  };

  const applyNativeMediaAspect = (root, w, h) => {
    if (!root || !(w > 0) || !(h > 0) || !mediaUsesNativeAspect(root)) return;
    root.style.setProperty("--media-ar", `${w} / ${h}`);
    root.style.aspectRatio = `${w} / ${h}`;
  };

  const paintProtectedFrame = (root, video, canvas, ctx) => {
    try {
      const vw = video.videoWidth || 0;
      const vh = video.videoHeight || 0;
      applyNativeMediaAspect(root, vw, vh);
      const rect = root.getBoundingClientRect();
      const cssW = Math.max(1, Math.round(rect.width * (window.devicePixelRatio || 1)));
      const cssH = Math.max(1, Math.round(rect.height * (window.devicePixelRatio || 1)));
      if (canvas.width !== cssW || canvas.height !== cssH) {
        canvas.width = cssW;
        canvas.height = cssH;
      }
      ctx.fillStyle = "#000";
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      if (vw > 0 && vh > 0) {
        const scale = Math.min(canvas.width / vw, canvas.height / vh);
        const dw = vw * scale;
        const dh = vh * scale;
        const dx = (canvas.width - dw) / 2;
        const dy = (canvas.height - dh) / 2;
        ctx.drawImage(video, dx, dy, dw, dh);
      }
      const label = root.getAttribute("data-watermark") || "";
      if (label && (vw > 0 || root.classList.contains("is-playing"))) {
        ctx.save();
        ctx.translate(canvas.width / 2, canvas.height / 2);
        ctx.rotate((-22 * Math.PI) / 180);
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        const fontSize = Math.max(12, Math.round(Math.min(canvas.width, canvas.height) * 0.032));
        ctx.font = `500 ${fontSize}px Vazirmatn, Tahoma, sans-serif`;
        ctx.lineWidth = 2;
        ctx.strokeStyle = "rgba(0,0,0,0.35)";
        ctx.fillStyle = "rgba(255,255,255,0.55)";
        ctx.globalAlpha = 0.28;
        // فقط ۳ خط کم‌تراکم — نه کاشی‌کاری کامل
        const offsets = [-canvas.height * 0.22, 0, canvas.height * 0.22];
        offsets.forEach((y) => {
          ctx.strokeText(label, 0, y);
          ctx.fillText(label, 0, y);
        });
        ctx.restore();
      }
      root.classList.remove("is-video-fallback");
    } catch (_err) {
      root.classList.add("is-video-fallback");
      if (video && !video.hasAttribute("controls")) {
        video.setAttribute("controls", "");
        video.controls = true;
      }
    }
  };

  const initProtectedVideo = (root) => {
    if (!root || root.dataset.videoReady === "1") return;
    const video = root.querySelector("video.media-protect-source, video");
    const canvas = root.querySelector("canvas.media-protect-canvas");
    if (!video) return;
    // بدون canvas → کنترل‌های بومی
    if (!canvas) {
      root.dataset.videoReady = "1";
      root.classList.add("is-video-fallback");
      video.setAttribute("controls", "");
      video.controls = true;
      video.removeAttribute("muted");
      video.muted = false;
      return;
    }
    root.dataset.videoReady = "1";
    const ctx = canvas.getContext("2d");
    if (!ctx) {
      root.classList.add("is-video-fallback");
      video.setAttribute("controls", "");
      video.controls = true;
      return;
    }
    const playBtn = root.querySelector("[data-video-play]");
    const toggleBtn = root.querySelector("[data-video-toggle]");
    const seek = root.querySelector("[data-video-seek]");
    const controls = root.querySelector(".media-protect-controls");
    let raf = 0;

    const stopLoop = () => {
      if (raf) {
        cancelAnimationFrame(raf);
        raf = 0;
      }
    };

    const loop = () => {
      paintProtectedFrame(root, video, canvas, ctx);
      if (!video.paused && !video.ended) {
        raf = requestAnimationFrame(loop);
      } else {
        raf = 0;
      }
    };

    const syncUi = () => {
      root.classList.toggle("is-playing", !video.paused && !video.ended);
      if (controls) controls.hidden = false;
      if (toggleBtn) toggleBtn.textContent = video.paused ? "▶" : "❚❚";
      if (seek && video.duration && Number.isFinite(video.duration)) {
        seek.value = String(Math.round((video.currentTime / video.duration) * 1000));
      }
    };

    const play = async () => {
      try {
        await video.play();
        stopLoop();
        loop();
        syncUi();
      } catch (_err) {
        /* autoplay / gesture */
      }
    };

    const pause = () => {
      video.pause();
      stopLoop();
      paintProtectedFrame(root, video, canvas, ctx);
      syncUi();
    };

    playBtn?.addEventListener("click", (event) => {
      event.preventDefault();
      event.stopPropagation();
      if (video.paused) play();
      else pause();
    });
    toggleBtn?.addEventListener("click", (event) => {
      event.preventDefault();
      event.stopPropagation();
      if (video.paused) play();
      else pause();
    });
    seek?.addEventListener("input", () => {
      if (!video.duration || !Number.isFinite(video.duration)) return;
      video.currentTime = (Number(seek.value) / 1000) * video.duration;
      paintProtectedFrame(root, video, canvas, ctx);
    });
    video.addEventListener("loadedmetadata", () => {
      applyNativeMediaAspect(root, video.videoWidth, video.videoHeight);
      requestAnimationFrame(() => paintProtectedFrame(root, video, canvas, ctx));
    });
    video.addEventListener("loadeddata", () => paintProtectedFrame(root, video, canvas, ctx));
    video.addEventListener("seeked", () => paintProtectedFrame(root, video, canvas, ctx));
    video.addEventListener("pause", syncUi);
    video.addEventListener("play", () => {
      stopLoop();
      loop();
      syncUi();
    });
    video.addEventListener("ended", () => {
      stopLoop();
      paintProtectedFrame(root, video, canvas, ctx);
      syncUi();
    });
    video.addEventListener("timeupdate", () => {
      if (seek && video.duration && Number.isFinite(video.duration)) {
        seek.value = String(Math.round((video.currentTime / video.duration) * 1000));
      }
    });

    // First frame / poster-sized black until metadata
    paintProtectedFrame(root, video, canvas, ctx);
    if (controls) controls.hidden = false;
  };

  const initProtectedImage = (root) => {
    if (!root || root.classList.contains("media-protect--video")) return;
    const img = root.querySelector("img");
    if (!img) return;
    const apply = () => applyNativeMediaAspect(root, img.naturalWidth, img.naturalHeight);
    if (img.complete && img.naturalWidth > 0) apply();
    else img.addEventListener("load", apply, { once: true });
  };

  const bootProtectedVideos = (scope) => {
    (scope || document).querySelectorAll("[data-video-protect]").forEach(initProtectedVideo);
    (scope || document).querySelectorAll("[data-media-protect]:not(.media-protect--video)").forEach(initProtectedImage);
  };
  bootProtectedVideos(document);

  // Re-init when lightbox clones protected video
  document.addEventListener("click", (event) => {
    if (!event.target.closest("[data-post-expand]")) return;
    window.setTimeout(() => {
      const body = document.querySelector("[data-post-lightbox-body]");
      if (body) {
        body.querySelectorAll("[data-video-protect]").forEach((el) => {
          delete el.dataset.videoReady;
          initProtectedVideo(el);
        });
      }
    }, 0);
  });

  document.addEventListener("submit", async (event) => {
    const form = event.target.closest("[data-media-comment-form]");
    if (!form) return;
    event.preventDefault();
    const cfg = mediaEngageCfg();
    const mediaId = form.getAttribute("data-media-comment-form");
    const input = form.querySelector('input[name="body"]');
    const parentInput = form.querySelector('input[name="parent_id"]');
    const bodyText = (input?.value || "").trim();
    if (!mediaId || !cfg.url || !cfg.nonce || !bodyText) return;
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.disabled = true;
    try {
      const body = new URLSearchParams();
      body.set("_wpnonce", cfg.nonce);
      body.set("engage_action", "comment");
      body.set("media_id", mediaId);
      body.set("body", bodyText);
      body.set("parent_id", parentInput?.value || "0");
      const res = await fetch(cfg.url, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        body: body.toString(),
        credentials: "same-origin",
      });
      const data = await res.json();
      if (!data?.ok) {
        window.alert(data?.error || "کامنت ثبت نشد.");
        return;
      }
      if (input) input.value = "";
      clearCommentReply(form);
      const wrap = form.closest("[data-media-engage]");
      const full = wrap?.querySelector("[data-media-comments-full]");
      if (full && data.comment) {
        const node = buildCommentLi(data.comment);
        const parentId = Number(data.comment.parent_id || 0);
        if (parentId > 0) {
          const parentLi = full.querySelector(`[data-comment-id="${parentId}"]`);
          let replies = parentLi?.querySelector(":scope > .media-comment-replies");
          if (!replies && parentLi) {
            replies = document.createElement("ul");
            replies.className = "media-comment-replies";
            parentLi.appendChild(replies);
          }
          if (replies) replies.appendChild(node);
          else full.appendChild(node);
        } else {
          full.appendChild(node);
        }
      }
      const expanded = !!(postLightbox?.classList.contains("is-open") || wrap?.closest(".home-feed-post.is-zoomed"));
      if (expanded && wrap) {
        expandCommentThread(wrap);
      } else {
        refreshCommentPreview(wrap);
      }
      const countEl = wrap?.querySelector("[data-comment-count]");
      if (countEl) countEl.textContent = String(data.count ?? 0);
    } catch (_err) {
      window.alert("خطا در ارتباط با سرور.");
    } finally {
      if (submitBtn) submitBtn.disabled = false;
    }
  });

  // باز/بسته کردن جزئیات فراخوان بدون تغییر URL (جلوگیری از 404)
  const syncInvitationCard = (card, open) => {
    if (!card) return;
    card.classList.toggle("is-open", open);
    const detail = card.querySelector("[data-invitation-detail]");
    const shortEl = card.querySelector("[data-invitation-excerpt-short]");
    const fullEl = card.querySelector("[data-invitation-excerpt-full]");
    const btn = card.querySelector("[data-invitation-toggle]");
    const openLabel = card.querySelector("[data-invitation-toggle-open]");
    const closeLabel = card.querySelector("[data-invitation-toggle-close]");
    if (detail) detail.hidden = !open;
    if (shortEl) shortEl.hidden = open;
    if (fullEl) fullEl.hidden = !open;
    if (btn) btn.setAttribute("aria-expanded", open ? "true" : "false");
    if (openLabel) openLabel.hidden = open;
    if (closeLabel) closeLabel.hidden = !open;
  };

  document.querySelectorAll("[data-invitation-card].is-open").forEach((card) => {
    syncInvitationCard(card, true);
  });

  const openInviteFromHash = () => {
    const hash = String(window.location.hash || "");
    const match = hash.match(/^#invite-([A-Za-z0-9_-]+)$/);
    if (!match) return;
    const token = match[1];
    const card = document.querySelector(`[data-invitation-card][data-invite-token="${token}"]`);
    if (!card) return;
    document.querySelectorAll("[data-invitation-card].is-open").forEach((other) => {
      if (other !== card) syncInvitationCard(other, false);
    });
    syncInvitationCard(card, true);
    card.id = "invitation-detail";
    window.setTimeout(() => {
      card.scrollIntoView({ behavior: "smooth", block: "nearest" });
    }, 50);
  };
  openInviteFromHash();
  window.addEventListener("hashchange", openInviteFromHash);

  document.addEventListener("click", (event) => {
    const btn = event.target.closest("[data-invitation-toggle]");
    if (!btn) return;
    event.preventDefault();
    const card = btn.closest("[data-invitation-card]");
    if (!card) return;
    const willOpen = !card.classList.contains("is-open");
    document.querySelectorAll("[data-invitation-card].is-open").forEach((other) => {
      if (other !== card) syncInvitationCard(other, false);
    });
    syncInvitationCard(card, willOpen);
    if (willOpen) {
      card.id = "invitation-detail";
      const token = card.getAttribute("data-invite-token") || "";
      if (token && window.history && window.history.replaceState) {
        window.history.replaceState(null, "", `${window.location.pathname}${window.location.search}#invite-${token}`);
      }
      card.scrollIntoView({ behavior: "smooth", block: "nearest" });
    } else if (window.history && window.history.replaceState) {
      window.history.replaceState(null, "", `${window.location.pathname}${window.location.search}`);
    }
  });

  // خروج واقعی پس از ۱۵ دقیقه بدون کلیک/اسکرول/تایپ — پینگ پس‌زمینه تمدید نمی‌کند
  const sessionCfg = window.CASTING_SESSION;
  if (sessionCfg && sessionCfg.active) {
    const idleMs = Math.max(60, Number(sessionCfg.idleSeconds) || 900) * 1000;
    const pingUrl = String(sessionCfg.pingUrl || "");
    const logoutUrl = String(sessionCfg.logoutUrl || "logout.php?reason=idle");
    const LAST_KEY = "casting_last_activity_at";
    let lastActivityAt = Date.now();
    try {
      const stored = Number(sessionStorage.getItem(LAST_KEY) || "0");
      if (stored > 0) lastActivityAt = stored;
    } catch (_err) {}
    let lastPing = 0;
    let loggingOut = false;
    let idleResetQueued = 0;

    const persistActivity = (ts) => {
      lastActivityAt = ts;
      try {
        sessionStorage.setItem(LAST_KEY, String(ts));
      } catch (_err) {}
    };

    const doIdleLogout = () => {
      if (loggingOut) return;
      loggingOut = true;
      try {
        sessionStorage.removeItem(LAST_KEY);
      } catch (_err) {}
      window.location.href = logoutUrl;
    };

    const checkIdleNow = () => {
      if (loggingOut) return true;
      if (Date.now() - lastActivityAt >= idleMs) {
        doIdleLogout();
        return true;
      }
      return false;
    };

    const pingSession = () => {
      if (!pingUrl || loggingOut) return;
      const now = Date.now();
      if (now - lastPing < 45000) return;
      lastPing = now;
      fetch(pingUrl, {
        method: "GET",
        credentials: "same-origin",
        cache: "no-store",
        headers: { Accept: "application/json" },
      })
        .then((res) => {
          if (res.status === 401) doIdleLogout();
        })
        .catch(() => {});
    };

    const markActivity = () => {
      if (loggingOut) return;
      if (idleResetQueued) return;
      idleResetQueued = window.setTimeout(() => {
        idleResetQueued = 0;
        persistActivity(Date.now());
        pingSession();
      }, 200);
    };

    if (!checkIdleNow()) {
      persistActivity(Date.now());
      pingSession();
    }

    ["pointerdown", "keydown", "scroll"].forEach((evt) => {
      document.addEventListener(evt, markActivity, { passive: true });
    });
    document.addEventListener(
      "visibilitychange",
      () => {
        if (document.visibilityState !== "visible") return;
        checkIdleNow();
      },
      { passive: true }
    );
    window.setInterval(checkIdleNow, 10000);
    window.addEventListener("pageshow", checkIdleNow);
  }

  // Application Manager: select all applicants in folder
  document.addEventListener("change", (event) => {
    const all = event.target.closest("[data-app-select-all]");
    if (!all) return;
    const checked = !!all.checked;
    document.querySelectorAll("[data-app-select]").forEach((box) => {
      box.checked = checked;
    });
  });

  // Application Manager: applicant note shadow/lightbox
  const appNoteLightbox = document.querySelector("[data-app-note-lightbox]");
  if (appNoteLightbox && appNoteLightbox.parentElement !== document.body) {
    document.body.appendChild(appNoteLightbox);
  }
  const appNoteTitle = appNoteLightbox?.querySelector("[data-app-note-lightbox-title]");
  const appNoteBody = appNoteLightbox?.querySelector("[data-app-note-lightbox-body]");

  const closeAppNoteLightbox = () => {
    if (!appNoteLightbox?.classList.contains("is-open")) return;
    appNoteLightbox.classList.remove("is-open");
    appNoteLightbox.setAttribute("aria-hidden", "true");
    if (appNoteBody) appNoteBody.textContent = "";
    document.body.style.overflow = "";
  };

  const openAppNoteLightbox = (trigger) => {
    if (!appNoteLightbox || !appNoteBody) return;
    const title = trigger.getAttribute("data-app-note-title") || "یادداشت متقاضی";
    const body = trigger.getAttribute("data-app-note-body") || "";
    if (appNoteTitle) appNoteTitle.textContent = title;
    appNoteBody.textContent = body;
    appNoteLightbox.classList.add("is-open");
    appNoteLightbox.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
  };

  document.addEventListener("click", (event) => {
    const openBtn = event.target.closest("[data-app-note-open]");
    if (openBtn) {
      event.preventDefault();
      openAppNoteLightbox(openBtn);
      return;
    }
    if (event.target.closest("[data-app-note-lightbox-close]")) {
      event.preventDefault();
      closeAppNoteLightbox();
      return;
    }
    if (
      appNoteLightbox?.classList.contains("is-open") &&
      !event.target.closest(".app-note-lightbox-panel")
    ) {
      closeAppNoteLightbox();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") closeAppNoteLightbox();
  });

  // پیش‌نمایش فوری عکس/ویدیو قبل از ثبت
  const revokePreviewUrl = (el) => {
    const prev = el?.dataset?.previewObjectUrl;
    if (prev) {
      try {
        URL.revokeObjectURL(prev);
      } catch (_err) {
        /* ignore */
      }
      delete el.dataset.previewObjectUrl;
    }
  };

  const showImagePreview = (card, file) => {
    const frame = card.querySelector("[data-file-preview-frame]");
    if (!frame) return;
    let img = frame.querySelector("[data-file-preview-img]");
    const empty = frame.querySelector("[data-file-preview-empty]");
    if (!img) {
      img = document.createElement("img");
      img.setAttribute("data-file-preview-img", "");
      img.alt = "پیش‌نمایش";
      img.decoding = "async";
      frame.appendChild(img);
    }
    revokePreviewUrl(img);
    const url = URL.createObjectURL(file);
    img.dataset.previewObjectUrl = url;
    img.src = url;
    if (empty) empty.hidden = true;
    frame.hidden = false;
  };

  const showVideoPreview = (card, file) => {
    const frame = card.querySelector("[data-file-preview-frame]");
    if (!frame) return;
    let video = frame.querySelector("[data-file-preview-video]");
    if (!video) {
      video = document.createElement("video");
      video.setAttribute("data-file-preview-video", "");
      video.controls = true;
      video.playsInline = true;
      video.preload = "metadata";
      frame.appendChild(video);
    }
    revokePreviewUrl(video);
    const url = URL.createObjectURL(file);
    video.dataset.previewObjectUrl = url;
    video.src = url;
    frame.hidden = false;
  };

  const uploadLimitByKind = {
    image: { bytes: 5 * 1024 * 1024, label: "۵ مگابایت", title: "عکس" },
    video: { bytes: 40 * 1024 * 1024, label: "۴۰ مگابایت", title: "ویدیو" },
    audio: { bytes: 25 * 1024 * 1024, label: "۲۵ مگابایت", title: "فایل صوتی" },
  };

  const detectUploadKind = (input) => {
    const explicit = (input.getAttribute("data-upload-kind") || "").toLowerCase();
    if (explicit === "video" || explicit === "audio" || explicit === "image") {
      return explicit;
    }
    const previewKind = (input.getAttribute("data-file-preview-kind") || "").toLowerCase();
    if (previewKind === "video" || previewKind === "audio") {
      return previewKind;
    }
    const accept = (input.getAttribute("accept") || "").toLowerCase();
    if (accept.includes("video")) return "video";
    if (accept.includes("audio")) return "audio";
    return "image";
  };

  const uploadTooLargeMessage = (kindKey) => {
    const lim = uploadLimitByKind[kindKey] || uploadLimitByKind.image;
    return `حجم ${lim.title} بالاتر از حد مجاز است. حداکثر حجم مجاز ${lim.label} است.`;
  };

  const validateUploadFileInput = (input) => {
    if (!(input instanceof HTMLInputElement) || input.type !== "file") return true;
    const file = input.files && input.files[0] ? input.files[0] : null;
    if (!file) return true;
    const accept = (input.getAttribute("accept") || "").toLowerCase();
    const hasLimitHint =
      input.hasAttribute("data-max-bytes") ||
      input.hasAttribute("data-upload-kind") ||
      accept.includes("image") ||
      accept.includes("video") ||
      accept.includes("audio");
    if (!hasLimitHint) return true;
    const kindKey = detectUploadKind(input);
    const lim = uploadLimitByKind[kindKey] || uploadLimitByKind.image;
    const maxAttr = parseInt(input.getAttribute("data-max-bytes") || "", 10);
    const maxBytes = Number.isFinite(maxAttr) && maxAttr > 0 ? maxAttr : lim.bytes;
    if (file.size > maxBytes) {
      window.alert(uploadTooLargeMessage(kindKey));
      input.value = "";
      return false;
    }
    return true;
  };

  document.addEventListener(
    "change",
    (event) => {
      const input = event.target.closest("input[type='file']");
      if (!(input instanceof HTMLInputElement)) return;
      validateUploadFileInput(input);
    },
    true
  );

  document.addEventListener(
    "submit",
    (event) => {
      const form = event.target;
      if (!(form instanceof HTMLFormElement)) return;
      if ((form.getAttribute("enctype") || "").toLowerCase() !== "multipart/form-data") return;
      const inputs = form.querySelectorAll("input[type='file']");
      for (const input of inputs) {
        if (!validateUploadFileInput(input)) {
          event.preventDefault();
          event.stopPropagation();
          return;
        }
      }
    },
    true
  );

  document.addEventListener("change", (event) => {
    const input = event.target.closest("[data-file-preview-input], input[type='file'][accept*='image']");
    if (!(input instanceof HTMLInputElement) || input.type !== "file") return;
    const file = input.files && input.files[0] ? input.files[0] : null;
    const card =
      input.closest("[data-file-preview-card]") ||
      input.closest(".portrait-upload-card") ||
      input.closest(".field");
    if (!card || !file) return;

    const kind = input.getAttribute("data-file-preview-kind") || "";
    const isVideo = kind === "video" || (file.type || "").startsWith("video/");
    if (isVideo) {
      showVideoPreview(card, file);
      return;
    }
    if ((file.type || "").startsWith("image/") || /\.(jpe?g|png|webp)$/i.test(file.name || "")) {
      showImagePreview(card, file);
    }
  });

  document.querySelectorAll("[data-mobile2-extra]").forEach((box) => {
    const addBtn = box.querySelector("[data-mobile2-add]");
    const field = box.querySelector("[data-mobile2-field]");
    const input = box.querySelector("[data-mobile2-input]");
    const removeBtn = box.querySelector("[data-mobile2-remove]");
    if (!(field instanceof HTMLElement) || !(input instanceof HTMLInputElement)) return;

    const show = () => {
      field.hidden = false;
      if (addBtn instanceof HTMLElement) addBtn.hidden = true;
      input.focus();
    };
    const hide = () => {
      input.value = "";
      field.hidden = true;
      if (addBtn instanceof HTMLElement) addBtn.hidden = false;
    };

    if (addBtn) {
      addBtn.addEventListener("click", (e) => {
        e.preventDefault();
        show();
      });
    }
    if (removeBtn) {
      removeBtn.addEventListener("click", (e) => {
        e.preventDefault();
        hide();
      });
    }
  });

  /**
   * Poll با backoff: وقتی پیام جدید نیست فاصله زیاد می‌شود؛ تب مخفی = خیلی کمتر درخواست.
   * @param {() => Promise<boolean>|boolean} run — true اگر فعالیت جدید بود
   * @param {{minMs?:number,maxMs?:number,hiddenMs?:number,factor?:number}} [opts]
   */
  const castingAdaptivePoll = (run, opts = {}) => {
    const minMs = Number(opts.minMs) > 0 ? Number(opts.minMs) : 5000;
    const maxMs = Number(opts.maxMs) > 0 ? Number(opts.maxMs) : 25000;
    const hiddenMs = Number(opts.hiddenMs) > 0 ? Number(opts.hiddenMs) : 45000;
    const factor = Number(opts.factor) > 1 ? Number(opts.factor) : 1.6;
    let delay = minMs;
    let timer = 0;
    let stopped = false;

    const schedule = () => {
      if (stopped) return;
      window.clearTimeout(timer);
      const wait = document.hidden ? Math.max(delay, hiddenMs) : delay;
      timer = window.setTimeout(async () => {
        if (stopped) return;
        if (document.hidden) {
          schedule();
          return;
        }
        let hadNew = false;
        try {
          hadNew = !!(await run());
        } catch (_err) {
          hadNew = false;
        }
        delay = hadNew ? minMs : Math.min(maxMs, Math.round(delay * factor));
        schedule();
      }, wait);
    };

    document.addEventListener("visibilitychange", () => {
      if (stopped) return;
      if (!document.hidden) {
        delay = minMs;
        schedule();
      }
    });

    schedule();
    return {
      bump() {
        delay = minMs;
        schedule();
      },
      stop() {
        stopped = true;
        window.clearTimeout(timer);
      },
    };
  };

  // ویجت شناور پیام‌ها + چت داخل همان پنل (در اپ موبایل مخفی است؛ JS را هم اجرا نکن)
  const messagesDock = document.querySelector("[data-messages-dock]");
  if (messagesDock && !castingIsNativeAppShell()) {
    const toggle = messagesDock.querySelector("[data-messages-dock-toggle]");
    const panel = messagesDock.querySelector("[data-messages-dock-panel]");
    const listView = messagesDock.querySelector("[data-messages-dock-list-view]");
    const threadView = messagesDock.querySelector("[data-messages-dock-thread-view]");
    const threadEl = messagesDock.querySelector("[data-messages-dock-thread]");
    const compose = messagesDock.querySelector("[data-messages-dock-compose]");
    const peerIdInput = messagesDock.querySelector("[data-messages-dock-peer-id]");
    const input = messagesDock.querySelector("[data-messages-dock-input]");
    const errEl = messagesDock.querySelector("[data-messages-dock-error]");
    const peerNameEl = messagesDock.querySelector("[data-messages-dock-peer-name]");
    const peerRoleEl = messagesDock.querySelector("[data-messages-dock-peer-role]");
    const fullLink = messagesDock.querySelector("[data-messages-dock-full]");
    const cfg = window.CASTING_CHAT_DOCK || {};
    let dockLastId = 0;

    const setOpen = (open) => {
      if (!(panel instanceof HTMLElement) || !(toggle instanceof HTMLElement)) return;
      panel.hidden = !open;
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
      if (!open) showList();
    };

    const showList = () => {
      if (listView) listView.hidden = false;
      if (threadView) threadView.hidden = true;
      if (errEl) {
        errEl.hidden = true;
        errEl.textContent = "";
      }
    };

    const showThread = () => {
      if (listView) listView.hidden = true;
      if (threadView) threadView.hidden = false;
    };

    const renderMessages = (messages) => {
      if (!(threadEl instanceof HTMLElement)) return;
      threadEl.innerHTML = "";
      (messages || []).forEach((msg) => {
        const bubble = document.createElement("div");
        bubble.className = "messages-dock-bubble " + (msg.is_mine ? "is-mine" : "is-theirs");
        if (msg.id) bubble.setAttribute("data-msg-id", String(msg.id));
        bubble.textContent = msg.message || "";
        threadEl.appendChild(bubble);
      });
      threadEl.scrollTop = threadEl.scrollHeight;
    };

    const openThread = async (peerId, meta = {}) => {
      if (!cfg.url || !cfg.nonce || !peerId) return;
      dockLastId = 0;
      setOpen(true);
      showThread();
      if (peerNameEl) peerNameEl.textContent = meta.name || "گفتگو";
      if (peerRoleEl) {
        peerRoleEl.textContent = meta.role || "";
        peerRoleEl.hidden = !meta.role;
      }
      if (peerIdInput) peerIdInput.value = String(peerId);
      if (fullLink) fullLink.href = (cfg.fullUrl || "chat.php") + "?with=" + encodeURIComponent(String(peerId));
      if (threadEl) threadEl.innerHTML = "<p class='meta'>در حال بارگذاری…</p>";
      if (errEl) {
        errEl.hidden = true;
        errEl.textContent = "";
      }
      try {
        const url = new URL(cfg.url, window.location.origin);
        url.searchParams.set("action", "thread");
        url.searchParams.set("peer_id", String(peerId));
        url.searchParams.set("_wpnonce", cfg.nonce);
        const res = await fetch(url.toString(), {
          credentials: "same-origin",
          headers: { Accept: "application/json" },
        });
        const data = await res.json();
        if (!data || !data.ok) {
          if (threadEl) threadEl.innerHTML = "";
          if (errEl) {
            errEl.hidden = false;
            errEl.textContent = (data && data.error) || "بارگذاری گفتگو ناموفق بود.";
          }
          return;
        }
        if (data.peer) {
          if (peerNameEl) peerNameEl.textContent = data.peer.name || meta.name || "گفتگو";
          if (peerRoleEl) {
            peerRoleEl.textContent = data.peer.role || "";
            peerRoleEl.hidden = !data.peer.role;
          }
        }
        if (data.locked) {
          renderMessages([]);
          if (compose) compose.hidden = true;
          if (errEl) {
            errEl.hidden = false;
            errEl.innerHTML = "";
            errEl.textContent = data.error || "ارسال پیام برای اعضای ویژه فعال است (عضویت ویژه)";
            const cartUrl = data.cart_url || "cart.php";
            const cartLink = document.createElement("a");
            cartLink.href = cartUrl;
            cartLink.className = "btn btn-primary btn-sm";
            cartLink.textContent = "خرید اشتراک";
            errEl.appendChild(document.createElement("br"));
            errEl.appendChild(cartLink);
          }
          return;
        }
        if (compose) {
          compose.hidden = !data.can_send;
        }
        if (!data.can_send && data.error && errEl) {
          errEl.hidden = false;
          errEl.textContent = data.error;
        }
        renderMessages(data.messages || []);
        if (typeof data.last_id === "number") dockLastId = data.last_id;
        input?.focus();
      } catch (e) {
        if (threadEl) threadEl.innerHTML = "";
        if (errEl) {
          errEl.hidden = false;
          errEl.textContent = "خطا در ارتباط با سرور.";
        }
      }
    };

    toggle?.addEventListener("click", (event) => {
      event.preventDefault();
      const open = toggle.getAttribute("aria-expanded") !== "true";
      setOpen(open);
    });

    messagesDock.querySelector("[data-messages-dock-back]")?.addEventListener("click", (event) => {
      event.preventDefault();
      showList();
    });

    messagesDock.querySelectorAll("[data-messages-dock-open]").forEach((btn) => {
      btn.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        const peerId = Number(btn.getAttribute("data-messages-dock-open") || "0");
        openThread(peerId, {
          name: btn.getAttribute("data-peer-name") || "",
          role: btn.getAttribute("data-peer-role") || "",
        });
      });
    });

    const filterInput = messagesDock.querySelector("[data-messages-dock-filter]");
    filterInput?.addEventListener("input", () => {
      const q = String(filterInput.value || "").trim().toLowerCase();
      messagesDock.querySelectorAll("[data-dock-user-row]").forEach((row) => {
        const name = String(row.getAttribute("data-dock-user-name") || "");
        row.hidden = q !== "" && !name.includes(q);
      });
    });

    compose?.addEventListener("submit", async (event) => {
      event.preventDefault();
      if (!(input instanceof HTMLTextAreaElement) || !(peerIdInput instanceof HTMLInputElement)) return;
      const peerId = Number(peerIdInput.value || "0");
      const message = (input.value || "").trim();
      if (!peerId || !message || !cfg.url || !cfg.nonce) return;
      const submitBtn = compose.querySelector('button[type="submit"]');
      if (submitBtn) submitBtn.disabled = true;
      try {
        const body = new FormData();
        body.set("action", "send");
        body.set("peer_id", String(peerId));
        body.set("message", message);
        body.set("_wpnonce", cfg.nonce);
        const res = await fetch(cfg.url, {
          method: "POST",
          credentials: "same-origin",
          body,
          headers: { Accept: "application/json" },
        });
        const data = await res.json();
        if (!data || !data.ok) {
          if (errEl) {
            errEl.hidden = false;
            errEl.textContent = (data && data.error) || "ارسال ناموفق بود.";
          }
          return;
        }
        input.value = "";
        if (errEl) {
          errEl.hidden = true;
          errEl.textContent = "";
        }
        if (threadEl && data.message) {
          const bubble = document.createElement("div");
          bubble.className = "messages-dock-bubble is-mine";
          if (data.message.id) {
            bubble.setAttribute("data-msg-id", String(data.message.id));
            const newId = Number(data.message.id || 0);
            if (newId > dockLastId) dockLastId = newId;
          }
          bubble.textContent = data.message.message || message;
          threadEl.appendChild(bubble);
          threadEl.scrollTop = threadEl.scrollHeight;
        }
        dockPoll.bump();
      } catch (e) {
        if (errEl) {
          errEl.hidden = false;
          errEl.textContent = "خطا در ارسال پیام.";
        }
      } finally {
        if (submitBtn) submitBtn.disabled = false;
      }
    });

    document.addEventListener("click", (event) => {
      if (!messagesDock.contains(event.target)) {
        setOpen(false);
      }
    });
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") setOpen(false);
    });

    const dockPoll = castingAdaptivePoll(
      async () => {
        if (!(threadView instanceof HTMLElement) || threadView.hidden) return false;
        const peerId = Number(peerIdInput instanceof HTMLInputElement ? peerIdInput.value : 0);
        if (!peerId || !cfg.url || !cfg.nonce) return false;
        try {
          const url = new URL(cfg.url, window.location.origin);
          url.searchParams.set("action", "thread");
          url.searchParams.set("peer_id", String(peerId));
          url.searchParams.set("after_id", String(dockLastId));
          url.searchParams.set("poll", "1");
          url.searchParams.set("_wpnonce", cfg.nonce);
          const res = await fetch(url.toString(), {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
          });
          const data = await res.json();
          if (!data || !data.ok || data.locked) return false;
          const incoming = Array.isArray(data.messages) ? data.messages : [];
          incoming.forEach((msg) => {
            if (!(threadEl instanceof HTMLElement)) return;
            if (threadEl.querySelector('[data-msg-id="' + String(msg.id || "") + '"]')) return;
            const bubble = document.createElement("div");
            bubble.className = "messages-dock-bubble " + (msg.is_mine ? "is-mine" : "is-theirs");
            bubble.setAttribute("data-msg-id", String(msg.id || ""));
            bubble.textContent = msg.message || "";
            threadEl.appendChild(bubble);
          });
          if (typeof data.last_id === "number" && data.last_id > dockLastId) {
            dockLastId = data.last_id;
          }
          if (incoming.length && threadEl instanceof HTMLElement) {
            threadEl.scrollTop = threadEl.scrollHeight;
          }
          return incoming.length > 0;
        } catch (_err) {
          return false;
        }
      },
      { minMs: 5000, maxMs: 25000, hiddenMs: 45000 }
    );
    messagesDock.addEventListener("click", () => dockPoll.bump());
  }

  // چت زنده: پیام جدید بدون رفرش
  const liveThread = document.querySelector("[data-chat-live]");
  const chatDockCfg = window.CASTING_CHAT_DOCK || {};

  const copyChatText = async (text) => {
    const value = String(text || "").trim();
    if (!value) return false;
    rememberCopiedText(value);
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(value);
        return true;
      }
    } catch (_err) {
      /* fallback */
    }
    const ta = document.createElement("textarea");
    ta.value = value;
    ta.setAttribute("readonly", "");
    ta.style.position = "fixed";
    ta.style.opacity = "0";
    document.body.appendChild(ta);
    ta.select();
    let ok = false;
    try {
      ok = document.execCommand("copy");
    } catch (_err2) {
      ok = false;
    }
    ta.remove();
    return ok;
  };

  const chatShareCartUrl = (href) => {
    if (href) return href;
    try {
      return new URL("cart.php", window.location.href).toString();
    } catch (_err) {
      return "cart.php";
    }
  };

  const closeChatShareSheet = () => {
    document.getElementById("chat-share-overlay")?.remove();
  };

  const chatShareTargetsFromPage = () => {
    const items = document.querySelectorAll(".chat-conv-item[href]");
    const out = [];
    const seen = {};
    items.forEach((a) => {
      if (!(a instanceof HTMLAnchorElement)) return;
      let id = 0;
      try {
        id = Number(new URL(a.href, window.location.href).searchParams.get("with") || 0);
      } catch (_err) {
        id = 0;
      }
      if (!id || seen[id]) return;
      seen[id] = true;
      const name = String(a.querySelector(".chat-conv-name")?.textContent || "").trim();
      if (!name) return;
      out.push({ id, name, role: "" });
    });
    return out;
  };

  const chatShareFoldFa = (value) =>
    String(value || "")
      .toLocaleLowerCase("fa")
      .replace(/ي/g, "ی")
      .replace(/ى/g, "ی")
      .replace(/ك/g, "ک");

  const chatShareParseJson = async (res) => {
    const text = await res.text();
    try {
      return JSON.parse(text);
    } catch (_err) {
      return null;
    }
  };

  const openChatShareSheet = async (rawMessage) => {
    const message = String(rawMessage || "").trim();
    if (!message) {
      window.alert("متنی برای اشتراک نیست.");
      return;
    }
    if (!chatDockCfg.url || !chatDockCfg.nonce) {
      window.alert("امکان اشتراک در این صفحه نیست.");
      return;
    }
    closeChatShareSheet();
    const overlay = document.createElement("div");
    overlay.id = "chat-share-overlay";
    overlay.className = "chat-share-overlay";
    overlay.setAttribute("role", "presentation");
    const sheet = document.createElement("div");
    sheet.className = "chat-share-sheet";
    sheet.setAttribute("role", "dialog");
    sheet.setAttribute("aria-modal", "true");
    sheet.setAttribute("aria-labelledby", "chat-share-title");
    const title = document.createElement("h2");
    title.id = "chat-share-title";
    title.textContent = "ارسال به چند نفر";
    const preview = document.createElement("p");
    preview.className = "chat-share-preview";
    preview.textContent = message.length > 180 ? message.slice(0, 180) + "…" : message;
    const bodyBox = document.createElement("div");
    bodyBox.className = "chat-share-body";
    const status = document.createElement("p");
    status.className = "chat-share-status";
    status.textContent = "در حال بارگذاری مخاطبان…";
    const actions = document.createElement("div");
    actions.className = "chat-share-actions";
    const cancelBtn = document.createElement("button");
    cancelBtn.type = "button";
    cancelBtn.className = "btn btn-ghost";
    cancelBtn.textContent = "بستن";
    cancelBtn.addEventListener("click", closeChatShareSheet);
    actions.appendChild(cancelBtn);
    sheet.appendChild(title);
    sheet.appendChild(preview);
    sheet.appendChild(bodyBox);
    sheet.appendChild(status);
    sheet.appendChild(actions);
    overlay.appendChild(sheet);
    overlay.addEventListener("click", (event) => {
      if (event.target === overlay) closeChatShareSheet();
    });
    document.body.appendChild(overlay);

    const onKey = (event) => {
      if (event.key === "Escape") {
        closeChatShareSheet();
        document.removeEventListener("keydown", onKey);
      }
    };
    document.addEventListener("keydown", onKey);

    const showPremium = (cartUrl) => {
      bodyBox.innerHTML = "";
      status.textContent = "";
      const note = document.createElement("p");
      note.className = "chat-premium-send-note";
      note.append("ارسال پیام برای اعضای ویژه فعال است (");
      const link = document.createElement("a");
      link.href = chatShareCartUrl(cartUrl);
      link.textContent = "عضویت ویژه";
      note.appendChild(link);
      note.append("). مدیران پورتال مستثنی‌اند.");
      bodyBox.appendChild(note);
    };

    try {
      let data = null;
      try {
        const url = new URL(chatDockCfg.url, window.location.origin);
        url.searchParams.set("action", "share_targets");
        url.searchParams.set("_wpnonce", chatDockCfg.nonce);
        const ctrl = new AbortController();
        const timer = window.setTimeout(() => ctrl.abort(), 8000);
        const res = await fetch(url.toString(), {
          credentials: "same-origin",
          headers: { Accept: "application/json" },
          signal: ctrl.signal,
        });
        window.clearTimeout(timer);
        data = await chatShareParseJson(res);
      } catch (_err) {
        data = null;
      }
      if (data && (data.needs_premium || data.can_share === false)) {
        showPremium(data.cart_url);
        return;
      }
      const pageTargets = chatShareTargetsFromPage();
      const targets = (data && data.ok && Array.isArray(data.targets) && data.targets.length)
        ? data.targets
        : pageTargets;
      const extraTargets = [];
      const search = document.createElement("input");
      search.type = "search";
      search.className = "chat-share-search";
      search.placeholder = "نام فارسی یا نام کاربری…";
      const list = document.createElement("ul");
      list.className = "chat-share-list";
      const maxPeers = Math.max(1, Number((data && data.max_peers) || 12));
      const mergedTargets = () => {
        const seen = {};
        const out = [];
        [...targets, ...extraTargets].forEach((target) => {
          const id = Number(target.id || 0);
          if (!id || seen[id]) return;
          seen[id] = true;
          out.push(target);
        });
        return out;
      };
      const renderList = (filter) => {
        const q = chatShareFoldFa(filter);
        list.innerHTML = "";
        const rows = mergedTargets().filter((target) => {
          if (!q) return true;
          return chatShareFoldFa(target.name).includes(q)
            || chatShareFoldFa(target.login).includes(q)
            || chatShareFoldFa(target.role).includes(q);
        });
        if (!rows.length) {
          const empty = document.createElement("li");
          empty.className = "chat-share-empty";
          empty.textContent = q.length >= 2
            ? "کسی با این نام پیدا نشد."
            : "نام فارسی یا نام کاربری را بنویسید.";
          list.appendChild(empty);
          return;
        }
        rows.forEach((target) => {
          const name = String(target.name || "");
          const role = String(target.role || target.login || "");
          const li = document.createElement("li");
          const label = document.createElement("label");
          label.className = "chat-share-item";
          const cb = document.createElement("input");
          cb.type = "checkbox";
          cb.value = String(target.id || "");
          const meta = document.createElement("span");
          meta.className = "chat-share-item-name";
          const strong = document.createElement("strong");
          strong.textContent = name || "کاربر";
          meta.appendChild(strong);
          if (role) {
            const roleEl = document.createElement("span");
            roleEl.textContent = role;
            meta.appendChild(roleEl);
          }
          label.appendChild(cb);
          label.appendChild(meta);
          li.appendChild(label);
          list.appendChild(li);
        });
      };
      let shareSuggestTimer = 0;
      let shareSuggestAbort = null;
      const fetchShareNameTargets = (query) => {
        window.clearTimeout(shareSuggestTimer);
        shareSuggestAbort?.abort();
        extraTargets.length = 0;
        const q = String(query || "").trim();
        if (q.length < 2) {
          renderList(query);
          return;
        }
        shareSuggestTimer = window.setTimeout(async () => {
          const controller = new AbortController();
          shareSuggestAbort = controller;
          try {
            const params = new URLSearchParams({ q });
            const res = await fetch(`search-members-suggest.php?${params.toString()}`, {
              signal: controller.signal,
              credentials: "same-origin",
              headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
            });
            const suggest = await chatShareParseJson(res);
            const items = Array.isArray(suggest && suggest.items) ? suggest.items : [];
            extraTargets.length = 0;
            items.forEach((item) => {
              extraTargets.push({
                id: Number(item.id || 0),
                name: String(item.name || ""),
                login: String(item.login || ""),
                role: String(item.role || item.login || ""),
              });
            });
          } catch (_err) {
            extraTargets.length = 0;
          }
          renderList(query);
        }, 220);
      };
      search.addEventListener("input", () => fetchShareNameTargets(search.value));
      renderList("");
      bodyBox.appendChild(search);
      bodyBox.appendChild(list);
      status.textContent = (data && data.quota_hint)
        ? data.quota_hint
        : "نام فارسی را بنویسید و مخاطب را انتخاب کنید. ارسال برای اعضای ویژه است.";
      const sendBtn = document.createElement("button");
      sendBtn.type = "button";
      sendBtn.className = "btn btn-primary";
      sendBtn.textContent = "ارسال";
      sendBtn.addEventListener("click", async () => {
        const ids = Array.from(list.querySelectorAll("input[type='checkbox']:checked"))
          .map((el) => Number(el.value || 0))
          .filter((id) => id > 0);
        if (!ids.length) {
          window.alert("حداقل یک مخاطب را انتخاب کنید.");
          return;
        }
        if (ids.length > maxPeers) {
          window.alert("حداکثر " + maxPeers + " مخاطب در هر اشتراک.");
          return;
        }
        sendBtn.disabled = true;
        try {
          const body = new FormData();
          body.set("action", "share");
          body.set("message", message);
          body.set("peer_ids", JSON.stringify(ids));
          body.set("_wpnonce", chatDockCfg.nonce);
          const shareRes = await fetch(chatDockCfg.url, {
            method: "POST",
            credentials: "same-origin",
            body,
            headers: { Accept: "application/json" },
          });
          const shareData = await chatShareParseJson(shareRes);
          if (shareData && shareData.needs_premium) {
            showPremium(shareData.cart_url);
            search.remove();
            list.remove();
            sendBtn.remove();
            return;
          }
          if (!shareData || !shareData.ok) {
            window.alert((shareData && shareData.error) || "ارسال ناموفق بود.");
            return;
          }
          const sentCount = Number(shareData.sent_count || 0);
          closeChatShareSheet();
          window.alert(
            shareData.error
              ? shareData.error
              : (sentCount + " پیام ارسال شد.")
          );
        } catch (_err) {
          window.alert("خطا در ارسال پیام.");
        } finally {
          sendBtn.disabled = false;
        }
      });
      actions.insertBefore(sendBtn, cancelBtn);
    } catch (_err) {
      status.textContent = "خطا در ارتباط با سرور.";
    }
  };

  const setChatBubbleEdited = (bubble, edited) => {
    if (!(bubble instanceof HTMLElement)) return;
    bubble.setAttribute("data-edited", edited ? "1" : "0");
    let badge = bubble.querySelector(".chat-bubble-edited");
    if (edited) {
      if (!badge) {
        badge = document.createElement("span");
        badge.className = "chat-bubble-edited";
        badge.textContent = "ویرایش‌شده";
        const textEl = bubble.querySelector(".chat-bubble-text");
        if (textEl) textEl.insertAdjacentElement("afterend", badge);
        else bubble.appendChild(badge);
      }
    } else if (badge) {
      badge.remove();
    }
  };

  const buildChatBubbleActions = (msg) => {
    const wrap = document.createElement("div");
    wrap.className = "chat-bubble-actions";
    const copyBtn = document.createElement("button");
    copyBtn.type = "button";
    copyBtn.className = "chat-bubble-action";
    copyBtn.setAttribute("data-chat-copy", String(msg.id || ""));
    copyBtn.textContent = "کپی";
    wrap.appendChild(copyBtn);
    const shareBtn = document.createElement("button");
    shareBtn.type = "button";
    shareBtn.className = "chat-bubble-action";
    shareBtn.setAttribute("data-chat-share", String(msg.id || ""));
    shareBtn.textContent = "اشتراک";
    wrap.appendChild(shareBtn);
    if (msg.can_edit) {
      const editBtn = document.createElement("button");
      editBtn.type = "button";
      editBtn.className = "chat-bubble-action";
      editBtn.setAttribute("data-chat-edit", String(msg.id || ""));
      editBtn.textContent = "ویرایش";
      wrap.appendChild(editBtn);
    }
    return wrap;
  };

    const fillChatBubbleBody = (article, msg) => {
      const photoUrl = String(msg.photo_url || "");
      if (photoUrl || msg.is_photo) {
        const p = document.createElement("p");
        p.className = "chat-bubble-text is-photo";
        const link = document.createElement("a");
        link.href = photoUrl;
        link.target = "_blank";
        link.rel = "noopener";
        const img = document.createElement("img");
        img.src = photoUrl;
        img.alt = "عکس";
        link.appendChild(img);
        p.appendChild(link);
        article.appendChild(p);
        article.setAttribute("data-photo-url", photoUrl);
        return;
      }
      const p = document.createElement("p");
      p.className = "chat-bubble-text";
      p.textContent = msg.message || "";
      article.appendChild(p);
    };

    const buildChatBubble = (msg, peerName) => {
    const article = document.createElement("article");
    article.className = "chat-bubble " + (msg.is_mine ? "is-mine" : "");
    const id = String(msg.id || "");
    if (id) article.setAttribute("data-msg-id", id);
    article.setAttribute("data-can-edit", msg.can_edit ? "1" : "0");
    article.setAttribute("data-edited", msg.is_edited ? "1" : "0");

    const header = document.createElement("header");
    const strong = document.createElement("strong");
    strong.textContent = msg.is_mine ? "شما" : (peerName || "کاربر");
    const time = document.createElement("time");
    time.textContent = msg.created_at || "";
    header.appendChild(strong);
    header.appendChild(time);

    article.appendChild(header);
    fillChatBubbleBody(article, msg);
    if (msg.is_edited) setChatBubbleEdited(article, true);
    article.appendChild(buildChatBubbleActions(msg));
    return article;
  };

  const applyChatMessageUpdate = (bubble, msg) => {
    if (!(bubble instanceof HTMLElement) || !msg) return;
    const textEl = bubble.querySelector(".chat-bubble-text");
    if (textEl) textEl.textContent = msg.message || "";
    setChatBubbleEdited(bubble, !!msg.is_edited);
    bubble.setAttribute("data-can-edit", msg.can_edit ? "1" : "0");
    const actions = bubble.querySelector(".chat-bubble-actions");
    if (actions) {
      const hasEdit = actions.querySelector("[data-chat-edit]");
      if (msg.can_edit && !hasEdit) {
        actions.appendChild(buildChatBubbleActions(msg).querySelector("[data-chat-edit]"));
      } else if (!msg.can_edit && hasEdit) {
        hasEdit.remove();
      }
    }
  };

  const appendLiveBubble = (root, msg, peerName) => {
    if (!(root instanceof HTMLElement) || !msg) return;
    const id = String(msg.id || "");
    const existing = id ? root.querySelector('[data-msg-id="' + id + '"]') : null;
    if (existing instanceof HTMLElement) {
      applyChatMessageUpdate(existing, msg);
      return;
    }
    root.querySelector("[data-chat-empty]")?.remove();
    const latest = root.querySelector("#latest");
    const article = buildChatBubble(msg, peerName);
    if (latest) root.insertBefore(article, latest);
    else root.appendChild(article);
    root.scrollTop = root.scrollHeight;
  };

  const startChatBubbleEdit = (bubble, peerId, onSaved) => {
    if (!(bubble instanceof HTMLElement) || bubble.classList.contains("is-editing")) return;
    const textEl = bubble.querySelector(".chat-bubble-text");
    if (!(textEl instanceof HTMLElement)) return;
    const original = textEl.textContent || "";
    const keepWidth = Math.max(bubble.offsetWidth, 280);
    bubble.classList.add("is-editing");
    bubble.style.minWidth = keepWidth + "px";
    bubble.style.width = keepWidth + "px";
    const form = document.createElement("form");
    form.className = "chat-bubble-edit-form";
    const textarea = document.createElement("textarea");
    textarea.maxLength = 2000;
    textarea.required = true;
    textarea.value = original;
    textarea.rows = Math.max(4, Math.min(12, (original.match(/\n/g) || []).length + 3));
    const actions = document.createElement("div");
    actions.className = "chat-bubble-edit-actions";
    const saveBtn = document.createElement("button");
    saveBtn.type = "submit";
    saveBtn.className = "btn btn-primary btn-sm";
    saveBtn.textContent = "ذخیره";
    const cancelBtn = document.createElement("button");
    cancelBtn.type = "button";
    cancelBtn.className = "btn btn-ghost btn-sm";
    cancelBtn.textContent = "انصراف";
    actions.appendChild(saveBtn);
    actions.appendChild(cancelBtn);
    form.appendChild(textarea);
    form.appendChild(actions);
    textEl.replaceWith(form);
    textarea.style.height = "auto";
    textarea.style.height = Math.max(120, textarea.scrollHeight) + "px";
    textarea.focus();
    textarea.setSelectionRange(textarea.value.length, textarea.value.length);

    const finish = (savedMsg) => {
      bubble.classList.remove("is-editing");
      bubble.style.minWidth = "";
      bubble.style.width = "";
      const p = document.createElement("p");
      p.className = "chat-bubble-text";
      p.textContent = savedMsg ? (savedMsg.message || original) : original;
      form.replaceWith(p);
      if (savedMsg) {
        applyChatMessageUpdate(bubble, savedMsg);
        if (typeof onSaved === "function") onSaved(savedMsg);
      }
    };

    cancelBtn.addEventListener("click", () => finish(null));
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const message = String(textarea.value || "").trim();
      if (!message || !chatDockCfg.url || !chatDockCfg.nonce) return;
      saveBtn.disabled = true;
      try {
        const body = new FormData();
        body.set("action", "edit");
        body.set("message_id", String(bubble.getAttribute("data-msg-id") || ""));
        body.set("peer_id", String(peerId || ""));
        body.set("message", message);
        body.set("_wpnonce", chatDockCfg.nonce);
        const res = await fetch(chatDockCfg.url, {
          method: "POST",
          credentials: "same-origin",
          body,
          headers: { Accept: "application/json" },
        });
        const data = await res.json();
        if (!data || !data.ok) {
          window.alert((data && data.error) || "ویرایش ناموفق بود.");
          return;
        }
        finish(data.message || { message, is_edited: true, can_edit: true });
      } catch (_err) {
        window.alert("خطا در ویرایش پیام.");
      } finally {
        saveBtn.disabled = false;
      }
    });
  };

  if (liveThread instanceof HTMLElement && chatDockCfg.url && chatDockCfg.nonce) {
    let lastId = Number(liveThread.getAttribute("data-last-id") || "0");
    const peerId = Number(liveThread.getAttribute("data-peer-id") || "0");
    const peerName = liveThread.getAttribute("data-peer-name") || "";
    const locked = liveThread.getAttribute("data-locked") === "1";
    let editSince = new Date(Date.now() - 60000).toISOString().slice(0, 19).replace("T", " ");

    liveThread.addEventListener("click", async (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) return;
      const copyBtn = target.closest("[data-chat-copy]");
      if (copyBtn instanceof HTMLElement) {
        event.preventDefault();
        const bubble = copyBtn.closest("[data-msg-id]");
        const photoUrl = bubble instanceof HTMLElement ? (bubble.getAttribute("data-photo-url") || bubble.querySelector(".chat-bubble-text.is-photo img")?.getAttribute("src") || "") : "";
        const textEl = bubble instanceof HTMLElement ? bubble.querySelector(".chat-bubble-text:not(.is-photo)") : null;
        const ok = await copyChatText(photoUrl || (textEl ? textEl.textContent : ""));
        if (ok) {
          copyBtn.textContent = "کپی شد";
          window.setTimeout(() => { copyBtn.textContent = "کپی"; }, 1200);
        }
        return;
      }
      const shareBtn = target.closest("[data-chat-share]");
      if (shareBtn instanceof HTMLElement) {
        event.preventDefault();
        const bubble = shareBtn.closest("[data-msg-id]");
        const photoUrl = bubble instanceof HTMLElement ? (bubble.getAttribute("data-photo-url") || bubble.querySelector(".chat-bubble-text.is-photo img")?.getAttribute("src") || "") : "";
        const textEl = bubble instanceof HTMLElement ? bubble.querySelector(".chat-bubble-text:not(.is-photo)") : null;
        openChatShareSheet(photoUrl || (textEl ? textEl.textContent : ""));
        return;
      }
      const editBtn = target.closest("[data-chat-edit]");
      if (editBtn instanceof HTMLElement) {
        event.preventDefault();
        const bubble = editBtn.closest("[data-msg-id]");
        if (bubble instanceof HTMLElement) {
          startChatBubbleEdit(bubble, peerId, (savedMsg) => {
            editSince = new Date(Date.now() - 5000).toISOString().slice(0, 19).replace("T", " ");
            if (savedMsg && savedMsg.edited_at) editSince = savedMsg.edited_at;
          });
        }
      }
    });

    const livePoll = castingAdaptivePoll(
      async () => {
        if (!peerId || locked) return false;
        try {
          const url = new URL(chatDockCfg.url, window.location.origin);
          url.searchParams.set("action", "thread");
          url.searchParams.set("peer_id", String(peerId));
          url.searchParams.set("after_id", String(lastId));
          url.searchParams.set("edit_since", editSince);
          url.searchParams.set("poll", "1");
          url.searchParams.set("_wpnonce", chatDockCfg.nonce);
          const res = await fetch(url.toString(), {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
          });
          const data = await res.json();
          if (!data || !data.ok) return false;
          if (data.locked) {
            window.location.reload();
            return false;
          }
          const incoming = Array.isArray(data.messages) ? data.messages : [];
          incoming.forEach((msg) => appendLiveBubble(liveThread, msg, peerName));
          const updates = Array.isArray(data.updates) ? data.updates : [];
          updates.forEach((msg) => {
            const bubble = liveThread.querySelector('[data-msg-id="' + String(msg.id || "") + '"]');
            if (bubble instanceof HTMLElement) applyChatMessageUpdate(bubble, msg);
            if (msg.edited_at) editSince = msg.edited_at;
          });
          if (typeof data.last_id === "number" && data.last_id > lastId) {
            lastId = data.last_id;
            liveThread.setAttribute("data-last-id", String(lastId));
          }
          return incoming.length > 0 || updates.length > 0;
        } catch (_err) {
          return false;
        }
      },
      { minMs: 4000, maxMs: 20000, hiddenMs: 40000 }
    );

    const liveForm = document.querySelector("[data-chat-live-send]");
    if (liveForm instanceof HTMLFormElement) {
      liveForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        const textarea = liveForm.querySelector("#message, textarea[name='message']");
        const hiddenMsg = liveForm.querySelector("input[name='message']");
        const message = String(
          (hiddenMsg instanceof HTMLInputElement && hiddenMsg.value) ||
          (textarea instanceof HTMLTextAreaElement ? textarea.value : "") ||
          ""
        ).trim();
        if (!message || !peerId) return;
        const submitBtn = liveForm.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;
        try {
          const body = new FormData();
          body.set("action", "send");
          body.set("peer_id", String(peerId));
          body.set("message", message);
          body.set("_wpnonce", chatDockCfg.nonce);
          const res = await fetch(chatDockCfg.url, {
            method: "POST",
            credentials: "same-origin",
            body,
            headers: { Accept: "application/json" },
          });
          const data = await res.json();
          if (!data || !data.ok) {
            window.alert((data && data.error) || "ارسال ناموفق بود.");
            return;
          }
          if (textarea instanceof HTMLTextAreaElement && !(hiddenMsg instanceof HTMLInputElement)) {
            textarea.value = "";
            growChatCompose(textarea);
          }
          if (data.message) {
            appendLiveBubble(liveThread, data.message, peerName);
            const newId = Number(data.message.id || 0);
            if (newId > lastId) {
              lastId = newId;
              liveThread.setAttribute("data-last-id", String(lastId));
            }
          }
          livePoll.bump();
        } catch (_err) {
          window.alert("خطا در ارسال پیام.");
        } finally {
          if (submitBtn) submitBtn.disabled = false;
        }
      });
      const photoInput = liveForm.querySelector("[data-chat-photo]");
      if (photoInput instanceof HTMLInputElement) {
        photoInput.addEventListener("change", async () => {
          const file = photoInput.files && photoInput.files[0];
          if (!file || !peerId || !chatDockCfg.url || !chatDockCfg.nonce) return;
          const attach = photoInput.closest(".chat-compose-attach");
          if (attach instanceof HTMLElement) attach.classList.add("is-busy");
          try {
            const body = new FormData();
            body.set("action", "send_photo");
            body.set("peer_id", String(peerId));
            body.set("photo", file);
            body.set("_wpnonce", chatDockCfg.nonce);
            const res = await fetch(chatDockCfg.url, {
              method: "POST",
              credentials: "same-origin",
              body,
              headers: { Accept: "application/json" },
            });
            const data = await res.json();
            if (!data || !data.ok) {
              window.alert((data && data.error) || "ارسال عکس ناموفق بود.");
              return;
            }
            if (data.message) {
              appendLiveBubble(liveThread, data.message, peerName);
              const newId = Number(data.message.id || 0);
              if (newId > lastId) {
                lastId = newId;
                liveThread.setAttribute("data-last-id", String(lastId));
              }
            }
            livePoll.bump();
          } catch (_err) {
            window.alert("خطا در ارسال عکس.");
          } finally {
            photoInput.value = "";
            if (attach instanceof HTMLElement) attach.classList.remove("is-busy");
          }
        });
      }
      const shareComposeBtn = liveForm.querySelector("[data-chat-share-compose]");
      if (shareComposeBtn instanceof HTMLElement) {
        shareComposeBtn.addEventListener("click", (event) => {
          event.preventDefault();
          const textarea = liveForm.querySelector("#message, textarea[name='message']");
          const hiddenMsg = liveForm.querySelector("input[name='message']");
          const shareMessage = String(
            (hiddenMsg instanceof HTMLInputElement && hiddenMsg.value) ||
            (textarea instanceof HTMLTextAreaElement ? textarea.value : "") ||
            ""
          ).trim();
          openChatShareSheet(shareMessage);
        });
      }
    }
  }

  const inboxRoot = document.querySelector("[data-chat-inbox]");
  if (inboxRoot instanceof HTMLElement && chatDockCfg.url && chatDockCfg.nonce && !liveThread) {
    let fingerprint = inboxRoot.getAttribute("data-chat-inbox") || "";
    castingAdaptivePoll(
      async () => {
        try {
          const url = new URL(chatDockCfg.url, window.location.origin);
          url.searchParams.set("action", "inbox");
          url.searchParams.set("fp_only", "1");
          url.searchParams.set("_wpnonce", chatDockCfg.nonce);
          const res = await fetch(url.toString(), {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
          });
          const data = await res.json();
          if (data && data.ok && data.fingerprint && data.fingerprint !== fingerprint) {
            window.location.reload();
            return true;
          }
          return false;
        } catch (_err) {
          return false;
        }
      },
      { minMs: 8000, maxMs: 30000, hiddenMs: 60000 }
    );
  }

  // فیلتر مخاطبین در صفحه چت کامل (ادمین)
  const chatContactFilter = document.querySelector("[data-chat-contact-filter]");
  const chatContactSelect = document.querySelector("[data-chat-contact-select]");
  if (chatContactFilter instanceof HTMLInputElement && chatContactSelect instanceof HTMLSelectElement) {
    chatContactFilter.addEventListener("input", () => {
      const q = (chatContactFilter.value || "").trim().toLowerCase();
      Array.from(chatContactSelect.options).forEach((opt) => {
        if (!opt.value) {
          opt.hidden = false;
          return;
        }
        const label = String(opt.getAttribute("data-contact-label") || opt.textContent || "").toLowerCase();
        opt.hidden = q !== "" && !label.includes(q);
      });
    });
  }

  const referralCodeText = (el) => {
    const raw = (el.getAttribute("data-copy") || el.textContent || "").trim();
    return raw.replace(/^معرف:\s*/u, "").trim();
  };

  const copyReferralCode = async (text) => {
    if (!text) return false;
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
        return true;
      }
    } catch (_err) {
      /* fallback below */
    }
    const ta = document.createElement("textarea");
    ta.value = text;
    ta.setAttribute("readonly", "");
    ta.style.position = "fixed";
    ta.style.insetInlineStart = "-9999px";
    document.body.appendChild(ta);
    ta.select();
    let ok = false;
    try {
      ok = document.execCommand("copy");
    } catch (_err) {
      ok = false;
    }
    ta.remove();
    return ok;
  };

  const showCopyToast = (message) => {
    let toast = document.querySelector("[data-copy-toast]");
    if (!toast) {
      toast = document.createElement("div");
      toast.className = "copy-toast";
      toast.setAttribute("data-copy-toast", "");
      toast.setAttribute("role", "status");
      toast.setAttribute("aria-live", "polite");
      const label = document.createElement("span");
      toast.appendChild(label);
      document.body.appendChild(toast);
    }
    const label = toast.querySelector("span");
    if (label) label.textContent = message;
    toast.classList.add("is-visible");
    window.clearTimeout(showCopyToast._timer);
    showCopyToast._timer = window.setTimeout(() => {
      toast.classList.remove("is-visible");
    }, 1800);
  };

  document.querySelectorAll(".referral-code").forEach((el) => {
    if (!el.hasAttribute("tabindex")) el.setAttribute("tabindex", "0");
    if (!el.hasAttribute("role")) el.setAttribute("role", "button");
    if (!el.getAttribute("title")) el.setAttribute("title", "برای کپی کلیک کنید");
    el.setAttribute("aria-label", "کپی کد معرفی");
  });

  document.addEventListener("click", async (event) => {
    const el = event.target.closest(".referral-code");
    if (!el) return;
    event.preventDefault();
    const code = referralCodeText(el);
    if (!code) return;
    const ok = await copyReferralCode(code);
    showCopyToast(ok ? "کد کپی شد" : "کپی نشد. دوباره تلاش کنید.");
  });

  document.addEventListener("keydown", async (event) => {
    if (event.key !== "Enter" && event.key !== " ") return;
    const el = event.target.closest?.(".referral-code");
    if (!el) return;
    event.preventDefault();
    const code = referralCodeText(el);
    if (!code) return;
    const ok = await copyReferralCode(code);
    showCopyToast(ok ? "کد کپی شد" : "کپی نشد. دوباره تلاش کنید.");
  });

  let imageZoomEl = null;
  let imageZoomImg = null;
  const closeImageZoom = () => {
    if (!imageZoomEl) return;
    imageZoomEl.classList.remove("is-open");
    document.body.style.overflow = "";
    window.setTimeout(() => {
      if (imageZoomImg) {
        imageZoomImg.removeAttribute("src");
      }
    }, 200);
  };
  const openImageZoom = (src, alt) => {
    if (!src) return;
    if (!imageZoomEl) {
      imageZoomEl = document.createElement("button");
      imageZoomEl.type = "button";
      imageZoomEl.className = "image-zoom-lightbox";
      imageZoomEl.setAttribute("aria-label", "بستن تصویر");
      imageZoomImg = document.createElement("img");
      imageZoomImg.draggable = false;
      imageZoomEl.appendChild(imageZoomImg);
      imageZoomEl.addEventListener("click", closeImageZoom);
      document.body.appendChild(imageZoomEl);
    }
    imageZoomImg.src = src;
    imageZoomImg.alt = alt || "";
    imageZoomEl.classList.add("is-open");
    document.body.style.overflow = "hidden";
  };
  document.addEventListener("click", (event) => {
    const trigger = event.target.closest("[data-image-zoom]");
    if (!trigger) return;
    event.preventDefault();
    openImageZoom(
      trigger.getAttribute("data-image-zoom"),
      trigger.closest(".ad-poster-zoom-wrap")?.querySelector("img")?.alt || ""
    );
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") closeImageZoom();
  });

  document.addEventListener("change", (event) => {
    const input = event.target.closest?.("[data-file-pick]");
    if (!input) return;
    const wrap = input.closest(".file-pick");
    const nameEl = wrap?.querySelector("[data-file-pick-name]");
    if (nameEl) {
      nameEl.textContent = input.files && input.files[0] ? input.files[0].name : "فایلی انتخاب نشده";
    }
  });

  document.querySelectorAll("[data-reject-reason-form]").forEach((form) => {
    const select = form.querySelector("[data-reject-reason-select]");
    const other = form.querySelector("[data-reject-reason-other]");
    if (!select || !other) return;

    const syncOther = () => {
      const isOther = select.value === "other";
      other.hidden = !isOther;
      other.disabled = !isOther;
      other.required = isOther;
      if (!isOther) other.value = "";
    };

    select.addEventListener("change", syncOther);
    syncOther();
  });

  document.querySelectorAll("[data-grant-premium-confirm]").forEach((box) => {
    const check = box.querySelector("[data-grant-confirm-check]");
    const hidden = box.querySelector("[data-grant-confirm-hidden]");
    const btn = box.querySelector("[data-grant-finalize-btn]");
    const form = box.querySelector("[data-grant-finalize-form]");
    const timer = box.querySelector("[data-grant-confirm-timer]");
    let remaining = Number.parseInt(box.getAttribute("data-grant-remaining") || "0", 10);
    if (!Number.isFinite(remaining) || remaining < 0) remaining = 0;

    const sync = () => {
      const ready = remaining <= 0 && !!(check && check.checked);
      if (hidden) hidden.value = check && check.checked ? "1" : "0";
      if (btn) btn.disabled = !ready;
      if (timer) {
        if (remaining > 0) {
          timer.innerHTML =
            'لطفاً <strong data-grant-seconds>' + remaining + "</strong> ثانیه صبر کنید…";
        } else if (check && !check.checked) {
          timer.textContent = "تیک اطمینان را بزنید، سپس تأیید نهایی را بزنید.";
        } else {
          timer.textContent = "می‌توانید تأیید نهایی را بزنید.";
        }
      }
    };

    check?.addEventListener("change", sync);
    form?.addEventListener("submit", (event) => {
      sync();
      if (remaining > 0 || !(check && check.checked)) {
        event.preventDefault();
      }
    });
    sync();

    if (remaining > 0) {
      const tick = window.setInterval(() => {
        remaining = Math.max(0, remaining - 1);
        box.setAttribute("data-grant-remaining", String(remaining));
        sync();
        if (remaining <= 0) window.clearInterval(tick);
      }, 1000);
    }
  });

  document.querySelectorAll("[data-undo-until]").forEach((el) => {
    const until = Number(el.getAttribute("data-undo-until") || 0);
    const remainEl = el.querySelector("[data-undo-remain]");
    if (!until || !remainEl) return;
    const pad = (n) => String(n).padStart(2, "0");
    const tick = () => {
      const left = Math.max(0, until - Math.floor(Date.now() / 1000));
      remainEl.textContent = Math.floor(left / 60) + ":" + pad(left % 60);
      if (left <= 0) {
        remainEl.textContent = "۰:۰۰";
        el.textContent = "مهلت اصلاح تمام شد. پوستر برای تأیید ادمین ارسال شد.";
        el.closest("article")?.querySelector("[data-undo-actions]")?.remove();
      }
    };
    tick();
    const timer = window.setInterval(() => {
      tick();
      if (until - Math.floor(Date.now() / 1000) <= 0) {
        window.clearInterval(timer);
      }
    }, 1000);
  });
})();
