<?php
namespace CRM\Booking\Service;

use CRM\Booking\BAO\Appointment;
use CRM\Booking\BAO\AppointmentType;
use CRM\Booking\BAO\Therapist;
use CRM\Booking\Service\TherapistSelector\TherapistSelectorFactory;
use CRM\Booking\Service\CalendarProvider\CalendarProviderInterface;
use CRM\Booking\Service\CalendarProvider\NullCalendarProvider;
use CRM\Booking\Service\CalendarProvider\InformaniakCalDavProvider;
use CRM\Booking\Utils;

/**
 * BookingService — orchestration complète d'une réservation :
 *   1. Validation du créneau
 *   2. Lookup / création du contact CiviCRM
 *   3. Sélection du intervenant·e
 *   4. Création du RDV + Activity CiviCRM
 *   5. Notifications email
 *   6. Hook calendrier externe (Phase 2)
 */
class BookingService {

  private SlotService $slotService;
  private NotificationService $notificationService;
  private CalendarProviderInterface $calendarProvider;

  public function __construct(
    ?SlotService $slotService = NULL,
    ?NotificationService $notificationService = NULL,
    ?CalendarProviderInterface $calendarProvider = NULL
  ) {
    if ($calendarProvider === NULL) {
      $provider = new InformaniakCalDavProvider();
      $calendarProvider = $provider->isAvailable() ? $provider : new NullCalendarProvider();
    }
    $this->slotService         = $slotService ?? new SlotService();
    $this->notificationService = $notificationService ?? new NotificationService();
    $this->calendarProvider    = $calendarProvider;
  }

  /**
   * Créer un rendez-vous complet.
   *
   * $data = [
   *   'appointment_type_id' => int,
   *   'start_datetime'      => 'YYYY-MM-DD HH:MM:SS',
   *   'contact'             => [
   *     'first_name', 'last_name', 'email', 'phone' (optionnel)
   *   ],
   *   'wp_user_id'          => int|null,  // si client connecté
   *   'notes'               => string|null,
   * ]
   *
   * Retourne ['success' => true, 'appointment_id' => int]
   *      ou  ['success' => false, 'error' => string]
   */
  public function book(array $data): array {
    try {
      $typeId        = (int) $data['appointment_type_id'];
      $startDatetime = $data['start_datetime'];
      $contactData   = $data['contact'];

      // 1. Charger le type
      $type = AppointmentType::getById($typeId);
      if (!$type || !$type['is_active']) {
        return ['success' => FALSE, 'error' => 'Type de rendez-vous invalide.'];
      }

      // 2. Lookup / création contact CiviCRM
      $contactResult = $this->resolveContact($contactData, $type, $data['wp_user_id'] ?? NULL);
      if (!$contactResult['success']) {
        return $contactResult;
      }
      $contactId = $contactResult['contact_id'];

      // 3. Trouver les intervenant·es disponibles sur ce créneau
      $candidates = $this->getAvailableCandidates($typeId, $startDatetime);
      if (empty($candidates)) {
        return ['success' => FALSE, 'error' => 'Ce créneau n\'est plus disponible.'];
      }

      // 4. Sélectionner le intervenant·e selon la stratégie configurée
      $selector   = TherapistSelectorFactory::create($type['therapist_selector']);
      $therapist  = $selector->select($candidates, $startDatetime);
      $therapistId = (int) $therapist['id'];

      // 5. La disponibilité a déjà été vérifiée à l'étape 3 (getAvailableCandidates)

      // 6. Calculer end_datetime
      $endDatetime = (new \DateTime($startDatetime))
        ->modify('+' . $type['duration_minutes'] . ' minutes')
        ->format('Y-m-d H:i:s');

      // 7. Créer le RDV + Activity CiviCRM
      $locationId = $this->slotService->locationForDate(
        $therapistId,
        substr($startDatetime, 0, 10)
      );

      $appointmentId = Appointment::create([
        'appointment_type_id' => $typeId,
        'therapist_id'        => $therapistId,
        'contact_id'          => $contactId,
        'start_datetime'      => $startDatetime,
        'end_datetime'        => $endDatetime,
        'status'              => Appointment::STATUS_CONFIRMED,
        'notes'               => $data['notes'] ?? NULL,
        'location_id'         => $locationId,
      ]);

      // 8. Agendas externes — non bloquant : un échec ici ne perd pas le RDV
      $appointment = Appointment::getById($appointmentId);
      $this->syncToCalendars($appointmentId, $appointment, $locationId);

      // 9. Emails de confirmation — NON BLOQUANT également
      try {
        $this->notificationService->sendConfirmation($appointment, $contactData);
      }
      catch (\Throwable $e) {
        Utils::logError('Envoi email échoué — RDV créé', [
          'appointment_id' => $appointmentId,
          'error'          => $e->getMessage(),
        ]);
      }

      return ['success' => TRUE, 'appointment_id' => $appointmentId];
    }
    catch (\Throwable $e) {
      Utils::logError('Erreur BookingService::book()', ['error' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Une erreur est survenue. Veuillez réessayer.'];
    }
  }

  /**
   * Synchroniser un rendez-vous déjà créé avec les agendas externes.
   * Utilisé par la création en backoffice, qui n'emprunte pas book().
   */
  public function syncExisting(int $appointmentId, ?array $appointment, ?int $locationId): void {
    $this->syncToCalendars($appointmentId, $appointment, $locationId);
  }

  /**
   * Envoyer les confirmations pour un rendez-vous déjà créé.
   * Les coordonnées sont relues depuis le contact CiviCRM.
   */
  public function notifyExisting(?array $appointment, int $contactId): void {
    if (!$appointment) return;

    try {
      $contact = \civicrm_api3('Contact', 'getsingle', [
        'id'     => $contactId,
        'return' => ['first_name', 'last_name', 'email'],
      ]);
      $this->notificationService->sendConfirmation($appointment, [
        'first_name' => $contact['first_name'] ?? '',
        'last_name'  => $contact['last_name']  ?? '',
        'email'      => $contact['email']      ?? '',
      ]);
    }
    catch (\Throwable $e) {
      Utils::logError('Confirmation non envoyée', [
        'appointment_id' => $appointment['id'] ?? 0,
        'error'          => $e->getMessage(),
      ]);
    }
  }

  /**
   * Déposer le rendez-vous dans l'agenda de l'intervenant et, le cas échéant,
   * dans celui du local. L'événement du local ne porte que le nom de
   * l'intervenant : aucune donnée patient n'y figure.
   */
  private function syncToCalendars(int $appointmentId, ?array $appointment, ?int $locationId): void {
    if (!$appointment || !$this->calendarProvider->isAvailable()) {
      return;
    }

    // Agenda personnel de l'intervenant
    try {
      $eventId = $this->calendarProvider->createEvent($appointment);
      if ($eventId) {
        \CRM_Core_DAO::executeQuery(
          'UPDATE civicrm_booking_appointment SET caldav_event_id = %1 WHERE id = %2',
          [1 => [$eventId, 'String'], 2 => [$appointmentId, 'Integer']]
        );
      }
    }
    catch (\Throwable $e) {
      Utils::logError('Agenda intervenant — dépôt impossible', [
        'appointment_id' => $appointmentId,
        'error'          => $e->getMessage(),
      ]);
    }

    if (!$locationId) {
      return;
    }

    // Agenda du local : titre limité au nom de l'intervenant
    try {
      $locationUrl = \CRM\Booking\BAO\Location::getCalendarUrl($locationId);
      if (!$locationUrl) return;

      $eventId = $this->calendarProvider->createEventInCalendar(
        $locationUrl,
        $appointment['start_datetime'],
        $appointment['end_datetime'],
        $appointment['therapist_name'] ?? 'Occupé'
      );

      if ($eventId) {
        \CRM_Core_DAO::executeQuery(
          'UPDATE civicrm_booking_appointment SET location_event_id = %1 WHERE id = %2',
          [1 => [$eventId, 'String'], 2 => [$appointmentId, 'Integer']]
        );
      }
    }
    catch (\Throwable $e) {
      Utils::logError('Agenda du local — dépôt impossible', [
        'appointment_id' => $appointmentId,
        'location_id'    => $locationId,
        'error'          => $e->getMessage(),
      ]);
    }
  }

  /**
   * Résoudre le contact CiviCRM selon les règles du type de RDV.
   */
  private function resolveContact(array $contactData, array $type, ?int $wpUserId): array {
    // Contact imposé par l'appelant (fiche de l'utilisateur connecté). Cette
    // valeur n'est jamais lue dans les données du formulaire : le
    // gestionnaire public la pose lui-même, après vérification de la session.
    $forcedId = (int) ($contactData['forced_contact_id'] ?? 0);
    if ($forcedId) {
      return ['success' => TRUE, 'contact_id' => $forcedId];
    }

    $email = trim($contactData['email'] ?? '');

    if (empty($email)) {
      return ['success' => FALSE, 'error' => 'L\'adresse email est requise.'];
    }

    // Chercher contact existant par email
    $existingContactId = $this->findContactByEmail($email);

    // RDV réservable uniquement par contact existant
    if ($type['requires_existing_contact'] && !$existingContactId) {
      return [
        'success' => FALSE,
        'error'   => MessageService::get(MessageService::CONTACT_NOT_FOUND),
      ];
    }

    // Contact existant → l'utiliser
    if ($existingContactId) {
      return ['success' => TRUE, 'contact_id' => $existingContactId];
    }

    // Nouveau contact → créer dans CiviCRM
    $contactId = $this->createContact($contactData);
    if (!$contactId) {
      return ['success' => FALSE, 'error' => 'Impossible de créer votre profil. Veuillez réessayer.'];
    }

    // Créer compte WP si requis
    if ($type['requires_account_creation']) {
      $this->createWordPressAccount($contactData, $contactId);
    }

    return ['success' => TRUE, 'contact_id' => $contactId];
  }

  /**
   * Chercher un contact CiviCRM par email.
   */
  private function findContactByEmail(string $email): ?int {
    try {
      $result = \civicrm_api3('Contact', 'get', [
        'email'      => $email,
        'is_deleted' => 0,
        'return'     => 'id',
        'options'    => ['limit' => 1],
      ]);
      if ($result['count'] > 0) {
        return (int) reset($result['values'])['id'];
      }
    }
    catch (\Throwable $e) {
      Utils::logError('findContactByEmail failed', ['email' => $email, 'error' => $e->getMessage()]);
    }
    return NULL;
  }

  /**
   * Créer un contact CiviCRM (type Individual).
   */
  private function createContact(array $data): ?int {
    try {
      $params = [
        'contact_type' => 'Individual',
        'first_name'   => trim($data['first_name'] ?? ''),
        'last_name'    => trim($data['last_name'] ?? ''),
        'email'        => trim($data['email']),
      ];
      if (!empty($data['phone'])) {
        $params['phone'] = trim($data['phone']);
      }
      $result = \civicrm_api3('Contact', 'create', $params);
      return (int) $result['id'];
    }
    catch (\Throwable $e) {
      Utils::logError('createContact failed', ['error' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Créer un compte WordPress pour un nouveau patient (si requires_account_creation).
   * CMS-spécifique — no-op si pas sous WordPress.
   */
  private function createWordPressAccount(array $data, int $contactId): void {
    if (!function_exists('wp_create_user')) return;
    $email    = trim($data['email']);
    $username = sanitize_user(strtolower($data['first_name'] . '.' . $data['last_name']));
    $password = wp_generate_password(12, FALSE);

    if (username_exists($username)) {
      $username .= rand(10, 99);
    }
    if (email_exists($email)) return; // Compte WP déjà existant

    $wpUserId = wp_create_user($username, $password, $email);
    if (is_wp_error($wpUserId)) {
      Utils::logError('createWordPressAccount failed', ['error' => $wpUserId->get_error_message()]);
      return;
    }
    // Lier le contact CiviCRM au user WP
    try {
      \civicrm_api3('UFMatch', 'create', [
        'uf_id'      => $wpUserId,
        'contact_id' => $contactId,
        'uf_name'    => $email,
      ]);
    }
    catch (\Throwable $e) {}
  }

  /**
   * Récupérer les intervenant·es disponibles sur un créneau précis.
   */
  private function getAvailableCandidates(int $typeId, string $startDatetime): array {
    $therapists = Therapist::getByAppointmentType($typeId);
    $available  = [];
    foreach ($therapists as $t) {
      if ($this->slotService->isSlotAvailable($typeId, (int) $t['id'], $startDatetime)) {
        $available[] = $t;
      }
    }
    return $available;
  }
}
