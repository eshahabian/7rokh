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
    return /Capacitor/i.test(ua) || /; wv\)/i.test(ua);
  };

  const castingIsAppShell = () => castingIsPwaShell() || castingIsNativeAppShell();

  const castingIsSiteHref = (href) => {
    try {
      const u = new URL(href, window.location.href);
      if (u.protocol !== "http:" && u.protocol !== "https:") return false;
      if (u.origin === window.location.origin) return true;
      const host = u.hostname.replace(/^www\./i, "").toLowerCase();
      return host === "7rokh.ir" || host.endsWith(".7rokh.ir");
    } catch (err) {
      return false;
    }
  };

  if (castingIsAppShell()) {
    document.documentElement.classList.add("is-pwa");
    if (castingIsNativeAppShell()) {
      document.documentElement.classList.add("is-native-app");
    }
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

  const birthBox = document.querySelector("[data-jalali-birth]");
  const ageOut = document.querySelector("[data-age-output]");
  if (birthBox && ageOut) {
    const yearEl = birthBox.querySelector("[data-jalali-year]");
    const monthEl = birthBox.querySelector("[data-jalali-month]");
    const dayEl = birthBox.querySelector("[data-jalali-day]");

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
  }

  const thread = document.getElementById("chat-thread");
  if (thread) {
    thread.scrollTop = thread.scrollHeight;
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
        const input = rows[0]?.querySelector('input[type="text"]');
        if (input) input.value = "";
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

  document.querySelectorAll("[data-location-fields]").forEach((box) => {
    let map = { cities: {} };
    try {
      map = JSON.parse(box.getAttribute("data-location-map") || "{}");
    } catch (err) {
      map = { cities: {} };
    }
    const provinceSel = box.querySelector("[data-location-province]");
    const citySel = box.querySelector("[data-location-city]");

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
    };

    const allowCityAll = box.hasAttribute("data-location-city-all");

    const syncCities = (keepCity) => {
      const province = provinceSel?.value || "";
      const cities = province ? map.cities?.[province] || [] : [];
      if (!province) {
        citySel.disabled = true;
        fillSelect(citySel, [], "اول استان را انتخاب کنید", "", false);
      } else {
        citySel.disabled = false;
        fillSelect(citySel, cities, "انتخاب شهر…", keepCity, allowCityAll);
      }
    };

    provinceSel?.addEventListener("change", () => syncCities(""));
    syncCities(citySel?.value || "");
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
        return;
      }
      suggestTimer = window.setTimeout(async () => {
        const controller = new AbortController();
        suggestAbort = controller;
        try {
          const params = new URLSearchParams({ q: query });
          const res = await fetch(`search-members-suggest.php?${params.toString()}`, {
            signal: controller.signal,
            headers: { "X-Requested-With": "XMLHttpRequest" },
          });
          if (!res.ok) return;
          const data = await res.json();
          const items = Array.isArray(data.items) ? data.items : [];
          predictedFull = pickPrediction(items, query);
        } catch (err) {
          if (err?.name !== "AbortError") clearPrediction();
        }
      }, 200);
    };

    const acceptPrediction = () => {
      if (!nameSearchInput || !predictedFull) return false;
      nameSearchInput.value = predictedFull;
      clearPrediction();
      syncClearButton();
      refreshResults();
      return true;
    };

    if (nameSearchInput) {
      nameSearchField?.addEventListener("click", (e) => {
        if (e.target.closest("[data-name-search-clear]")) return;
        nameSearchInput.focus();
      });

      nameSearchClear?.addEventListener("click", (e) => {
        e.preventDefault();
        e.stopPropagation();
        nameSearchInput.value = "";
        clearPrediction();
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
        if (!predictedFull) return;
        if (e.key === "Tab" || e.key === "ArrowRight") {
          const atEnd = nameSearchInput.selectionStart === nameSearchInput.value.length
            && nameSearchInput.selectionEnd === nameSearchInput.value.length;
          if (e.key === "Tab" || atEnd) {
            e.preventDefault();
            acceptPrediction();
          }
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

  if ("serviceWorker" in navigator && window.CASTING_PWA && window.CASTING_PWA.swUrl) {
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
      }
    } catch (_err) {
      memberPreviewBody.innerHTML = '<p class="empty-state">خطا در بارگذاری پروفایل.</p>';
    } finally {
      memberPreviewLoading = false;
      memberPreviewPanel?.scrollTo(0, 0);
    }
  };

  const postMemberPreviewAction = async (memberId, action) => {
    const body = new FormData();
    body.append("_wpnonce", memberPreviewNonce);
    body.append("member_id", String(memberId));
    body.append("action", action);
    const res = await fetch("member-preview.php", {
      method: "POST",
      credentials: "same-origin",
      body,
      headers: { Accept: "application/json" },
    });
    return res.json();
  };

  document.addEventListener("click", async (event) => {
    const actionBtn = event.target.closest("[data-member-preview-action]");
    if (actionBtn && memberPreviewBody?.contains(actionBtn)) {
      event.preventDefault();
      event.stopPropagation();
      const memberId = actionBtn.getAttribute("data-member-id");
      const action = actionBtn.getAttribute("data-member-preview-action");
      if (!memberId || !action || actionBtn.disabled) return;
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

  // Media like / comment
  const mediaEngageCfg = () => window.CASTING_MEDIA_ENGAGE || {};

  const buildCommentLi = (comment) => {
    const li = document.createElement("li");
    const strong = document.createElement("strong");
    strong.textContent = comment?.name || "کاربر";
    const span = document.createElement("span");
    span.textContent = comment?.body || "";
    li.appendChild(strong);
    li.appendChild(document.createTextNode(" "));
    li.appendChild(span);
    return li;
  };

  const refreshCommentPreview = (wrap) => {
    if (!wrap) return;
    const preview = wrap.querySelector("[data-media-comments]");
    const full = wrap.querySelector("[data-media-comments-full]");
    if (!preview || !full) return;
    const items = Array.from(full.querySelectorAll("li"));
    preview.innerHTML = "";
    items.slice(0, 2).forEach((li) => preview.appendChild(li.cloneNode(true)));
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

  let postLightboxSource = null;
  let postLightboxEngage = null;
  const postLightbox = document.querySelector("[data-post-lightbox]");
  if (postLightbox && postLightbox.parentElement !== document.body) {
    document.body.appendChild(postLightbox);
  }
  const postLightboxPanel = postLightbox?.querySelector(".post-lightbox-panel");
  const postLightboxBody = postLightbox?.querySelector("[data-post-lightbox-body]");

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
    document.body.style.overflow = "";
  };

  const openPostLightbox = (trigger) => {
    if (!postLightbox || !postLightboxBody) return;
    const root = trigger.closest(".ig-profile-cell, .profile-media-item, .home-feed-post");
    if (!root) return;
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
          const clone = cloneSource.cloneNode(true);
          if (clone.tagName === "VIDEO") {
            clone.setAttribute("controlslist", "nodownload noplaybackrate noremoteplayback");
            clone.setAttribute("disablepictureinpicture", "");
          }
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
    document.body.style.overflow = "hidden";
    postLightboxPanel?.scrollTo(0, 0);
  };

  document.addEventListener("click", (event) => {
    if (event.target.closest(".ig-profile-cell-delete, .ig-profile-pending-delete")) {
      return;
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
    if (event.key === "Escape") closePostLightbox();
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
    if (event.target.closest("[data-media-protect], .media-protect, .portrait-lightbox, .post-lightbox-media")) {
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
    document.documentElement.classList.toggle(
      "media-protect-obscured",
      document.hidden || document.visibilityState === "hidden"
    );
  });
  window.addEventListener("blur", () => {
    if (window.CASTING_MEDIA_PROTECT?.isMobile) {
      document.documentElement.classList.add("media-protect-obscured");
    }
  });
  window.addEventListener("focus", () => {
    document.documentElement.classList.remove("media-protect-obscured");
  });

  const paintProtectedFrame = (root, video, canvas, ctx) => {
    try {
      const rect = root.getBoundingClientRect();
      const cssW = Math.max(1, Math.round(rect.width * (window.devicePixelRatio || 1)));
      const cssH = Math.max(1, Math.round(rect.height * (window.devicePixelRatio || 1)));
      if (canvas.width !== cssW || canvas.height !== cssH) {
        canvas.width = cssW;
        canvas.height = cssH;
      }
      ctx.fillStyle = "#000";
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      const vw = video.videoWidth || 0;
      const vh = video.videoHeight || 0;
      if (vw > 0 && vh > 0) {
        const scale = Math.max(canvas.width / vw, canvas.height / vh);
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
    }
  };

  const initProtectedVideo = (root) => {
    if (!root || root.dataset.videoReady === "1") return;
    const video = root.querySelector("video.media-protect-source, video");
    const canvas = root.querySelector("canvas.media-protect-canvas");
    if (!video || !canvas) return;
    root.dataset.videoReady = "1";
    const ctx = canvas.getContext("2d");
    if (!ctx) return;
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

  const bootProtectedVideos = (scope) => {
    (scope || document).querySelectorAll("[data-video-protect]").forEach(initProtectedVideo);
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
      const wrap = form.closest("[data-media-engage]");
      const full = wrap?.querySelector("[data-media-comments-full]");
      if (full && data.comment) {
        full.appendChild(buildCommentLi(data.comment));
      }
      if (postLightbox?.classList.contains("is-open") && wrap) {
        const preview = wrap.querySelector("[data-media-comments]");
        if (preview && full) preview.innerHTML = full.innerHTML;
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

  // خروج خودکار پس از ۵ دقیقه عدم فعالیت
  const sessionCfg = window.CASTING_SESSION;
  if (sessionCfg && sessionCfg.active) {
    const idleMs = Math.max(60, Number(sessionCfg.idleSeconds) || 300) * 1000;
    const pingUrl = String(sessionCfg.pingUrl || "");
    const logoutUrl = String(sessionCfg.logoutUrl || "logout.php?reason=idle");
    let idleTimer = 0;
    let lastPing = 0;
    let loggingOut = false;

    const doIdleLogout = () => {
      if (loggingOut) return;
      loggingOut = true;
      window.location.href = logoutUrl;
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

    const resetIdle = () => {
      if (loggingOut) return;
      window.clearTimeout(idleTimer);
      idleTimer = window.setTimeout(doIdleLogout, idleMs);
      pingSession();
    };

    ["pointerdown", "keydown", "scroll", "touchstart", "mousemove", "visibilitychange"].forEach((evt) => {
      document.addEventListener(
        evt,
        () => {
          if (evt === "visibilitychange" && document.visibilityState !== "visible") return;
          resetIdle();
        },
        { passive: true }
      );
    });
    resetIdle();
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
})();
