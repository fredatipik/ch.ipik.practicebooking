<?php
/**
 * Template formulaire de booking public.
 * Variables disponibles : $types (array), $atts (shortcode atts).
 */
if (!defined('ABSPATH')) exit;
?>
<div id="ipik-booking-form" class="ipik-booking" role="main" aria-label="<?php esc_attr_e('Formulaire de prise de rendez-vous', 'ipik-booking'); ?>">

  <!-- Étape 1 : Type de rendez-vous -->
  <div class="ipik-step" id="ipik-step-type" data-step="1">
    <h3 class="ipik-step__title"><?php esc_html_e('Quel type de rendez-vous souhaitez-vous prendre ?', 'ipik-booking'); ?></h3>
    <?php if (empty($types)): ?>
      <p class="ipik-empty"><?php esc_html_e('Aucun type de rendez-vous n\'est disponible pour le moment.', 'ipik-booking'); ?></p>
    <?php endif; ?>
    <div class="ipik-type-list" role="list">
      <?php foreach ($types as $type): ?>
        <button
          type="button"
          class="ipik-type-card"
          role="listitem"
          data-type-id="<?php echo (int) $type['id']; ?>"
          data-requires-existing="<?php echo (int) $type['requires_existing_contact']; ?>"
          data-requires-account="<?php echo (int) $type['requires_account_creation']; ?>"
          data-duration="<?php echo (int) $type['duration_minutes']; ?>"
          style="--type-color: <?php echo esc_attr($type['color']); ?>"
          aria-label="<?php echo esc_attr($type['label']); ?>"
        >
          <span class="ipik-type-card__dot"></span>
          <span class="ipik-type-card__label"><?php echo esc_html($type['label']); ?></span>
          <span class="ipik-type-card__duration"><?php echo (int) $type['duration_minutes']; ?> min</span>
          <?php if ($type['description']): ?>
            <span class="ipik-type-card__desc"><?php echo esc_html($type['description']); ?></span>
          <?php endif; ?>
        </button>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Étape 2 : Sélection du créneau -->
  <div class="ipik-step ipik-step--hidden" id="ipik-step-slot" data-step="2" aria-hidden="true">
    <button type="button" class="ipik-back" data-target="type" aria-label="<?php esc_attr_e('Retour', 'ipik-booking'); ?>">← <?php esc_html_e('Retour', 'ipik-booking'); ?></button>
    <h3 class="ipik-step__title"><?php esc_html_e('Choisissez une date et un créneau', 'ipik-booking'); ?></h3>

    <!-- Mini-calendrier (navigation mois) -->
    <div class="ipik-calendar" id="ipik-calendar" role="region" aria-label="<?php esc_attr_e('Calendrier', 'ipik-booking'); ?>">
      <div class="ipik-calendar__nav">
        <button type="button" id="ipik-cal-prev" aria-label="<?php esc_attr_e('Mois précédent', 'ipik-booking'); ?>">‹</button>
        <span id="ipik-cal-month"></span>
        <button type="button" id="ipik-cal-next" aria-label="<?php esc_attr_e('Mois suivant', 'ipik-booking'); ?>">›</button>
      </div>
      <div class="ipik-calendar__grid" id="ipik-cal-grid" role="grid" aria-label="<?php esc_attr_e('Jours du mois', 'ipik-booking'); ?>">
        <!-- Généré par JS -->
      </div>
    </div>

    <!-- Créneaux du jour sélectionné -->
    <div class="ipik-slots" id="ipik-slots" aria-live="polite" aria-label="<?php esc_attr_e('Créneaux disponibles', 'ipik-booking'); ?>">
      <p class="ipik-slots__hint"><?php esc_html_e('Sélectionnez une date pour voir les créneaux disponibles.', 'ipik-booking'); ?></p>
    </div>
  </div>

  <!-- Étape 3 : Informations du patient -->
  <div class="ipik-step ipik-step--hidden" id="ipik-step-contact" data-step="3" aria-hidden="true">
    <button type="button" class="ipik-back" data-target="slot" aria-label="<?php esc_attr_e('Retour', 'ipik-booking'); ?>">← <?php esc_html_e('Retour', 'ipik-booking'); ?></button>
    <h3 class="ipik-step__title"><?php esc_html_e('Vos coordonnées', 'ipik-booking'); ?></h3>

    <!-- Récapitulatif du créneau choisi -->
    <div class="ipik-summary" id="ipik-summary" aria-live="polite">
      <p class="ipik-summary__text"></p>
    </div>

    <!-- Message pour types requires_existing_contact : vérif email d'abord -->
    <div class="ipik-email-gate" id="ipik-email-gate" style="display:none;">
      <p class="ipik-email-gate__msg"><?php echo esc_html($messages['msg_email_gate'] ?? ''); ?></p>
      <div class="ipik-field">
        <label for="ipik-email-check"><?php esc_html_e('Votre email', 'ipik-booking'); ?> <span aria-hidden="true">*</span></label>
        <input type="email" id="ipik-email-check" name="email_check" autocomplete="email" required />
        <button type="button" id="ipik-check-email-btn"><?php esc_html_e('Vérifier', 'ipik-booking'); ?></button>
      </div>
      <div id="ipik-email-gate-result" aria-live="polite"></div>
    </div>

    <!-- Formulaire contact (toujours vide — pas de pré-remplissage affiché) -->
    <form id="ipik-booking-form-data" novalidate>
      <input type="hidden" name="appointment_type_id" id="ipik-field-type-id" />
      <input type="hidden" name="start_datetime" id="ipik-field-start" />
      <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('ipik_booking_nonce')); ?>" />

      <div class="ipik-fields">
        <div class="ipik-field">
          <label for="ipik-first-name"><?php esc_html_e('Prénom', 'ipik-booking'); ?> <span aria-hidden="true">*</span></label>
          <input type="text" id="ipik-first-name" name="first_name" autocomplete="given-name" required />
        </div>
        <div class="ipik-field">
          <label for="ipik-last-name"><?php esc_html_e('Nom', 'ipik-booking'); ?> <span aria-hidden="true">*</span></label>
          <input type="text" id="ipik-last-name" name="last_name" autocomplete="family-name" required />
        </div>
        <div class="ipik-field">
          <label for="ipik-email"><?php esc_html_e('Email', 'ipik-booking'); ?> <span aria-hidden="true">*</span></label>
          <input type="email" id="ipik-email" name="email" autocomplete="email" required />
        </div>
        <div class="ipik-field">
          <label for="ipik-phone"><?php esc_html_e('Téléphone', 'ipik-booking'); ?></label>
          <input type="tel" id="ipik-phone" name="phone" autocomplete="tel" />
        </div>
        <div class="ipik-field ipik-field--full">
          <label for="ipik-notes"><?php esc_html_e('Notes (optionnel)', 'ipik-booking'); ?></label>
          <textarea id="ipik-notes" name="notes" rows="3" maxlength="500"></textarea>
        </div>
      </div>

      <div id="ipik-form-error" class="ipik-error" role="alert" aria-live="assertive" style="display:none;"></div>

      <button type="submit" class="ipik-submit" id="ipik-submit-btn">
        <span class="ipik-submit__label"><?php esc_html_e('Confirmer le rendez-vous', 'ipik-booking'); ?></span>
        <span class="ipik-submit__spinner" aria-hidden="true"></span>
      </button>
    </form>
  </div>

  <!-- Étape 4 : Confirmation -->
  <div class="ipik-step ipik-step--hidden" id="ipik-step-confirm" data-step="4" aria-hidden="true" role="status" aria-live="polite">
    <div class="ipik-confirm">
      <div class="ipik-confirm__icon" aria-hidden="true">✓</div>
      <h3 class="ipik-confirm__title"><?php esc_html_e('Rendez-vous confirmé !', 'ipik-booking'); ?></h3>
      <p class="ipik-confirm__msg"><?php echo esc_html($messages['msg_confirmation'] ?? ''); ?></p>
      <button type="button" id="ipik-new-booking"><?php esc_html_e('Prendre un autre rendez-vous', 'ipik-booking'); ?></button>
    </div>
  </div>

</div><!-- #ipik-booking-form -->
