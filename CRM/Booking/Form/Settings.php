<?php
namespace CRM\Booking\Form;

use CRM\Booking\BAO\Therapist;
use CRM\Booking\Service\CalendarProvider\InformaniakCalDavProvider;
use CRM\Booking\Service\MessageService;
use CRM\Booking\Service\TemplateService;
use CRM\Booking\Utils;

/**
 * Formulaire — paramètres globaux de ch.ipik.practicebooking.
 * Regroupe : réservation, agenda CalDAV, facturation, modèles d'email,
 * et l'état de synchronisation des agendas.
 */
class Settings extends \CRM_Core_Form {

  public function preProcess(): void {
    parent::preProcess();
    \CRM_Core_Permission::check('administer booking') || \CRM_Core_Error::statusBounce(ts('Accès refusé.'));
    $this->setTitle(ts('Paramètres Booking'));
    Utils::setBreadCrumb();

    // Action de nettoyage des agendas
    if (\CRM_Utils_Request::retrieve('do', 'String', $this, FALSE, '') === 'cleanup') {
      $this->cleanupOrphans();
      \CRM_Utils_System::redirect(\CRM_Utils_System::url('civicrm/booking/settings', 'reset=1'));
    }
  }

  public function buildQuickForm(): void {
    // ---- Réservation ----
    $this->add('select', 'availability_mode', ts('Mode de disponibilité par défaut'), [
      'weekly'   => ts('Horaires hebdomadaires'),
      'workdays' => ts('Jours de travail déclarés'),
    ], TRUE);
    $this->add('text', 'default_start_time', ts('Horaire par défaut — début'), ['size' => 6]);
    $this->add('text', 'default_end_time',   ts('Horaire par défaut — fin'),   ['size' => 6]);

    $this->add('checkbox', 'create_activities', ts('Créer une activité CiviCRM pour chaque rendez-vous'));

    $this->add('select', 'therapist_selector', ts('Stratégie d\'attribution globale'), Utils::getSelectorOptions(), TRUE);
    $this->add('text', 'slot_interval_minutes', ts('Intervalle entre créneaux (minutes)'), ['size' => 4], TRUE);
    $this->addRule('slot_interval_minutes', ts('Entier positif requis.'), 'positiveInteger');
    $this->add('text', 'reminder_hours_before', ts('Rappel email (heures avant le RDV)'), ['size' => 4], TRUE);
    $this->addRule('reminder_hours_before', ts('Entier positif requis.'), 'positiveInteger');

    // ---- Textes du formulaire public ----
    foreach (MessageService::definitions() as $key => $def) {
      $this->add('textarea', $key, $def['label'], [
        'rows'        => 2,
        'cols'        => 60,
        'placeholder' => $def['default'],
      ]);
    }
    $this->assign('messageFields', MessageService::definitions());

    // ---- Envoi des e-mails ----
    // Liste des adresses déclarées dans CiviCRM, comme pour un envoi individuel
    $this->add('select', 'from_email', ts('Adresse d\'expédition'),
      ['' => ts('— Adresse par défaut du domaine —')] + $this->getFromAddresses());

    // ---- Agenda CalDAV ----
    $this->add('text', 'caldav_user', ts('Identifiant CalDAV'), ['maxlength' => 255, 'size' => 40]);
    $this->add('password', 'caldav_password', ts('Mot de passe'), ['maxlength' => 255, 'size' => 40]);

    // ---- Facturation ----
    $this->add('select', 'contribution_financial_type_id', ts('Type financier par défaut'),
      ['' => ts('— Aucun —')] + $this->getFinancialTypes());
    $this->add('select', 'contribution_status_id', ts('Statut de contribution par défaut'),
      ['' => ts('— Aucun —')] + $this->getContributionStatuses());

    // ---- Modèles d'email ----
    $this->assign('templateLinks', TemplateService::getTemplateLinks());

    // ---- État de la synchronisation ----
    $this->assign('syncRows',    $this->buildSyncStatus());
    $this->assign('orphanCount', $this->countOrphans());
    $this->assign('cleanupURL',  \CRM_Utils_System::url('civicrm/booking/settings', 'do=cleanup&reset=1'));

    $this->addButtons([['type' => 'submit', 'name' => ts('Enregistrer'), 'isDefault' => TRUE]]);
  }

  public function setDefaultValues(): array {
    $defaults = [];
    foreach (array_keys(MessageService::definitions()) as $key) {
      $defaults[$key] = Utils::getSetting($key, '');
    }

    // Le réglage contient la valeur de l'option ; une ancienne version y
    // stockait le libellé, que l'on rapporte alors à l'option correspondante.
    $fromChoice = trim((string) Utils::getSetting('from_email', ''));
    if ($fromChoice !== '') {
      $fromOptions = Utils::getFromAddressOptions();
      if (!isset($fromOptions[$fromChoice])) {
        $found = array_search(
          html_entity_decode($fromChoice, ENT_QUOTES, 'UTF-8'),
          $fromOptions,
          TRUE
        );
        $fromChoice = $found === FALSE ? '' : (string) $found;
      }
    }

    return $defaults + [
      'create_activities'              => (int) Utils::getSetting('create_activities', 1),
      'availability_mode'              => Utils::getSetting('availability_mode', 'weekly'),
      'default_start_time'             => Utils::getSetting('default_start_time', '09:00'),
      'default_end_time'               => Utils::getSetting('default_end_time', '17:00'),
      'therapist_selector'             => Utils::getSetting('therapist_selector', 'round_robin'),
      'slot_interval_minutes'          => Utils::getSetting('slot_interval_minutes', 15),
      'reminder_hours_before'          => Utils::getSetting('reminder_hours_before', 24),
      'from_email'                     => $fromChoice,
      'caldav_user'                    => Utils::getSetting('caldav_user', ''),
      'caldav_password'                => '', // jamais pré-rempli
      'contribution_financial_type_id' => Utils::getSetting('contribution_financial_type_id', ''),
      'contribution_status_id'         => Utils::getSetting('contribution_status_id', ''),
    ];
  }

  public function postProcess(): void {
    $values = $this->exportValues();

    $keys = [
      'availability_mode',
      'default_start_time',
      'default_end_time',
      'therapist_selector',
      'slot_interval_minutes',
      'reminder_hours_before',
      'from_email',
      'caldav_user',
      'contribution_financial_type_id',
      'contribution_status_id',
    ];
    foreach ($keys as $key) {
      if (isset($values[$key])) {
        Utils::setSetting($key, $values[$key]);
      }
    }

    foreach (array_keys(MessageService::definitions()) as $key) {
      Utils::setSetting($key, trim($values[$key] ?? ''));
    }

    Utils::setSetting('create_activities', !empty($values['create_activities']) ? '1' : '0');

    // Le mot de passe n'est écrasé que s'il est saisi
    if (!empty($values['caldav_password'])) {
      Utils::setSetting('caldav_password', $values['caldav_password']);
    }

    \CRM_Core_Session::setStatus(ts('Paramètres enregistrés.'), ts('Succès'), 'success');
    \CRM_Utils_System::redirect(\CRM_Utils_System::url('civicrm/booking/settings', 'reset=1'));
  }

  // -------------------------------------------------------------------------
  // Listes déroulantes CiviCRM
  // -------------------------------------------------------------------------

  /**
   * Adresses d'expédition déclarées dans CiviCRM, pour la liste déroulante.
   * Même source que le sélecteur des envois individuels.
   *
   * La clé est la valeur de l'option. Le libellé est transmis tel quel :
   * CiviCRM échappe lui-même le texte des options, et l'échapper ici le
   * ferait afficher en double (&quot; au lieu de ").
   *
   * @return array<string, string>
   */
  private function getFromAddresses(): array {
    return Utils::getFromAddressOptions();
  }

  private function getFinancialTypes(): array {
    $options = [];
    try {
      $result = \civicrm_api3('FinancialType', 'get', [
        'is_active' => 1,
        'options'   => ['limit' => 0, 'sort' => 'name'],
        'return'    => ['id', 'name'],
      ]);
      foreach ($result['values'] as $ft) {
        $options[$ft['id']] = $ft['name'];
      }
    }
    catch (\Throwable $e) {}
    return $options;
  }

  private function getContributionStatuses(): array {
    $options = [];
    try {
      $result = \civicrm_api3('OptionValue', 'get', [
        'option_group_id' => 'contribution_status',
        'is_active'       => 1,
        'options'         => ['limit' => 0, 'sort' => 'weight'],
        'return'          => ['value', 'label'],
      ]);
      foreach ($result['values'] as $st) {
        $options[$st['value']] = $st['label'];
      }
    }
    catch (\Throwable $e) {}
    return $options;
  }

  // -------------------------------------------------------------------------
  // Synchronisation des agendas
  // -------------------------------------------------------------------------

  private function buildSyncStatus(): array {
    $provider  = new InformaniakCalDavProvider();
    $available = $provider->isAvailable();
    $rows = [];

    foreach (Therapist::getAll(FALSE) as $t) {
      $url    = trim((string) ($t['calendar_url'] ?? ''));
      $status = 'no_url';

      if ($url !== '' && $available) {
        $status = $this->probe($url) ? 'ok' : 'error';
      }
      elseif ($url !== '') {
        $status = 'no_credentials';
      }

      $rows[] = [
        'name'     => $t['display_name'],
        'status'   => $status,
        'edit_url' => \CRM_Utils_System::url('civicrm/booking/therapist/edit', ['id' => $t['id'], 'reset' => 1]),
      ];
    }
    return $rows;
  }

  /**
   * Vérifier qu'un agenda répond (PROPFIND).
   */
  private function probe(string $url): bool {
    $url = preg_replace('/\?.*$/', '', trim($url));
    if ($url === '') return FALSE;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_CUSTOMREQUEST  => 'PROPFIND',
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_TIMEOUT        => 8,
      CURLOPT_CONNECTTIMEOUT => 4,
      CURLOPT_HTTPHEADER     => ['Depth: 0'],
      CURLOPT_USERPWD        => Utils::getSetting('caldav_user', '') . ':' . Utils::getSetting('caldav_password', ''),
      CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $code >= 200 && $code < 300;
  }

  private function countOrphans(): int {
    return (int) \CRM_Core_DAO::singleValueQuery(
      "SELECT COUNT(*) FROM civicrm_booking_appointment
       WHERE status = 'cancelled' AND caldav_event_id IS NOT NULL AND caldav_event_id != ''"
    );
  }

  /**
   * Retirer des agendas les événements des rendez-vous annulés.
   */
  private function cleanupOrphans(): void {
    $provider = new InformaniakCalDavProvider();
    if (!$provider->isAvailable()) {
      \CRM_Core_Session::setStatus(ts('Identifiants CalDAV manquants.'), ts('Impossible'), 'error');
      return;
    }

    $dao = \CRM_Core_DAO::executeQuery(
      "SELECT id, therapist_id, caldav_event_id
       FROM civicrm_booking_appointment
       WHERE status = 'cancelled' AND caldav_event_id IS NOT NULL AND caldav_event_id != ''"
    );

    $done = 0;
    while ($dao->fetch()) {
      try {
        $provider->deleteEvent((int) $dao->therapist_id, $dao->caldav_event_id);
        \CRM_Core_DAO::executeQuery(
          'UPDATE civicrm_booking_appointment SET caldav_event_id = NULL WHERE id = %1',
          [1 => [(int) $dao->id, 'Integer']]
        );
        $done++;
      }
      catch (\Throwable $e) {
        Utils::logError('Nettoyage CalDAV', ['appointment_id' => $dao->id, 'error' => $e->getMessage()]);
      }
    }

    \CRM_Core_Session::setStatus(
      ts('%1 événement(s) retiré(s) des agendas.', [1 => $done]),
      ts('Nettoyage terminé'), 'success'
    );
  }
}
