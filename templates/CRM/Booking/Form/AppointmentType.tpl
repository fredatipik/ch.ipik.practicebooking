{* ch.ipik.practicebooking — Form/AppointmentType.tpl *}
<div class="crm-container crm-form-block">
  <h3>{if $recordId}{ts}Modifier le type de rendez-vous{/ts}{else}{ts}Nouveau type de rendez-vous{/ts}{/if}</h3>

  <div class="crm-section">
    <div class="label">{$form.label.label}</div>
    <div class="content">{$form.label.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.description.label}</div>
    <div class="content">{$form.description.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.duration_minutes.label}</div>
    <div class="content">{$form.duration_minutes.html} <span class="description">{ts}minutes{/ts}</span></div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.color.label}</div>
    <div class="content">
      {$form.color.html}
      <input type="color" id="ipik-color-picker" style="margin-left:.5rem;cursor:pointer;" />
      <span class="description">{ts}Ex : #10b981{/ts}</span>
    </div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.therapist_selector.label}</div>
    <div class="content">{$form.therapist_selector.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.slot_interval_minutes.label}</div>
    <div class="content">
      {$form.slot_interval_minutes.html}
      <span class="description">{ts}Laisser vide pour utiliser la valeur définie dans les paramètres généraux.{/ts}</span>
    </div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.requires_existing_contact.label}</div>
    <div class="content">
      {$form.requires_existing_contact.html}
      <span class="description">{ts}Si coché, le visiteur doit être connecté au site avec un compte rattaché à une fiche CiviCRM. Les autres sont invités à se connecter.{/ts}</span>
    </div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.requires_account_creation.label}</div>
    <div class="content">
      {$form.requires_account_creation.html}
      <span class="description">{ts}Si coché, un compte WordPress sera créé pour les nouveaux patients.{/ts}</span>
    </div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{ts}Intervenant·es éligibles{/ts}</div>
    <div class="content">
      {if $therapistOptions}
        {foreach from=$therapistOptions key=tid item=tname}
          {assign var="cbname" value="therapist_ids_`$tid`"}
          <div style="margin-bottom:.25rem">{$form.$cbname.html} {$tname}</div>
        {/foreach}
      {else}
        <em>{ts}Aucun·e intervenant·e enregistré·e.{/ts}</em>
      {/if}
    </div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.weight.label}</div>
    <div class="content">{$form.weight.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.is_active.label}</div>
    <div class="content">{$form.is_active.html}</div>
    <div class="clear"></div>
  </div>

  <div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
    <a href="{$cancelURL}" class="button cancel">{ts}Annuler{/ts}</a>
  </div>
</div>

{literal}
<script>
(function() {
  var txt = document.querySelector('input[name="color"]');
  var picker = document.getElementById('ipik-color-picker');
  if (!txt || !picker) return;
  picker.value = txt.value || '#10b981';
  picker.addEventListener('input', function() { txt.value = picker.value; });
  txt.addEventListener('input', function() {
    if (/^#[0-9a-f]{6}$/i.test(txt.value)) picker.value = txt.value;
  });
})();
</script>
{/literal}
