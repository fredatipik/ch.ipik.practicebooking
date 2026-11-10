<?php
namespace CRM\Booking\Form;

use CRM\Booking\BAO\Appointment as BAOAppointment;
use CRM\Booking\BAO\AppointmentType;
use CRM\Booking\BAO\Location;
use CRM\Booking\BAO\Therapist;
use CRM\Booking\Service\BookingService;
use CRM\Booking\Service\SlotService;
use CRM\Booking\Utils;

/**
 * Formulaire — création ou modification d'un rendez-vous depuis le backoffice.
 *
 * Création. Deux façons de fixer l'horaire : choisir un créneau libre parmi
 * ceux que propose le calcul de disponibilité, ou saisir date et heure
 * directement. La saisie libre permet de caler un rendez-vous en dehors des
 * plages déclarées — utile au téléphone, pour une urgence.
 *
 * Modification (paramètre « id »). Un troisième choix, « conserver l'horaire
 * actuel », est proposé par défaut. Le patient ne se modifie pas. Les
 * événements d'agenda sont retirés puis recréés, et l'e-mail de convocation
 * peut être renvoyé.
 */
class Appointment extends \CRM_Core_Form {

  protected int $_therapistId = 0;
  protected int $_contactId   = 0;

  /** Rendez-vous modifié ; 0 pour une création */
  protected int $_id = 0;
  protected ?array $_appointment = NULL;

  public function preProcess(): void {
    parent::preProcess();
    \CRM_Core_Permission::check('access booking')
      || \CRM_Core_Error::statusBounce(ts('Accès refusé.'));

    // Pré-sélection possible depuis l'agenda d'un·e intervenant·e
    $this->_therapistId = (int) \CRM_Utils_Request::retrieve('therapist_id', 'Positive', $this, FALSE, 0);
    $this->_contactId   = (int) \CRM_Utils_Request::retrieve('cid', 'Positive', $this, FALSE, 0);
    $this->_id          = (int) \CRM_Utils_Request::retrieve('id', 'Positive', $this, FALSE, 0);

    if ($this->_id) {
      $this->_appointment = BAOAppointment::getById($this->_id);
      if (!$this->_appointment) {
        \CRM_Core_Error::statusBounce(ts('Rendez-vous introuvable.'));
      }
      if (($this->_appointment['status'] ?? '') === 'cancelled') {
        \CRM_Core_Error::statusBounce(ts('Un rendez-vous annulé ne peut pas être modifié.'));
      }
      $this->setTitle(ts('Modifier le rendez-vous'));
    }
    else {
      $this->setTitle(ts('Nouveau rendez-vous'));
    }
    Utils::setBreadCrumb();
  }

  public function buildQuickForm(): void {
    $isEdit = $this->_id > 0;
    $this->assign('isEditMode', $isEdit);

    // ---- Patient ----
    if ($isEdit) {
      $this->assign('patientName', $this->_appointment['contact_name'] ?? '');
      $this->assign('patientUrl', \CRM_Utils_System::url('civicrm/contact/view', [
        'cid' => (int) $this->_appointment['contact_id'],
      ]));
      $this->assign('currentWhen', Utils::formatDatetime($this->_appointment['start_datetime']));
      $this->assign('currentPlace', $this->_appointment['location_name'] ?? '');
      $this->assign('manualDate', substr($this->_appointment['start_datetime'], 0, 10));
      $this->assign('manualTime', substr($this->_appointment['start_datetime'], 11, 5));
    }
    else {
      $this->addEntityRef('contact_id', ts('Patient·e'), [
        'api'         => ['params' => ['contact_type' => 'Individual']],
        'create'      => TRUE,
        'placeholder' => ts('Rechercher ou créer un contact…'),
      ], TRUE);
      $this->assign('manualDate', '');
      $this->assign('manualTime', '');
    }

    // ---- Type de rendez-vous ----
    $types = AppointmentType::getOptions();
    $this->add('select', 'appointment_type_id', ts('Type de rendez-vous'),
      ['' => ts('— Choisir —')] + $types, TRUE);

    // ---- Intervenant·e ----
    $therapists = [];
    foreach (Therapist::getAll() as $t) {
      $therapists[$t['id']] = $t['display_name'];
    }
    $this->add('select', 'therapist_id', ts('Intervenant·e'),
      ['' => ts('— Choisir —')] + $therapists, TRUE);

    // ---- Horaire ----
    $modes = [
      'free'   => ts('Choisir parmi les créneaux disponibles'),
      'manual' => ts('Saisir une date et une heure'),
    ];
    if ($isEdit) {
      $modes = ['keep' => ts('Conserver l\'horaire actuel')] + $modes;
    }
    $this->add('select', 'slot_mode', ts('Horaire'), $modes, TRUE);

    // Créneaux libres, alimentés en JavaScript
    $this->add('select', 'slot', ts('Créneau'), ['' => ts('— Choisir un type et un·e intervenant·e —')]);

    // Saisie libre
    $this->add('text', 'manual_date', ts('Date'));
    $this->add('text', 'manual_time', ts('Heure'));

    // ---- Local ----
    $locations = Location::getOptions();
    if ($isEdit) {
      $locationChoices = [
        'keep' => ts('Conserver le local actuel'),
        ''     => ts('Déduire automatiquement'),
        '0'    => ts('Hors local'),
      ] + $locations;
    }
    else {
      $locationChoices = [
        ''  => ts('— Déduire automatiquement —'),
        '0' => ts('Hors local'),
      ] + $locations;
    }
    $this->add('select', 'location_id', ts('Local'), $locationChoices);

    $this->add('textarea', 'notes', ts('Notes'), ['rows' => 3, 'maxlength' => 500]);

    $this->add('checkbox', 'send_notifications',
      $isEdit ? ts('Renvoyer l\'e-mail de convocation') : ts('Envoyer les e-mails de confirmation'));

    $this->addButtons([[
      'type'      => 'submit',
      'name'      => $isEdit ? ts('Enregistrer les modifications') : ts('Créer le rendez-vous'),
      'isDefault' => TRUE,
    ]]);

    $this->assign('slotsURL', \CRM_Utils_System::url('civicrm/booking/appointment/slots'));
    $this->assign('cancelURL', $this->returnUrl());
  }

  public function setDefaultValues(): array {
    if ($this->_id) {
      $a = $this->_appointment;
      return [
        'appointment_type_id' => $a['appointment_type_id'],
        'therapist_id'        => $a['therapist_id'],
        'slot_mode'           => 'keep',
        'location_id'         => 'keep',
        'notes'               => $a['notes'] ?? '',
        // Cochée automatiquement par le formulaire dès qu'un changement
        // concerne le patient (horaire, type, intervenant·e)
        'send_notifications'  => 0,
      ];
    }

    $defaults = [
      'slot_mode'          => 'free',
      'send_notifications' => 1,
    ];

    // Intervenant·e passé·e en paramètre, sinon la personne connectée
    if ($this->_therapistId) {
      $defaults['therapist_id'] = $this->_therapistId;
    }
    else {
      $loggedIn = (int) \CRM_Core_Session::getLoggedInContactID();
      $self     = $loggedIn ? Therapist::getByContactId($loggedIn) : NULL;
      if ($self) {
        $defaults['therapist_id'] = $self['id'];
      }
    }

    return $defaults;
  }

  public function validate(): bool {
    parent::validate();
    $values = $this->exportValues();
    $mode   = $values['slot_mode'] ?? 'free';

    // Horaire conservé : rien à contrôler
    if ($mode === 'keep') {
      return empty($this->_errors);
    }

    if ($mode === 'free') {
      if (empty($values['slot'])) {
        $this->_errors['slot'] = ts('Choisissez un créneau, ou passez en saisie libre.');
      }
      return empty($this->_errors);
    }

    // Saisie libre
    $date = trim($values['manual_date'] ?? '');
    $time = trim($values['manual_time'] ?? '');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
      $this->_errors['manual_date'] = ts('Date attendue au format AAAA-MM-JJ.');
    }
    if (!preg_match('/^\d{1,2}:\d{2}$/', $time)) {
      $this->_errors['manual_time'] = ts('Heure attendue au format HH:MM.');
    }

    return empty($this->_errors);
  }

  public function postProcess(): void {
    if ($this->_id) {
      $this->updateAppointment();
      return;
    }
    $this->createAppointment();
  }

  // -------------------------------------------------------------------------
  // Création
  // -------------------------------------------------------------------------

  private function createAppointment(): void {
    $values    = $this->exportValues();
    $contactId = (int) $values['contact_id'];
    $typeId    = (int) $values['appointment_type_id'];
    $therapId  = (int) $values['therapist_id'];

    $start = $this->startFromValues($values);

    $type = AppointmentType::getById($typeId);
    if (!$type) {
      \CRM_Core_Session::setStatus(ts('Type de rendez-vous introuvable.'), ts('Erreur'), 'error');
      \CRM_Utils_System::redirect($this->returnUrl());
    }

    $end        = $this->endFrom($start, $type);
    $locationId = $this->locationFromChoice($values['location_id'] ?? '', $therapId, $start, NULL);

    $appointmentId = BAOAppointment::create([
      'appointment_type_id' => $typeId,
      'therapist_id'        => $therapId,
      'contact_id'          => $contactId,
      'start_datetime'      => $start,
      'end_datetime'        => $end,
      'status'              => BAOAppointment::STATUS_CONFIRMED,
      'notes'               => $values['notes'] ?? NULL,
      'location_id'         => $locationId,
    ]);

    $appointment = BAOAppointment::getById($appointmentId);

    // Agendas externes et notifications, via le service commun
    $booking = new BookingService();
    $booking->syncExisting($appointmentId, $appointment, $locationId);

    if (!empty($values['send_notifications'])) {
      $booking->notifyExisting($appointment, $contactId);
    }

    \CRM_Core_Session::setStatus(
      ts('Rendez-vous du %1 créé.', [1 => Utils::formatDatetime($start)]),
      ts('Succès'),
      'success'
    );
    \CRM_Utils_System::redirect($this->returnUrl());
  }

  // -------------------------------------------------------------------------
  // Modification
  // -------------------------------------------------------------------------

  private function updateAppointment(): void {
    $values    = $this->exportValues();
    $old       = $this->_appointment;
    $contactId = (int) $old['contact_id'];
    $typeId    = (int) $values['appointment_type_id'];
    $therapId  = (int) $values['therapist_id'];

    $type = AppointmentType::getById($typeId);
    if (!$type) {
      \CRM_Core_Session::setStatus(ts('Type de rendez-vous introuvable.'), ts('Erreur'), 'error');
      \CRM_Utils_System::redirect($this->returnUrl());
    }

    $start      = $this->startFromValues($values, $old['start_datetime']);
    $end        = $this->endFrom($start, $type);
    $locationId = $this->locationFromChoice($values['location_id'] ?? 'keep', $therapId, $start, $old);

    // 1. Retirer les anciens événements : ils se retrouvent par l'ancien·ne
    //    intervenant·e et l'ancien local, qui vont peut-être changer.
    BAOAppointment::detachFromCalendars($this->_id);

    // 2. Mettre à jour le rendez-vous et son activité
    BAOAppointment::update($this->_id, [
      'appointment_type_id' => $typeId,
      'therapist_id'        => $therapId,
      'start_datetime'      => $start,
      'end_datetime'        => $end,
      'notes'               => $values['notes'] ?? '',
      'location_id'         => $locationId,
    ]);

    $updated = BAOAppointment::getById($this->_id);

    // 3. Recréer les événements, puis renvoyer la convocation si demandé
    $booking = new BookingService();
    $booking->syncExisting($this->_id, $updated, $locationId);

    $mailed = FALSE;
    if (!empty($values['send_notifications'])) {
      $booking->notifyExisting($updated, $contactId);
      $mailed = TRUE;
    }

    \CRM_Core_Session::setStatus(
      $mailed
        ? ts('Rendez-vous modifié (%1). La convocation a été renvoyée.', [1 => Utils::formatDatetime($start)])
        : ts('Rendez-vous modifié (%1).', [1 => Utils::formatDatetime($start)]),
      ts('Succès'),
      'success'
    );
    \CRM_Utils_System::redirect($this->returnUrl());
  }

  // -------------------------------------------------------------------------
  // Aides communes
  // -------------------------------------------------------------------------

  /**
   * Début du rendez-vous selon le mode d'horaire choisi.
   *
   * @param string|NULL $current Horaire actuel, pour le mode « conserver ».
   */
  private function startFromValues(array $values, ?string $current = NULL): string {
    $mode = $values['slot_mode'] ?? 'free';

    if ($mode === 'keep' && $current) {
      return $current;
    }
    if ($mode === 'free') {
      return $values['slot'];
    }
    return sprintf('%s %s:00', $values['manual_date'], $this->padTime($values['manual_time']));
  }

  private function endFrom(string $start, array $type): string {
    return (new \DateTime($start))
      ->modify('+' . (int) $type['duration_minutes'] . ' minutes')
      ->format('Y-m-d H:i:s');
  }

  /**
   * Local retenu selon le choix du formulaire.
   *
   * 'keep' conserve celui du rendez-vous existant, '0' signifie « hors
   * local », la chaîne vide déduit le local de la journée de travail.
   *
   * @param array|NULL $existing Rendez-vous actuel, pour « conserver ».
   */
  private function locationFromChoice(string $chosen, int $therapistId, string $start, ?array $existing): ?int {
    if ($chosen === 'keep') {
      return !empty($existing['location_id']) ? (int) $existing['location_id'] : NULL;
    }
    if ($chosen === '0') {
      return NULL;
    }
    if ($chosen !== '') {
      return (int) $chosen;
    }
    return (new SlotService())->locationForDate($therapistId, substr($start, 0, 10));
  }

  /**
   * '9:00' → '09:00'
   */
  private function padTime(string $time): string {
    [$h, $m] = array_pad(explode(':', trim($time)), 2, '00');
    return sprintf('%02d:%02d', (int) $h, (int) $m);
  }

  private function returnUrl(): string {
    return $this->_contactId
      ? \CRM_Utils_System::url('civicrm/booking/therapist-agenda', ['cid' => $this->_contactId, 'reset' => 1])
      : \CRM_Utils_System::url('civicrm/booking/appointments', 'reset=1');
  }
}
