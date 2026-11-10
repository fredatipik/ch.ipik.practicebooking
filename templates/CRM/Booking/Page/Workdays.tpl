{* ch.ipik.practicebooking — Page/Workdays.tpl *}
<div class="crm-container ipik-workdays" id="ipik-workdays"
     data-therapist="{$therapist.id}"
     data-save-url="{$saveURL}">

  <p class="description">
    {ts}Cliquez sur un jour pour le déclarer travaillé. Les horaires et le local se règlent dans le panneau de droite, puis s'appliquent aux jours suivants.{/ts}
  </p>

  {* Réglages appliqués aux prochains clics *}
  <div class="ipik-wd-toolbar">
    <div class="ipik-wd-field">
      <label>{ts}De{/ts}</label>
      <input type="time" id="ipik-wd-start" value="{$defaults.start}" step="300" />
    </div>
    <div class="ipik-wd-field">
      <label>{ts}À{/ts}</label>
      <input type="time" id="ipik-wd-end" value="{$defaults.end}" step="300" />
    </div>
    <div class="ipik-wd-field">
      <label>{ts}Local{/ts}</label>
      <select id="ipik-wd-location">
        <option value="">{ts}Hors local{/ts}</option>
        {foreach from=$locations item=loc name=locs}
          <option value="{$loc.id}" {if $smarty.foreach.locs.first}selected="selected"{/if}>{$loc.name}</option>
        {/foreach}
      </select>
    </div>
    <div class="ipik-wd-status" id="ipik-wd-status" aria-live="polite"></div>
  </div>

  {* Navigation entre périodes *}
  <div class="ipik-wd-nav">
    <a href="{$prevURL}" class="button">← {ts}Six mois avant{/ts}</a>
    <strong>{$periodLabel}</strong>
    <a href="{$nextURL}" class="button">{ts}Six mois après{/ts} →</a>
  </div>

  {* Calendrier *}
  <div class="ipik-wd-grid">
    {foreach from=$months item=month}
      <div class="ipik-wd-month">
        <div class="ipik-wd-month__label">{$month.label}</div>
        <div class="ipik-wd-days">
          <div class="ipik-wd-dow">L</div><div class="ipik-wd-dow">M</div>
          <div class="ipik-wd-dow">M</div><div class="ipik-wd-dow">J</div>
          <div class="ipik-wd-dow">V</div><div class="ipik-wd-dow">S</div>
          <div class="ipik-wd-dow">D</div>
          {foreach from=$month.cells item=cell}
            {if $cell}
              <div role="button"
                   class="ipik-wd-day{if $cell.selected} is-on{/if}{if $cell.is_past} is-past{/if}{if $cell.is_today} is-today{/if}"
                   data-date="{$cell.date}"
                   data-start="{$cell.start}"
                   data-end="{$cell.end}"
                   data-location="{$cell.location}"
                   {if $cell.color}style="--wd-color:{$cell.color}"{/if}
                   {if $cell.is_past}aria-disabled="true" tabindex="-1"{else}aria-disabled="false" tabindex="0"{/if}
                   aria-pressed="{if $cell.selected}true{else}false{/if}"
                   title="{$cell.date}{if $cell.selected} · {$cell.start}–{$cell.end}{/if}">{$cell.day}</div>
            {else}
              <span class="ipik-wd-day is-empty"></span>
            {/if}
          {/foreach}
        </div>
      </div>
    {/foreach}
  </div>

  <div class="ipik-wd-footer">
    <a href="{$agendaURL}" class="button">{ts}Retour à l'agenda{/ts}</a>
  </div>
</div>

<style>
.ipik-workdays .ipik-wd-toolbar {
  display:flex; align-items:flex-end; gap:1.2rem; flex-wrap:wrap;
  background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px;
  padding:.8rem 1rem; margin:1rem 0;
}
.ipik-wd-field { display:flex; flex-direction:column; gap:.2rem; }
.ipik-wd-field label { font-size:.78rem; color:#6b7280; }
.ipik-wd-field input, .ipik-wd-field select { padding:.25rem .4rem; }
.ipik-wd-status { font-size:.82rem; color:#065f46; margin-left:auto; min-height:1.2em; }
.ipik-wd-status.is-error { color:#b91c1c; }

.ipik-wd-nav { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin:1rem 0; flex-wrap:wrap; }

.ipik-wd-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:1.4rem 2.6rem; }
@media (max-width:1100px) { .ipik-wd-grid { grid-template-columns:repeat(2, 1fr); gap:1.4rem 2.2rem; } }
@media (max-width:700px)  { .ipik-wd-grid { grid-template-columns:1fr; } }

.ipik-wd-month__label {
  font-weight:600; font-size:.88rem; margin-bottom:.4rem;
  padding-bottom:.25rem; border-bottom:1px solid #e5e7eb;
}
/* Les cases sont des <div role="button"> et non des <button> : les thèmes
   CiviCRM restylent les boutons (fond plein, hauteur minimale, marges, états
   désactivés), ce qui défigurait le calendrier malgré toutes les règles
   imposées. Un <div> n'est pas touché par ces styles. */
.ipik-workdays .ipik-wd-month { max-width:330px; }
.ipik-workdays .ipik-wd-days {
  display:grid; grid-template-columns:repeat(7, minmax(0, 1fr)); gap:3px;
}
.ipik-workdays .ipik-wd-dow {
  text-align:center; font-size:.68rem; color:#9ca3af; padding-bottom:.2rem;
}
.ipik-workdays .ipik-wd-day {
  box-sizing:border-box; aspect-ratio:1 / 1;
  display:flex; align-items:center; justify-content:center;
  border:1px solid transparent; border-radius:5px;
  font-size:.8rem; line-height:1; color:inherit;
  cursor:pointer; user-select:none; -webkit-user-select:none;
  transition:background .12s, border-color .12s;
}
.ipik-workdays .ipik-wd-day:hover { background:#eef2ff; border-color:#c7d2fe; }
.ipik-workdays .ipik-wd-day:focus-visible { outline:2px solid #6366f1; outline-offset:1px; }
.ipik-workdays .ipik-wd-day.is-empty { visibility:hidden; cursor:default; }
.ipik-workdays .ipik-wd-day.is-past  { color:#c4c9d1; cursor:not-allowed; }
.ipik-workdays .ipik-wd-day.is-past:hover { background:transparent; border-color:transparent; }
.ipik-workdays .ipik-wd-day.is-today { font-weight:700; text-decoration:underline; }
.ipik-workdays .ipik-wd-day.is-on {
  background:var(--wd-color, #10b981); border-color:var(--wd-color, #10b981);
  color:#fff; font-weight:600;
}
.ipik-workdays .ipik-wd-day.is-on:hover { opacity:.85; background:var(--wd-color, #10b981); }
.ipik-workdays .ipik-wd-day.is-saving { opacity:.5; pointer-events:none; }

.ipik-wd-footer { margin-top:1.5rem; }
</style>

{literal}
<script>
(function () {
  var root = document.getElementById('ipik-workdays');
  if (!root) return;

  var therapistId = root.dataset.therapist;
  var saveUrl     = root.dataset.saveUrl;
  var statusEl    = document.getElementById('ipik-wd-status');
  var startEl     = document.getElementById('ipik-wd-start');
  var endEl       = document.getElementById('ipik-wd-end');
  var locationEl  = document.getElementById('ipik-wd-location');

  var locationColors = {};
  root.querySelectorAll('.ipik-wd-day.is-on').forEach(function (btn) {
    var loc = btn.dataset.location;
    var col = btn.style.getPropertyValue('--wd-color');
    if (loc && col) locationColors[loc] = col;
  });

  function say(message, isError) {
    statusEl.textContent = message;
    statusEl.classList.toggle('is-error', !!isError);
    if (!isError) {
      setTimeout(function () {
        if (statusEl.textContent === message) statusEl.textContent = '';
      }, 2500);
    }
  }

  function post(params) {
    var body = new URLSearchParams(params);
    return fetch(saveUrl, {
      method: 'POST',
      body: body,
      credentials: 'same-origin'
    }).then(function (r) { return r.json(); });
  }

  function toggle(btn) {
    if (!btn || btn.classList.contains('is-empty')
      || btn.getAttribute('aria-disabled') === 'true'
      || btn.classList.contains('is-saving')) return;

    var isOn = btn.classList.contains('is-on');
    btn.classList.add('is-saving');

    var params = {
      therapist_id: therapistId,
      date: btn.dataset.date,
      action_type: isOn ? 'unset' : 'set'
    };

    if (!isOn) {
      params.start_time  = startEl.value;
      params.end_time    = endEl.value;
      params.location_id = locationEl.value;
    }

    post(params).then(function (res) {
      btn.classList.remove('is-saving');

      if (!res.success) {
        say(res.message || 'Enregistrement impossible.', true);
        return;
      }

      if (res.selected) {
        btn.classList.add('is-on');
        btn.setAttribute('aria-pressed', 'true');
        btn.dataset.start    = res.start;
        btn.dataset.end      = res.end;
        btn.dataset.location = res.location_id || '';
        var color = locationColors[res.location_id];
        if (color) btn.style.setProperty('--wd-color', color);
        btn.title = btn.dataset.date + ' · ' + res.start + '–' + res.end;
      } else {
        btn.classList.remove('is-on');
        btn.setAttribute('aria-pressed', 'false');
        btn.dataset.start = btn.dataset.end = btn.dataset.location = '';
        btn.style.removeProperty('--wd-color');
        btn.title = btn.dataset.date;
      }

      say(res.message);
    }).catch(function () {
      btn.classList.remove('is-saving');
      say('Erreur de communication avec le serveur.', true);
    });
  }

  root.addEventListener('click', function (event) {
    toggle(event.target.closest('.ipik-wd-day'));
  });

  // Entrée ou Espace, comme pour un vrai bouton
  root.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter' && event.key !== ' ') return;
    var btn = event.target.closest('.ipik-wd-day');
    if (!btn) return;
    event.preventDefault();
    toggle(btn);
  });
})();
</script>
{/literal}
