/**
 * ch.ipik.practicebooking v0.5.0 — booking-form.js
 * Formulaire public de prise de rendez-vous.
 */
(function () {
  'use strict';

  const state = {
    selectedTypeId:    null,
    requiresExisting:  false,
    requiresAccount:   false,
    duration:          60,
    selectedDatetime:  null,
    slots:             {},
    currentMonth:      new Date(),
    emailGateCleared:  false,
  };

  const cfg   = window.ipikBooking || {};
  const ajaxUrl = cfg.ajaxUrl || '';
  // Lire le nonce depuis le champ hidden (toujours frais)
  function getNonce() {
    const el = document.getElementById('ipik-nonce-value');
    return el ? el.value : (cfg.nonce || '');
  }
  const l10n = cfg.l10n || {};

  const el = {
    form:          document.getElementById('ipik-booking-form'),
    steps: {
      type:    document.getElementById('ipik-step-type'),
      slot:    document.getElementById('ipik-step-slot'),
      contact: document.getElementById('ipik-step-contact'),
      confirm: document.getElementById('ipik-step-confirm'),
    },
    typeCards:      document.querySelectorAll('.ipik-type-card'),
    calPrev:        document.getElementById('ipik-cal-prev'),
    calNext:        document.getElementById('ipik-cal-next'),
    calMonth:       document.getElementById('ipik-cal-month'),
    calGrid:        document.getElementById('ipik-cal-grid'),
    slotsContainer: document.getElementById('ipik-slots'),
    summary:        document.getElementById('ipik-summary'),
    summaryText:    document.querySelector('#ipik-summary .ipik-summary__text'),
    emailGate:      document.getElementById('ipik-email-gate'),
    emailCheck:     document.getElementById('ipik-email-check'),
    emailCheckBtn:  document.getElementById('ipik-check-email-btn'),
    emailGateResult:document.getElementById('ipik-email-gate-result'),
    contactForm:    document.getElementById('ipik-booking-form-data'),
    fieldTypeId:    document.getElementById('ipik-field-type-id'),
    fieldStart:     document.getElementById('ipik-field-start'),
    firstNameEl:    document.getElementById('ipik-first-name'),
    lastNameEl:     document.getElementById('ipik-last-name'),
    emailEl:        document.getElementById('ipik-email'),
    phoneEl:        document.getElementById('ipik-phone'),
    notesEl:        document.getElementById('ipik-notes'),
    formError:      document.getElementById('ipik-form-error'),
    submitBtn:      document.getElementById('ipik-submit-btn'),
    newBookingBtn:  document.getElementById('ipik-new-booking'),
    backBtns:       document.querySelectorAll('.ipik-back'),
  };

  if (!el.form) return;

  // Pré-remplir si utilisateur connecté avec contact CiviCRM
  if (cfg.currentUser) {
    var u = cfg.currentUser;
    if (el.firstNameEl && u.first_name) el.firstNameEl.value = u.first_name;
    if (el.lastNameEl  && u.last_name)  el.lastNameEl.value  = u.last_name;
    if (el.emailEl     && u.email)      el.emailEl.value     = u.email;
  }

  // -------------------------------------------------------------------------
  // Navigation
  // -------------------------------------------------------------------------
  function goTo(stepName) {
    Object.entries(el.steps).forEach(([name, node]) => {
      if (!node) return;
      const active = name === stepName;
      node.classList.toggle('ipik-step--hidden', !active);
      node.setAttribute('aria-hidden', String(!active));
    });
    const activeStep = el.steps[stepName];
    if (activeStep) {
      const title = activeStep.querySelector('.ipik-step__title');
      if (title) { title.setAttribute('tabindex', '-1'); title.focus(); }
    }
  }

  // -------------------------------------------------------------------------
  // Étape 1 : Type de RDV
  // -------------------------------------------------------------------------
  el.typeCards.forEach(card => {
    card.addEventListener('click', () => {
      state.selectedTypeId   = parseInt(card.dataset.typeId, 10);
      state.requiresExisting = card.dataset.requiresExisting === '1';
      state.requiresAccount  = card.dataset.requiresAccount  === '1';
      state.duration         = parseInt(card.dataset.duration, 10) || 60;
      state.slots            = {};
      state.emailGateCleared = false;
      el.typeCards.forEach(c => c.classList.remove('ipik-type-card--selected'));
      card.classList.add('ipik-type-card--selected');
      loadSlots();
      goTo('slot');
    });
  });

  // Type préréglé par le shortcode : l'étape 1 est sautée et ne doit plus
  // être atteignable, ni par « Retour » ni par « Nouvelle réservation ».
  let presetCard = null;
  if (cfg.preType) {
    presetCard = document.querySelector(`.ipik-type-card[data-type-id="${cfg.preType}"]`);
    if (presetCard) {
      el.backBtns.forEach(btn => {
        if (btn.dataset.target === 'type') {
          btn.style.display = 'none';
          btn.setAttribute('aria-hidden', 'true');
        }
      });
      setTimeout(() => presetCard.click(), 50);
    }
  }

  // -------------------------------------------------------------------------
  // Étape 2 : Calendrier + créneaux
  // -------------------------------------------------------------------------
  function loadSlots() {
    const now  = new Date();
    const from = new Date(Math.max(now, new Date(state.currentMonth.getFullYear(), state.currentMonth.getMonth(), 1)));
    const to   = new Date(state.currentMonth.getFullYear(), state.currentMonth.getMonth() + 2, 0);
    renderCalendarShell();
    setSlotsHint(l10n.loading);
    ajax('get_slots', {
      type_id:   state.selectedTypeId,
      date_from: formatDate(from),
      date_to:   formatDate(to),
    }).then(data => {
      state.slots = data.slots || {};
      renderCalendar();
      var nb = Object.keys(state.slots).length;
      setSlotsHint(nb ? l10n.selectDate : l10n.noSlots);
    }).catch(() => setSlotsHint(l10n.errorGeneric));
  }

  function renderCalendarShell() {
    const months = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
    el.calMonth.textContent = `${months[state.currentMonth.getMonth()]} ${state.currentMonth.getFullYear()}`;
    el.calGrid.innerHTML = '';
    ['Lu','Ma','Me','Je','Ve','Sa','Di'].forEach(d => {
      const h = document.createElement('div');
      h.className = 'ipik-cal__header';
      h.textContent = d;
      el.calGrid.appendChild(h);
    });
  }

  function renderCalendar() {
    renderCalendarShell();
    const year    = state.currentMonth.getFullYear();
    const month   = state.currentMonth.getMonth();
    const firstDay = new Date(year, month, 1);
    const lastDay  = new Date(year, month + 1, 0);
    const today    = new Date(); today.setHours(0,0,0,0);
    let offset = (firstDay.getDay() + 6) % 7;
    for (let i = 0; i < offset; i++) {
      const e = document.createElement('div');
      e.className = 'ipik-cal__day ipik-cal__day--empty';
      el.calGrid.appendChild(e);
    }
    for (let d = 1; d <= lastDay.getDate(); d++) {
      const date    = new Date(year, month, d);
      const dateStr = formatDate(date);
      const hasSlots = !!(state.slots[dateStr] && state.slots[dateStr].length);
      const isPast  = date < today;
      const cell    = document.createElement('button');
      cell.type = 'button';
      cell.className = 'ipik-cal__day';
      cell.textContent = d;
      cell.setAttribute('aria-label', dateStr);
      if (isPast || !hasSlots) {
        cell.classList.add('ipik-cal__day--unavailable');
        cell.disabled = true;
      } else {
        cell.classList.add('ipik-cal__day--available');
        cell.addEventListener('click', () => selectDate(dateStr, cell));
      }
      el.calGrid.appendChild(cell);
    }
  }

  function selectDate(dateStr, cell) {
    document.querySelectorAll('.ipik-cal__day--selected').forEach(c => c.classList.remove('ipik-cal__day--selected'));
    cell.classList.add('ipik-cal__day--selected');
    renderSlots(dateStr);
  }

  function renderSlots(dateStr) {
    const daySlots = state.slots[dateStr] || [];
    el.slotsContainer.innerHTML = '';
    if (!daySlots.length) { setSlotsHint(l10n.noSlots); return; }
    const grid = document.createElement('div');
    grid.className = 'ipik-slots__grid';
    grid.setAttribute('role', 'list');
    daySlots.forEach(slot => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'ipik-slot-btn';
      btn.textContent = slot.time;
      btn.setAttribute('role', 'listitem');
      btn.addEventListener('click', () => selectSlot(slot.datetime, slot.time, dateStr, btn));
      grid.appendChild(btn);
    });
    el.slotsContainer.appendChild(grid);
  }

  function selectSlot(datetime, time, dateStr, btn) {
    document.querySelectorAll('.ipik-slot-btn--selected').forEach(b => b.classList.remove('ipik-slot-btn--selected'));
    btn.classList.add('ipik-slot-btn--selected');
    state.selectedDatetime = datetime;
    if (el.summaryText) el.summaryText.textContent = `${formatDisplay(dateStr)} à ${time}`;
    if (el.fieldTypeId) el.fieldTypeId.value = state.selectedTypeId;
    if (el.fieldStart)  el.fieldStart.value  = datetime;
    if (state.requiresExisting && !state.emailGateCleared) {
      if (el.emailGate)    el.emailGate.style.display    = '';
      if (el.contactForm)  el.contactForm.style.display  = 'none';
    } else {
      if (el.emailGate)    el.emailGate.style.display    = 'none';
      if (el.contactForm)  el.contactForm.style.display  = '';
    }
    goTo('contact');
  }

  el.calPrev?.addEventListener('click', () => { state.currentMonth.setMonth(state.currentMonth.getMonth() - 1); loadSlots(); });
  el.calNext?.addEventListener('click', () => { state.currentMonth.setMonth(state.currentMonth.getMonth() + 1); loadSlots(); });

  // -------------------------------------------------------------------------
  // Étape 3 : Contact
  // -------------------------------------------------------------------------
  el.emailCheckBtn?.addEventListener('click', () => {
    const email = el.emailCheck?.value.trim();
    if (!email) { showGateMessage(l10n.emailRequired, 'error'); return; }
    el.emailCheckBtn.disabled = true;
    showGateMessage(l10n.loading, 'info');
    ajax('check_email', { email, type_id: state.selectedTypeId }).then(data => {
      if (data.allowed) {
        if (el.emailEl) el.emailEl.value = email;
        state.emailGateCleared = true;
        if (el.emailGate)   el.emailGate.style.display   = 'none';
        if (el.contactForm) el.contactForm.style.display = '';
      } else {
        showGateMessage(l10n.contactNotFound, 'error');
      }
    }).catch(() => showGateMessage(l10n.errorGeneric, 'error'))
      .finally(() => { if (el.emailCheckBtn) el.emailCheckBtn.disabled = false; });
  });

  el.contactForm?.addEventListener('submit', e => {
    e.preventDefault();
    hideFormError();
    const data = {
      appointment_type_id: state.selectedTypeId,
      start_datetime:      state.selectedDatetime,
      first_name:          el.firstNameEl?.value.trim(),
      last_name:           el.lastNameEl?.value.trim(),
      email:               el.emailEl?.value.trim(),
      phone:               el.phoneEl?.value.trim(),
      notes:               el.notesEl?.value.trim(),
    };
    if (!data.first_name || !data.last_name || !data.email) {
      showFormError('Veuillez remplir tous les champs obligatoires.');
      return;
    }
    setSubmitting(true);
    ajax('submit_booking', data)
      .then(() => goTo('confirm'))
      .catch(err => showFormError(err.message || l10n.errorGeneric))
      .finally(() => setSubmitting(false));
  });

  el.backBtns.forEach(btn => btn.addEventListener('click', () => goTo(btn.dataset.target)));
  el.newBookingBtn?.addEventListener('click', () => {
    state.selectedTypeId = null; state.selectedDatetime = null; state.emailGateCleared = false;
    el.contactForm?.reset();
    // Réappliquer le pré-remplissage
    if (cfg.currentUser) {
      var u = cfg.currentUser;
      if (el.firstNameEl && u.first_name) el.firstNameEl.value = u.first_name;
      if (el.lastNameEl  && u.last_name)  el.lastNameEl.value  = u.last_name;
      if (el.emailEl     && u.email)      el.emailEl.value     = u.email;
    }
    if (presetCard) {
      presetCard.click();
    } else {
      goTo('type');
    }
  });

  // -------------------------------------------------------------------------
  // Helpers
  // -------------------------------------------------------------------------
  function setSlotsHint(msg) { el.slotsContainer.innerHTML = `<p class="ipik-slots__hint">${msg}</p>`; }
  function showFormError(msg) { if (el.formError) { el.formError.textContent = msg; el.formError.style.display = ''; el.formError.focus(); } }
  function hideFormError()    { if (el.formError) { el.formError.style.display = 'none'; el.formError.textContent = ''; } }
  function setSubmitting(on)  { if (el.submitBtn) { el.submitBtn.disabled = on; const s = el.submitBtn.querySelector('.ipik-submit__spinner'); if (s) s.style.display = on ? '' : 'none'; } }
  function showGateMessage(msg, type) { if (el.emailGateResult) { el.emailGateResult.textContent = msg; el.emailGateResult.className = `ipik-gate-msg ipik-gate-msg--${type}`; } }
  // Format Y-m-d en heure LOCALE.
  // Ne jamais utiliser toISOString() : il convertit en UTC et décale d'un jour
  // pour tout fuseau positif (Europe/Zurich = UTC+1 ou +2).
  function formatDate(date) {
    var y = date.getFullYear();
    var m = String(date.getMonth() + 1).padStart(2, '0');
    var d = String(date.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + d;
  }
  function formatDisplay(dateStr) {
    const [y, m, d] = dateStr.split('-');
    const months = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
    return `${parseInt(d)} ${months[parseInt(m) - 1]} ${y}`;
  }

  function ajax(action, data) {
    const body = new URLSearchParams({
      action: `ipik_booking_${action}`,
      nonce:  getNonce(),
      ...data
    });
    return fetch(ajaxUrl, { method: 'POST', body, credentials: 'same-origin' })
      .then(r => {
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.json();
      })
      .then(json => {
        if (!json.success) throw new Error(json.data?.message || l10n.errorGeneric);
        return json.data;
      });
  }

})();
