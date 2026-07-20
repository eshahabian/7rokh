(() => {
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
      if (!jy || !jm || !jd) {
        ageOut.value = "";
        return;
      }
      const { gy, gm, gd } = jalaliToGregorian(jy, jm, jd);
      const today = new Date();
      let age = today.getFullYear() - gy;
      const md = today.getMonth() + 1 - gm;
      if (md < 0 || (md === 0 && today.getDate() < gd)) age -= 1;
      ageOut.value = age >= 0 ? age + " سال" : "";
      const hiddenAge = document.querySelector('input[name="age"]');
      if (hiddenAge) hiddenAge.value = String(age);
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

    const fillSelect = (select, items, placeholder, selected) => {
      if (!select) return;
      const keep = selected || "";
      select.innerHTML = "";
      const first = document.createElement("option");
      first.value = "";
      first.textContent = placeholder;
      select.appendChild(first);
      (items || []).forEach((name) => {
        const opt = document.createElement("option");
        opt.value = name;
        opt.textContent = name;
        if (keep === name) opt.selected = true;
        select.appendChild(opt);
      });
    };

    const syncCities = (keepCity) => {
      const province = provinceSel?.value || "";
      const cities = province ? map.cities?.[province] || [] : [];
      if (!province) {
        citySel.disabled = true;
        fillSelect(citySel, [], "اول استان را انتخاب کنید", "");
      } else {
        citySel.disabled = false;
        fillSelect(citySel, cities, "انتخاب شهر…", keepCity);
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
      specSel.disabled = false;
      const placeholder = document.createElement("option");
      placeholder.value = "";
      placeholder.textContent = "انتخاب تخصص…";
      specSel.appendChild(placeholder);
      Object.keys(map[cat]).forEach((key) => {
        const opt = document.createElement("option");
        opt.value = key;
        opt.textContent = map[cat][key];
        if (prev === key) opt.selected = true;
        specSel.appendChild(opt);
      });
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
      placeholder.textContent = "انتخاب کنید";
      specSel.appendChild(placeholder);
      Object.keys(map[cat]).forEach((key) => {
        const opt = document.createElement("option");
        opt.value = key;
        opt.textContent = map[cat][key];
        if (prev === key) opt.selected = true;
        specSel.appendChild(opt);
      });
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
  const nameSearchBox = document.querySelector("[data-name-search]");
  const nameSearchInput = document.querySelector("[data-name-search-input]");
  const nameSearchSuggest = document.querySelector("[data-name-search-suggest]");

  if (memberSearchForm && memberSearchResults && nameSearchInput) {
    let suggestTimer = 0;
    let resultsTimer = 0;
    let suggestAbort = null;
    let resultsAbort = null;

    const buildFormQuery = () => {
      const params = new URLSearchParams(new FormData(memberSearchForm));
      params.set("ajax", "1");
      params.delete("page");
      return params.toString();
    };

    const refreshResults = () => {
      window.clearTimeout(resultsTimer);
      resultsAbort?.abort();
      resultsTimer = window.setTimeout(async () => {
        const controller = new AbortController();
        resultsAbort = controller;
        memberSearchResults.classList.add("is-loading");
        try {
          const res = await fetch(`${memberSearchForm.getAttribute("action") || "search-users.php"}?${buildFormQuery()}`, {
            signal: controller.signal,
            headers: { "X-Requested-With": "XMLHttpRequest" },
          });
          if (!res.ok) return;
          memberSearchResults.innerHTML = await res.text();
        } catch (err) {
          if (err?.name !== "AbortError") {
            /* ignore network errors during live typing */
          }
        } finally {
          memberSearchResults.classList.remove("is-loading");
        }
      }, 400);
    };

    const hideSuggest = () => {
      if (!nameSearchSuggest) return;
      nameSearchSuggest.hidden = true;
      nameSearchSuggest.innerHTML = "";
      nameSearchBox?.classList.remove("is-suggest-open");
    };

    const renderSuggest = (items) => {
      if (!nameSearchSuggest) return;
      nameSearchSuggest.innerHTML = "";
      if (!items.length) {
        hideSuggest();
        return;
      }
      items.forEach((item) => {
        const li = document.createElement("li");
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "name-search-suggest-item";
        btn.dataset.name = item.name || "";
        btn.innerHTML = `<strong>${item.name || ""}</strong><span class="meta">${item.login || ""}</span>`;
        li.appendChild(btn);
        nameSearchSuggest.appendChild(li);
      });
      nameSearchSuggest.hidden = false;
      nameSearchBox?.classList.add("is-suggest-open");
    };

    const fetchSuggest = () => {
      window.clearTimeout(suggestTimer);
      suggestAbort?.abort();
      const q = (nameSearchInput.value || "").trim();
      if (q.length < 2) {
        hideSuggest();
        return;
      }
      suggestTimer = window.setTimeout(async () => {
        const controller = new AbortController();
        suggestAbort = controller;
        try {
          const params = new URLSearchParams({ q });
          const res = await fetch(`search-members-suggest.php?${params.toString()}`, {
            signal: controller.signal,
            headers: { "X-Requested-With": "XMLHttpRequest" },
          });
          if (!res.ok) return;
          const data = await res.json();
          renderSuggest(Array.isArray(data.items) ? data.items : []);
        } catch (err) {
          if (err?.name !== "AbortError") hideSuggest();
        }
      }, 250);
    };

    nameSearchInput.addEventListener("input", () => {
      fetchSuggest();
      refreshResults();
    });

    nameSearchSuggest?.addEventListener("click", (e) => {
      const btn = e.target.closest(".name-search-suggest-item");
      if (!btn) return;
      nameSearchInput.value = btn.dataset.name || nameSearchInput.value;
      hideSuggest();
      refreshResults();
    });

    document.addEventListener("click", (e) => {
      if (!nameSearchBox?.contains(e.target)) hideSuggest();
    });

    memberSearchForm.addEventListener("change", refreshResults);
    memberSearchForm.addEventListener("submit", () => hideSuggest());
  }
})();
