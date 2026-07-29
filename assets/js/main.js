(() => {
  const THEME_KEY = "casting_theme";

  const castingIsPwaShell = () =>
    window.matchMedia("(display-mode: standalone)").matches
    || window.matchMedia("(display-mode: fullscreen)").matches
    || window.matchMedia("(display-mode: minimal-ui)").matches
    || Boolean(window.navigator.standalone);

  const castingIsSameOriginHref = (href) => {
    try {
      return new URL(href, window.location.href).origin === window.location.origin;
    } catch (err) {
      return false;
    }
  };

  // در PWA لینک‌های داخلی همان اپ باز شوند؛ در مرورگر target=_blank می‌ماند
  if (castingIsPwaShell()) {
    document.documentElement.classList.add("is-pwa");
    document.addEventListener(
      "click",
      (e) => {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
          return;
        }
        const a = e.target.closest("a[href]");
        if (!a || a.getAttribute("target") !== "_blank") return;
        const href = a.getAttribute("href") || "";
        if (!href || href.startsWith("#") || href.startsWith("mailto:") || href.startsWith("tel:")) return;
        if (!castingIsSameOriginHref(href)) return;
        e.preventDefault();
        window.location.assign(a.href);
      },
      true
    );
  }

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

  let storedTheme = "night";
  try {
    storedTheme = localStorage.getItem(THEME_KEY) || "night";
  } catch (err) {}
  applyTheme(storedTheme === "day" ? "day" : "night");

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
    const resultsAnchorTop = document.querySelector('[data-member-search-results-anchor="top"]');
    const resultsAnchorBottom = document.querySelector('[data-member-search-results-anchor="bottom"]');

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

    const placeSearchResults = (active) => {
      const results = document.querySelector("[data-member-search-results]");
      if (!results || !resultsAnchorTop || !resultsAnchorBottom) return;
      const target = active ? resultsAnchorTop : resultsAnchorBottom;
      if (results.parentElement !== target) {
        target.appendChild(results);
      }
      resultsAnchorTop.hidden = !active;
      resultsAnchorBottom.hidden = active;
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
          placeSearchResults(formHasActiveSearch());
          const query = buildFormQuery(false);
          window.history.replaceState({}, "", query ? `search-users.php?${query}` : "search-users.php");
        } catch (err) {
          if (err?.name !== "AbortError") {
            /* ignore */
          }
        } finally {
          resultsEl.classList.remove("is-loading");
        }
      }, 280);
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

    memberSearchForm.addEventListener("change", refreshResults);
    memberSearchForm.addEventListener("input", (e) => {
      const el = e.target;
      if (!(el instanceof HTMLElement)) return;
      if (el.matches("input:not([type=hidden]):not([type=checkbox]):not([type=radio]), textarea")) {
        refreshResults();
      }
    });
    memberSearchForm.addEventListener("submit", (e) => {
      e.preventDefault();
      refreshResults();
    });
    placeSearchResults(formHasActiveSearch());
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
          msg.hidden = false;
          field.classList.add("is-invalid");
          pass2.setAttribute("aria-invalid", "true");
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

      form.querySelectorAll("[data-talent-required-mark]").forEach((mark) => {
        mark.hidden = hideTalentFields;
      });

      const nonTalentPhotoHint = form.querySelector("[data-non-talent-photo-hint]");
      if (nonTalentPhotoHint) {
        nonTalentPhotoHint.hidden = !hideTalentFields;
      }

      form.querySelectorAll("#profile-photos input[type='file']").forEach((input) => {
        const isPrimary = input.hasAttribute("data-portrait-primary");
        if (hideTalentFields) {
          input.required = isPrimary;
        } else {
          input.required = true;
        }
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
        if (form.dataset.registerInvalidHandled === "1") return;
        form.dataset.registerInvalidHandled = "1";
        window.setTimeout(() => {
          delete form.dataset.registerInvalidHandled;
        }, 300);
        focusRegisterField(first);
      },
      true
    );

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
      portraitLightboxImg = document.createElement("img");
      portraitLightboxEl.appendChild(portraitLightboxImg);
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
      const data = await res.json();
      if (!data?.ok || !data.html) {
        memberPreviewBody.innerHTML = '<p class="empty-state">بارگذاری پروفایل ناموفق بود.</p>';
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
        if (action === "favorite") {
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

  const promoSlider = document.querySelector("[data-promo-slider]");
  if (promoSlider) {
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

    showSlide(index);
    startTimer();
  }

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
      btn.classList.toggle("is-on", on);
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
        errBox.hidden = ok;
        errBox.textContent = ok ? "" : text;
      }
      if (okBox) {
        okBox.hidden = !ok;
        okBox.textContent = ok ? text : "";
        if (ok) {
          window.setTimeout(() => {
            okBox.hidden = true;
          }, 1800);
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
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: body.toString(),
            credentials: "same-origin",
          });
          const data = await res.json();
          if (!data || !data.ok) {
            flash(false, (data && data.error) || "ذخیره ناموفق بود.");
            return;
          }
          if (field === "enabled") {
            const enabled = !!data.enabled;
            setToggleUi(btn, enabled, "enabled");
            const reqBtn = row.querySelector('[data-msg-toggle="require_project"]');
            if (reqBtn) {
              reqBtn.disabled = !enabled;
              if (!enabled) {
                setToggleUi(reqBtn, false, "require_project");
              }
            }
            flash(true, enabled ? "دسترسی روشن شد." : "دسترسی خاموش شد.");
          } else {
            setToggleUi(btn, !!data.require_project, "require_project");
            flash(true, data.require_project ? "محدودیت پروژه فعال شد." : "محدودیت پروژه برداشته شد.");
          }
        } catch (e) {
          flash(false, "خطا در ارتباط با سرور.");
        } finally {
          btn.removeAttribute("aria-busy");
        }
      });
    });
  }
})();
