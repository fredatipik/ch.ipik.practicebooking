{* ch.ipik.practicebooking — Page/TherapistAgenda.tpl *}
{assign var="t_id"  value=$therapist.id}
{assign var="t_cid" value=$therapist.contact_id}

<div class="crm-container ipik-agenda">

  {* ------------------------------------------------------------------ *}
  {* Rendez-vous                                                         *}
  {* ------------------------------------------------------------------ *}
  <div class="ipik-agenda__head">
    <h3>
      {if $showPast}{ts}Rendez-vous passés{/ts}{else}{ts}Rendez-vous à venir{/ts}{/if}
      <span class="ipik-count">{$total}</span>
    </h3>
    <div>
      <a href="{crmURL p='civicrm/booking/appointment/new' q="therapist_id=`$t_id`&cid=`$t_cid`&reset=1"}" class="button">
        {ts}+ Nouveau rendez-vous{/ts}
      </a>
      <a href="{$toggleURL}" class="button">
        {if $showPast}{ts}Voir les rendez-vous à venir{/ts}{else}{ts}Voir l'historique{/ts}{/if}
      </a>
    </div>
  </div>

  {if $months}
    {foreach from=$months item=month}
      <h4 class="ipik-month">{$month.label}</h4>
      <table class="ipik-appts">
        <tbody>
          {foreach from=$month.appointments item=appt}
            {assign var="appt_id"  value=$appt.id}
            {assign var="appt_cid" value=$appt.contact_id}
            {assign var="inv_id"   value=$appt.swissqr_invoice_id}
            <tr class="ipik-appt ipik-appt--{$appt.status}">
              <td class="ipik-appt__when">
                <div class="ipik-appt__day">{$appt.day_label}</div>
                <div class="ipik-appt__time">{$appt.time_label} – {$appt.end_label}</div>
              </td>
              <td class="ipik-appt__type">
                <span class="ipik-dot" style="background:{$appt.type_color}"></span>
                {$appt.type_label}
              </td>
              <td class="ipik-appt__who">
                <a href="{crmURL p='civicrm/contact/view' q="cid=`$appt_cid`"}">{$appt.contact_name}</a>
              </td>
              <td class="ipik-appt__status">
                {if $appt.status eq 'completed'}
                  <span class="ipik-badge ipik-badge--done">{ts}Effectué{/ts}</span>
                {elseif $appt.status eq 'pending'}
                  <span class="ipik-badge ipik-badge--pending">{ts}En attente{/ts}</span>
                {/if}
              </td>
              <td class="ipik-appt__actions">
                {if $appt.status eq 'confirmed' or $appt.status eq 'pending'}
                  <a href="{crmURL p='civicrm/booking/appointment/edit' q="id=`$appt_id`&cid=`$t_cid`&reset=1"}"
                     title="{ts}Modifier le rendez-vous{/ts}">✎</a>
                {/if}
                {if $appt.status eq 'confirmed'}
                  <a href="{crmURL p='civicrm/booking/appointment/complete' q="id=`$appt_id`&cid=`$t_cid`"}"
                     title="{ts}Marquer la séance comme effectuée{/ts}"
                     onclick="return confirm('{ts}Confirmer que la séance a eu lieu ?{/ts}')">✓</a>
                  <a href="{crmURL p='civicrm/booking/appointment/cancel' q="id=`$appt_id`&cid=`$t_cid`"}"
                     title="{ts}Annuler — un email sera envoyé{/ts}"
                     onclick="return confirm('{ts}Annuler ce rendez-vous ? Le patient et vous recevrez un email.{/ts}')">✕</a>
                {/if}
                {if $inv_id}
                  <a href="{crmURL p='civicrm/swissqr/invoice/edit' q="id=`$inv_id`&reset=1"}"
                     title="{ts}Ouvrir la facture{/ts}">🧾</a>
                {/if}
              </td>
            </tr>
          {/foreach}
        </tbody>
      </table>
    {/foreach}

    {if $pageLinks}
      <div class="ipik-pager">
        {foreach from=$pageLinks item=p}
          {if $p.current}
            <span class="ipik-pager__cur">{$p.num}</span>
          {else}
            <a href="{$p.url}">{$p.num}</a>
          {/if}
        {/foreach}
      </div>
    {/if}

  {else}
    <p class="ipik-empty">
      {if $showPast}{ts}Aucun rendez-vous passé.{/ts}{else}{ts}Aucun rendez-vous à venir.{/ts}{/if}
    </p>
  {/if}

  {* ------------------------------------------------------------------ *}
  {* Disponibilités — présentation selon le mode                         *}
  {* ------------------------------------------------------------------ *}
  {if $availMode eq 'workdays'}

  <h3 class="ipik-section">{ts}Jours de travail{/ts}</h3>
  <p class="ipik-empty">
    {if $workdayCount}
      {ts count=$workdayCount plural='%count journées déclarées à venir.'}%count journée déclarée à venir.{/ts}
    {else}
      {ts}Aucune journée déclarée — aucun créneau ne sera proposé.{/ts}
    {/if}
  </p>
  <a href="{$workdaysURL}" class="button">{ts}Ouvrir le calendrier des jours de travail{/ts}</a>

  {else}

  <h3 class="ipik-section">{ts}Disponibilités récurrentes{/ts}</h3>

  {if $availabilities}
    <table class="ipik-avail-list">
      <tbody>
        {foreach from=$availabilities item=avail}
          {assign var="dow" value=$avail.day_of_week}
          <tr>
            <th scope="row">{$dayNames.$dow}</th>
            <td>{$avail.start_time|truncate:5:""} – {$avail.end_time|truncate:5:""}</td>
          </tr>
        {/foreach}
      </tbody>
    </table>
  {else}
    <p class="ipik-empty">{ts}Aucune disponibilité configurée — aucun créneau ne sera proposé.{/ts}</p>
  {/if}

  <a href="{crmURL p='civicrm/booking/availability/add' q="therapist_id=`$t_id`&cid=`$t_cid`"}" class="button">
    {ts}Modifier les disponibilités{/ts}
  </a>

  {* ------------------------------------------------------------------ *}
  {* Congés et créneaux exceptionnels — mode hebdomadaire uniquement     *}
  {* ------------------------------------------------------------------ *}
  <h3 class="ipik-section">{ts}Congés et créneaux exceptionnels{/ts}</h3>

  {if $exceptions}
    <table class="ipik-exc-list">
      <tbody>
        {foreach from=$exceptions item=exc}
          {assign var="exc_id" value=$exc.id}
          <tr>
            <td>
              {if $exc.type eq 'off'}
                <span class="ipik-badge ipik-badge--off">{ts}Congé{/ts}</span>
              {else}
                <span class="ipik-badge ipik-badge--special">{ts}Créneau exceptionnel{/ts}</span>
              {/if}
            </td>
            <td>
              {$exc.date_start} → {$exc.date_end}
              {if $exc.start_time}
                <br><small>{$exc.start_time|truncate:5:""} – {$exc.end_time|truncate:5:""}</small>
              {/if}
            </td>
            <td class="ipik-note">{$exc.note|default:''}</td>
            <td>
              <a href="{crmURL p='civicrm/booking/exception/delete' q="id=`$exc_id`&cid=`$t_cid`"}"
                 onclick="return confirm('{ts}Supprimer cette exception ?{/ts}')">{ts}Supprimer{/ts}</a>
            </td>
          </tr>
        {/foreach}
      </tbody>
    </table>
  {else}
    <p class="ipik-empty">{ts}Aucun congé ni créneau exceptionnel.{/ts}</p>
  {/if}

  <a href="{crmURL p='civicrm/booking/exception/add' q="therapist_id=`$t_id`&cid=`$t_cid`"}" class="button">
    {ts}Ajouter un congé ou un créneau exceptionnel{/ts}
  </a>

  {/if}

</div>

<style>
.ipik-agenda__head { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
.ipik-agenda__head h3 { margin:0; }
.ipik-count { background:#e5e7eb; border-radius:10px; font-size:.75rem; padding:.1rem .5rem; margin-left:.4rem; vertical-align:middle; }

.ipik-month { margin:1.5rem 0 .4rem; font-size:.9rem; text-transform:uppercase; letter-spacing:.04em; color:#6b7280; border-bottom:1px solid #e5e7eb; padding-bottom:.3rem; }

.ipik-appts { width:100%; border-collapse:collapse; }
.ipik-appt { border-bottom:1px solid #f3f4f6; }
.ipik-appt:hover { background:#fafafa; }
.ipik-appt--completed { opacity:.6; }
.ipik-appt td { padding:.5rem .6rem; vertical-align:middle; }
.ipik-appt__when  { width:16rem; }
.ipik-appt__day   { font-weight:600; text-transform:capitalize; }
.ipik-appt__time  { font-size:.8rem; color:#6b7280; }
.ipik-appt__type  { white-space:nowrap; }
.ipik-appt__actions { text-align:right; white-space:nowrap; }
.ipik-appt__actions a { text-decoration:none; font-size:1rem; margin-left:.4rem; }
.ipik-dot { display:inline-block; width:9px; height:9px; border-radius:50%; margin-right:.4rem; }

.ipik-badge { border-radius:4px; font-size:.72rem; padding:.15rem .45rem; font-weight:600; }
.ipik-badge--done    { background:#dbeafe; color:#1e40af; }
.ipik-badge--pending { background:#fef3c7; color:#92400e; }
.ipik-badge--off     { background:#fee2e2; color:#991b1b; }
.ipik-badge--special { background:#d1fae5; color:#065f46; }

.ipik-pager { margin:1rem 0; display:flex; gap:.3rem; }
.ipik-pager a, .ipik-pager__cur { padding:.2rem .55rem; border-radius:4px; text-decoration:none; font-size:.85rem; }
.ipik-pager a { border:1px solid #e5e7eb; }
.ipik-pager__cur { background:#3b82f6; color:#fff; font-weight:600; }

.ipik-section { margin-top:2rem; }
.ipik-empty { color:#9ca3af; font-style:italic; margin:.6rem 0 1rem; }

.ipik-avail-list, .ipik-exc-list { border-collapse:collapse; margin:.6rem 0 1rem; }
.ipik-avail-list th, .ipik-avail-list td,
.ipik-exc-list td { padding:.35rem .8rem .35rem 0; text-align:left; }
.ipik-avail-list th { width:7rem; font-weight:600; }
.ipik-note { color:#6b7280; font-size:.85rem; }
</style>
