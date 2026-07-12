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
})();
