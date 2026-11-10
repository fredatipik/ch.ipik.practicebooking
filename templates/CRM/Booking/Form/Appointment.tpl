{* ch.ipik.practicebooking — Form/Appointment.tpl (création et modification) *}
<div class="crm-container crm-form-block ipik-new-appt"
     data-slots-url="{$slotsURL}"
     data-edit="{if $isEditMode}1{else}0{/if}">

  {if $isEditMode}
    <div class="crm-section">
      <div class="label">{ts}Patient·e{/ts}</div>
      <div class="content">
        <a href="{$patientUrl}">{$patientName}</a>
        <span class="description">
          {ts}Le patient ne se modifie pas. Pour un autre patient, annulez ce rendez-vous et créez-en un nouveau.{/ts}
        </span>
      </div>
      <div class="clear"></div>
    </div>
    <div class="crm-section">
      <div class="label">{ts}Horaire actuel{/ts}</div>
      <div class="content">
        {$currentWhen}{if $currentPlace} — {$currentPlace}{/if}
      </div>
      <div class="clear"></div>
    </div>
  {else}
    <div class="crm-section">
      <div class="label">{$form.contact_id.label}</div>
      <div class="content">{$form.contact_id.html}</div>
      <div class="clear"></div>
    </div>
  {/if}

  <div class="crm-section">
    <div class="label">{$form.appointment_type_id.label}</div>
    <div class="content">{$form.appointment_type_id.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.therapist_id.label}</div>
    <div class="content">{$form.therapist_id.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.slot_mode.label}</div>
    <div class="content">
      {$form.slot_mode.html}
      {if $isEditMode}
        <span class="description">
          {ts}Les créneaux proposés excluent celui du rendez-vous lui-même : pour le décaler de quelques minutes, utilisez la saisie libre.{/ts}
        </span>
      {/if}
    </div>
    <div class="clear"></div>
  </div>

  {* Créneaux libres *}
  <div class="crm-section" id="ipik-slot-free">
    <div class="label">{$form.slot.label}</div>
    <div class="content">
      {$form.slot.html}
      <span class="description" id="ipik-slot-hint">
        {ts}Choisissez un type et un·e intervenant·e pour voir les créneaux disponibles.{/ts}
      </span>
    </div>
    <div class="clear"></div>
  </div>

  {* Saisie libre *}
  <div class="crm-section" id="ipik-slot-manual" style="display:none">
    <div class="label">{ts}Date et heure{/ts}</div>
    <div class="content">
      <input type="date" name="manual_date" id="ipik-manual-date" class="crm-form-text" value="{$manualDate}" />
      <input type="time" name="manual_time" id="ipik-manual-time" class="crm-form-text" step="300" value="{$manualTime}" />
      <span class="description">
        {ts}La saisie libre ignore les disponibilités déclarées et les agendas. À réserver aux cas particuliers : le créneau ne sera pas vérifié.{/ts}
      </span>
    </div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.location_id.label}</div>
    <div class="content">
      {$form.location_id.html}
      <span class="description">
        {if $isEditMode}
          {ts}Le local actuel est conservé par défaut. Si la date ou l'intervenant·e change, choisissez « Déduire automatiquement » pour le recalculer d'après la journée de travail.{/ts}
        {else}
          {ts}Par défaut, le local découle de la journée de travail ou du local habituel de l'intervenant·e. Précisez-le si le rendez-vous se tient ailleurs — l'agenda du local en sera informé.{/ts}
        {/if}
      </span>
    </div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.notes.label}</div>
    <div class="content">{$form.notes.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.send_notifications.label}</div>
    <div class="content">
      {$form.send_notifications.html}
      <span class="description">
        {if $isEditMode}
          {ts}Renvoie la confirmation au patient et à l'intervenant·e. Cochée automatiquement dès que l'horaire, le type ou l'intervenant·e change.{/ts}
        {else}
          {ts}Décochez si la personne a déjà été prévenue.{/ts}
        {/if}
      </span>
    </div>
    <div class="clear"></div>
  </div>

  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
    <a href="{$cancelURL}" class="button cancel">{ts}Annuler{/ts}</a>
  </div>
</div>

{literal}
<script>
(function () {
  var root = document.querySelector('.ipik-new-appt');
  if (!root) return;

  var slotsUrl = root.dataset.slotsUrl;
  var isEdit   = root.dataset.edit === '1';
  var modeEl   = document.querySelector('select[name="slot_mode"]');
  var typeEl   = document.querySelector('select[name="appointment_type_id"]');
  var therEl   = document.querySelector('select[name="therapist_id"]');
  var slotEl   = document.querySelector('select[name="slot"]');
  var notifyEl = document.querySelector('input[name="send_notifications"]');
  var dateEl   = document.getElementById('ipik-manual-date');
  var timeEl   = document.getElementById('ipik-manual-time');
  var hintEl   = document.getElementById('ipik-slot-hint');
  var freeBox  = document.getElementById('ipik-slot-free');
  var manBox   = document.getElementById('ipik-slot-manual');

  // En modification, tout changement qui concerne le patient coche le renvoi
  // de la convocation ; la case reste décochable.
  function markChanged() {
    if (isEdit && notifyEl) notifyEl.checked = true;
  }

  function toggleMode() {
    var mode = modeEl.value;
    freeBox.style.display = (mode === 'free')   ? '' : 'none';
    manBox.style.display  = (mode === 'manual') ? '' : 'none';
    if (mode === 'free') loadSlots();
  }

  function loadSlots() {
    // Les créneaux ne sont demandés que lorsqu'ils sont affichés
    if (modeEl.value !== 'free') return;

    var typeId = typeEl.value;
    var therId = therEl.value;

    slotEl.innerHTML = '';

    if (!typeId || !therId) {
      slotEl.appendChild(new Option('— Choisir un type et un·e intervenant·e —', ''));
      hintEl.textContent = 'Choisissez un type et un·e intervenant·e pour voir les créneaux disponibles.';
      return;
    }

    slotEl.appendChild(new Option('Chargement…', ''));
    hintEl.textContent = '';

    var url = slotsUrl
      + (slotsUrl.indexOf('?') === -1 ? '?' : '&')
      + 'type_id=' + encodeURIComponent(typeId)
      + '&therapist_id=' + encodeURIComponent(therId);

    fetch(url, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        slotEl.innerHTML = '';
        var slots = data.slots || [];

        if (!slots.length) {
          slotEl.appendChild(new Option('Aucun créneau disponible', ''));
          hintEl.textContent = 'Aucune disponibilité sur les deux prochains mois. Utilisez la saisie libre si nécessaire.';
          return;
        }

        slotEl.appendChild(new Option('— Choisir —', ''));
        slots.forEach(function (s) {
          slotEl.appendChild(new Option(s.label, s.value));
        });
        hintEl.textContent = slots.length + ' créneau(x) disponible(s).';
      })
      .catch(function () {
        slotEl.innerHTML = '';
        slotEl.appendChild(new Option('Erreur de chargement', ''));
        hintEl.textContent = 'Les créneaux n\u2019ont pas pu être chargés.';
      });
  }

  modeEl.addEventListener('change', toggleMode);
  typeEl.addEventListener('change', function () { markChanged(); loadSlots(); });
  therEl.addEventListener('change', function () { markChanged(); loadSlots(); });
  slotEl.addEventListener('change', markChanged);
  if (dateEl) dateEl.addEventListener('change', markChanged);
  if (timeEl) timeEl.addEventListener('change', markChanged);

  toggleMode();
})();
</script>
{/literal}
