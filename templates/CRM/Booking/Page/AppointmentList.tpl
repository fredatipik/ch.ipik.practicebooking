{* ch.ipik.practicebooking — Page/AppointmentList.tpl *}
<div class="crm-container ipik-appt-list">

  <div style="margin-bottom:1rem">
    <a href="{crmURL p='civicrm/booking/appointment/new' q='reset=1'}" class="button">
      {ts}+ Nouveau rendez-vous{/ts}
    </a>
  </div>

  {* Filtres *}
  <div class="crm-block crm-form-block ipik-filters">
    <form method="get" action="{crmURL p='civicrm/booking/appointments'}">
      <input type="hidden" name="q" value="civicrm/booking/appointments" />
      <input type="hidden" name="page" value="CiviCRM" />
      <input type="hidden" name="reset" value="1" />
      <div class="ipik-filters__row">
        <div>
          <label>{ts}Intervenant·e{/ts}</label><br>
          <select name="therapist_id">
            <option value="">{ts}Tous{/ts}</option>
            {foreach from=$therapists item=t}
              {assign var="t_id" value=$t.id}
              <option value="{$t_id}" {if $filters.therapist_id eq $t_id}selected="selected"{/if}>{$t.display_name}</option>
            {/foreach}
          </select>
        </div>
        <div>
          <label>{ts}Du{/ts}</label><br>
          <input type="date" name="date_from" value="{$filters.date_from}" />
        </div>
        <div>
          <label>{ts}Au{/ts}</label><br>
          <input type="date" name="date_to" value="{$filters.date_to}" />
        </div>
        <div>
          <label>{ts}Statut{/ts}</label><br>
          <select name="status">
            {foreach from=$statusOptions key=val item=label}
              <option value="{$val}" {if $filters.status eq $val}selected="selected"{/if}>{$label}</option>
            {/foreach}
          </select>
        </div>
        <div>
          <button type="submit" class="button">{ts}Filtrer{/ts}</button>
        </div>
      </div>
    </form>
  </div>

  {* Tableau *}
  <table class="display dataTable" style="width:100%">
    <thead>
      <tr>
        <th>{ts}Date / Heure{/ts}</th>
        <th>{ts}Type{/ts}</th>
        <th>{ts}Intervenant·e{/ts}</th>
        <th>{ts}Patient{/ts}</th>
        <th>{ts}Lieu{/ts}</th>
        <th>{ts}Statut{/ts}</th>
        <th>{ts}Facturation{/ts}</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      {foreach from=$appointments item=appt}
      {assign var="appt_cid" value=$appt.contact_id}
      {assign var="appt_id"  value=$appt.id}
      {assign var="inv_id"   value=$appt.swissqr_invoice_id}
      {assign var="st"       value=$appt.status}
      <tr>
        <td>{$appt.start_datetime|date_format:'%d.%m.%Y %H:%M'}</td>
        <td>
          <span class="ipik-dot" style="background:{$appt.type_color}"></span>
          {$appt.type_label}
        </td>
        <td>
          {if $appt.therapist_url}
            <a href="{$appt.therapist_url}">{$appt.therapist_name}</a>
          {else}
            {$appt.therapist_name}
          {/if}
        </td>
        <td>
          <a href="{crmURL p='civicrm/contact/view' q="cid=`$appt_cid`"}">{$appt.contact_name}</a>
        </td>
        <td>
          {if $appt.location_name}
            <span class="ipik-loc" style="border-color:{$appt.location_color}">{$appt.location_name}</span>
          {else}
            <span style="color:#d1d5db">—</span>
          {/if}
        </td>
        <td>
          <span class="crm-status-badge crm-status-{$st}">
            {$statusOptions.$st|default:$st}
          </span>
        </td>
        <td class="ipik-billing">
          {if $inv_id}
            <a href="{crmURL p='civicrm/swissqr/invoice/edit' q="id=`$inv_id`&reset=1"}"
               title="{ts}Ouvrir la facture{/ts}">🧾 #{$inv_id}</a>
          {else}
            {if $invoiceAvailable}
              <a href="{$appt.invoice_url}" class="ipik-btn" title="{ts}Créer une facture QR{/ts}">{ts}Facturer{/ts}</a>
            {/if}
            <a href="{$appt.contribution_url}" class="ipik-btn" title="{ts}Enregistrer une contribution{/ts}">{ts}Contribution{/ts}</a>
          {/if}
        </td>
        <td class="ipik-actions">
          {if $st eq 'confirmed' or $st eq 'pending'}
            <a href="{crmURL p='civicrm/booking/appointment/edit' q="id=`$appt_id`&reset=1"}"
               title="{ts}Modifier le rendez-vous{/ts}">✎</a>
          {/if}
          {if $st eq 'confirmed'}
            <a href="{crmURL p='civicrm/booking/appointment/complete' q="id=`$appt_id`"}"
               title="{ts}Marquer la séance comme effectuée{/ts}"
               onclick="return confirm('{ts}Confirmer que la séance a eu lieu ?{/ts}')">✓</a>
            <a href="{crmURL p='civicrm/booking/appointment/cancel' q="id=`$appt_id`"}"
               title="{ts}Annuler — un email sera envoyé au patient et au intervenant·e{/ts}"
               onclick="return confirm('{ts}Annuler ce rendez-vous ? Un email sera envoyé au patient et au intervenant·e.{/ts}')">✕</a>
          {/if}
        </td>
      </tr>
      {foreachelse}
      <tr><td colspan="8" style="text-align:center;color:#9ca3af"><em>{ts}Aucun rendez-vous sur cette période.{/ts}</em></td></tr>
      {/foreach}
    </tbody>
  </table>
</div>

<style>
.ipik-filters__row { display:flex; gap:1rem; flex-wrap:wrap; align-items:flex-end; }
.ipik-dot { display:inline-block; width:9px; height:9px; border-radius:50%; margin-right:.4rem; }

.ipik-billing { white-space:nowrap; }
.ipik-btn {
  display:inline-block; padding:.12rem .5rem; margin-right:.25rem;
  border:1px solid #d1d5db; border-radius:4px;
  font-size:.76rem; text-decoration:none; color:#374151; background:#fff;
}
.ipik-btn:hover { background:#f3f4f6; border-color:#9ca3af; }

.ipik-actions { text-align:right; white-space:nowrap; }
.ipik-actions a { text-decoration:none; font-size:1rem; margin-left:.4rem; }

.ipik-loc {
  display:inline-block; font-size:.74rem; padding:.05rem .4rem;
  border:1px solid #d1d5db; border-left-width:3px; border-radius:3px; color:#4b5563;
}
.crm-status-badge { border-radius:4px; font-size:.8rem; padding:.2rem .5rem; font-weight:600; }
.crm-status-confirmed { background:#d1fae5; color:#065f46; }
.crm-status-pending   { background:#fef3c7; color:#92400e; }
.crm-status-completed { background:#dbeafe; color:#1e40af; }
.crm-status-cancelled { background:#f3f4f6; color:#6b7280; }
</style>
