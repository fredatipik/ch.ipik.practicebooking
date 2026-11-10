<?php
namespace CRM\Booking\BAO;

/**
 * BAO Appointment — CRUD rendez-vous et création d'Activity CiviCRM.
 */
class Appointment {

  const STATUS_PENDING   = 'pending';
  const STATUS_CONFIRMED = 'confirmed';
  const STATUS_CANCELLED = 'cancelled';
  const STATUS_COMPLETED = 'completed';

  /**
   * Récupérer un RDV par ID (avec données jointes).
   */
  public static function getById(int $id): ?array {
    $dao = \CRM_Core_DAO::executeQuery(
      'SELECT a.*,
              at.label AS type_label, at.duration_minutes, at.color AS type_color,
              c.display_name AS contact_name,
              t_contact.display_name AS therapist_name,
              l.name AS location_name,
              l.address AS location_address
       FROM civicrm_booking_appointment a
       JOIN civicrm_booking_appointment_type at ON at.id = a.appointment_type_id
       JOIN civicrm_contact c ON c.id = a.contact_id
       JOIN civicrm_booking_therapist t ON t.id = a.therapist_id
       JOIN civicrm_contact t_contact ON t_contact.id = t.contact_id
       LEFT JOIN civicrm_booking_location l ON l.id = a.location_id
       WHERE a.id = %1',
      [1 => [$id, 'Integer']]
    );
    return $dao->fetch() ? $dao->toArray() : NULL;
  }

  /**
   * Lister les RDV d'un intervenant·e sur une période.
   */
  public static function getForTherapist(int $therapistId, string $dateFrom, string $dateTo): array {
    $dao = \CRM_Core_DAO::executeQuery(
      'SELECT a.*,
              at.label AS type_label, at.color AS type_color,
              c.display_name AS contact_name
       FROM civicrm_booking_appointment a
       JOIN civicrm_booking_appointment_type at ON at.id = a.appointment_type_id
       JOIN civicrm_contact c ON c.id = a.contact_id
       WHERE a.therapist_id = %1
         AND a.start_datetime >= %2
         AND a.start_datetime <= %3
         AND a.status != %4
       ORDER BY a.start_datetime',
      [
        1 => [$therapistId, 'Integer'],
        2 => [$dateFrom . ' 00:00:00', 'String'],
        3 => [$dateTo . ' 23:59:59', 'String'],
        4 => [self::STATUS_CANCELLED, 'String'],
      ]
    );
    $results = [];
    while ($dao->fetch()) {
      $results[] = $dao->toArray();
    }
    return $results;
  }

  /**
   * Lister tous les RDV (vue admin) avec filtres optionnels.
   */
  public static function getAll(array $filters = [], int $limit = 50, int $offset = 0): array {
    $where = ['a.status != "cancelled"'];
    $params = [];
    $i = 1;

    if (!empty($filters['therapist_id'])) {
      $where[] = "a.therapist_id = %{$i}";
      $params[$i++] = [(int) $filters['therapist_id'], 'Integer'];
    }
    if (!empty($filters['contact_id'])) {
      $where[] = "a.contact_id = %{$i}";
      $params[$i++] = [(int) $filters['contact_id'], 'Integer'];
    }
    if (!empty($filters['date_from'])) {
      $where[] = "a.start_datetime >= %{$i}";
      $params[$i++] = [$filters['date_from'] . ' 00:00:00', 'String'];
    }
    if (!empty($filters['date_to'])) {
      $where[] = "a.start_datetime <= %{$i}";
      $params[$i++] = [$filters['date_to'] . ' 23:59:59', 'String'];
    }
    if (!empty($filters['status'])) {
      $where[] = "a.status = %{$i}";
      $params[$i++] = [$filters['status'], 'String'];
    }

    $order = (($filters['order'] ?? 'DESC') === 'ASC') ? 'ASC' : 'DESC';

    // LIMIT et OFFSET directement dans le SQL pour éviter les problèmes d'index
    $sql = 'SELECT a.*,
                   at.label AS type_label, at.color AS type_color,
                   c.display_name AS contact_name,
                   t_contact.display_name AS therapist_name,
                   l.name AS location_name, l.color AS location_color
            FROM civicrm_booking_appointment a
            JOIN civicrm_booking_appointment_type at ON at.id = a.appointment_type_id
            JOIN civicrm_contact c ON c.id = a.contact_id
            JOIN civicrm_booking_therapist t ON t.id = a.therapist_id
            JOIN civicrm_contact t_contact ON t_contact.id = t.contact_id
            LEFT JOIN civicrm_booking_location l ON l.id = a.location_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY a.start_datetime ' . $order . '
            LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

    $dao = \CRM_Core_DAO::executeQuery($sql, $params);
    $results = [];
    while ($dao->fetch()) {
      $results[] = $dao->toArray();
    }
    return $results;
  }

  /**
   * Créer un rendez-vous + Activity CiviCRM associée.
   * Retourne l'ID du RDV créé.
   */
  public static function create(array $params): int {
    // 1. Insérer le RDV
    // CRM_Core_DAO refuse NULL sur un paramètre typé : le local absent
    // passe donc par une requête où il est posé littéralement.
    $common = [
      1 => [(int) $params['appointment_type_id'], 'Integer'],
      2 => [(int) $params['therapist_id'], 'Integer'],
      3 => [(int) $params['contact_id'], 'Integer'],
      4 => [$params['start_datetime'], 'String'],
      5 => [$params['end_datetime'], 'String'],
      6 => [$params['status'] ?? self::STATUS_CONFIRMED, 'String'],
      7 => [(string) ($params['notes'] ?? ''), 'String'],
    ];

    $locationId = !empty($params['location_id']) ? (int) $params['location_id'] : NULL;

    if ($locationId === NULL) {
      \CRM_Core_DAO::executeQuery(
        'INSERT INTO civicrm_booking_appointment
           (appointment_type_id, therapist_id, contact_id,
            start_datetime, end_datetime, status, notes, location_id)
         VALUES (%1, %2, %3, %4, %5, %6, %7, NULL)',
        $common
      );
    }
    else {
      \CRM_Core_DAO::executeQuery(
        'INSERT INTO civicrm_booking_appointment
           (appointment_type_id, therapist_id, contact_id,
            start_datetime, end_datetime, status, notes, location_id)
         VALUES (%1, %2, %3, %4, %5, %6, %7, %8)',
        $common + [8 => [$locationId, 'Integer']]
      );
    }
    $appointmentId = (int) \CRM_Core_DAO::singleValueQuery('SELECT LAST_INSERT_ID()');

    // 2. Créer l'Activity CiviCRM
    $activityId = self::createActivity($appointmentId, $params);

    // 3. Lier l'activity au RDV
    if ($activityId) {
      \CRM_Core_DAO::executeQuery(
        'UPDATE civicrm_booking_appointment SET civicrm_activity_id = %1 WHERE id = %2',
        [
          1 => [$activityId, 'Integer'],
          2 => [$appointmentId, 'Integer'],
        ]
      );
    }

    return $appointmentId;
  }

  /**
   * Créer l'Activity CiviCRM correspondant au RDV.
   */
  /**
   * Modifier un rendez-vous : type, intervenant·e, horaire, local, notes.
   *
   * Le patient ne change pas : un autre patient serait un autre rendez-vous.
   * Les événements d'agenda ne sont pas touchés ici — l'appelant les retire
   * avant (detachFromCalendars) et les recrée après, car ils dépendent de
   * l'ancien comme du nouvel·le intervenant·e et local.
   */
  public static function update(int $id, array $params): void {
    // CRM_Core_DAO refuse NULL sur un paramètre typé : le local absent passe
    // donc par une requête où il est posé littéralement.
    $common = [
      1 => [(int) $params['appointment_type_id'], 'Integer'],
      2 => [(int) $params['therapist_id'], 'Integer'],
      3 => [$params['start_datetime'], 'String'],
      4 => [$params['end_datetime'], 'String'],
      5 => [(string) ($params['notes'] ?? ''), 'String'],
      6 => [$id, 'Integer'],
    ];

    $locationId = !empty($params['location_id']) ? (int) $params['location_id'] : NULL;

    if ($locationId === NULL) {
      \CRM_Core_DAO::executeQuery(
        'UPDATE civicrm_booking_appointment
         SET appointment_type_id = %1, therapist_id = %2, start_datetime = %3,
             end_datetime = %4, notes = %5, location_id = NULL
         WHERE id = %6',
        $common
      );
    }
    else {
      \CRM_Core_DAO::executeQuery(
        'UPDATE civicrm_booking_appointment
         SET appointment_type_id = %1, therapist_id = %2, start_datetime = %3,
             end_datetime = %4, notes = %5, location_id = %7
         WHERE id = %6',
        $common + [7 => [$locationId, 'Integer']]
      );
    }

    self::updateActivity($id, $params);
  }

  /**
   * Répercuter la modification sur l'activité CiviCRM liée, si elle existe.
   */
  private static function updateActivity(int $id, array $params): void {
    $activityId = (int) \CRM_Core_DAO::singleValueQuery(
      'SELECT civicrm_activity_id FROM civicrm_booking_appointment WHERE id = %1',
      [1 => [$id, 'Integer']]
    );
    if (!$activityId) {
      return;
    }

    try {
      $type = AppointmentType::getById((int) $params['appointment_type_id']);

      $apiParams = [
        'id'                 => $activityId,
        'subject'            => $type ? $type['label'] : 'Rendez-vous',
        'activity_date_time' => $params['start_datetime'],
        'duration'           => $type['duration_minutes'] ?? 60,
        'details'            => sprintf(
          'Rendez-vous #%d — %s',
          $id,
          \CRM\Booking\Utils::formatDatetime($params['start_datetime'])
        ),
      ];

      $assignee = self::getTherapistContactId((int) $params['therapist_id']);
      if ($assignee) {
        $apiParams['assignee_id'] = $assignee;
      }

      \civicrm_api3('Activity', 'create', $apiParams);
    }
    catch (\Throwable $e) {
      \CRM\Booking\Utils::logError('Échec mise à jour Activity', [
        'appointment_id' => $id,
        'error'          => $e->getMessage(),
      ]);
    }
  }

  /**
   * Retirer le rendez-vous des agendas de l'intervenant·e et du local.
   * À appeler AVANT de modifier le rendez-vous : les événements se retrouvent
   * par l'ancien·ne intervenant·e et l'ancien local.
   */
  public static function detachFromCalendars(int $id): void {
    $appt = self::getById($id);
    if ($appt) {
      self::removeFromCalendars($id, $appt);
    }
  }

  private static function createActivity(int $appointmentId, array $params): ?int {
    // Les activités sont visibles dans les calendriers et les recherches
    // CiviCRM. Dans un cadre où la seule existence d'un rendez-vous est
    // une information sensible, cette création peut être désactivée.
    if ((string) \CRM\Booking\Utils::getSetting('create_activities', '1') !== '1') {
      return NULL;
    }

    try {
      $type = AppointmentType::getById((int) $params['appointment_type_id']);
      $typeName = $type ? $type['label'] : 'Rendez-vous';

      // Le sujet est repris tel quel dans les listes d'activités et les
      // calendriers, visibles de toute personne ayant accès aux activités.
      // Il ne doit donc porter que le type de rendez-vous : l'identité du
      // patient figure dans le champ « cible », soumis aux permissions de
      // visibilité des contacts.
      $result = \civicrm_api3('Activity', 'create', [
        'activity_type_id'  => self::getActivityTypeId(),
        'subject'           => $typeName,
        'activity_date_time'=> $params['start_datetime'],
        'duration'          => $type['duration_minutes'] ?? 60,
        'status_id'         => 'Scheduled',
        'source_contact_id' => \CRM_Core_Session::getLoggedInContactID() ?: $params['contact_id'],
        'target_id'         => $params['contact_id'],
        'assignee_id'       => self::getTherapistContactId((int) $params['therapist_id']),
        'details'           => sprintf(
          'Rendez-vous #%d — %s',
          $appointmentId,
          \CRM\Booking\Utils::formatDatetime($params['start_datetime'])
        ),
      ]);
      return (int) ($result['id'] ?? 0) ?: NULL;
    }
    catch (\Throwable $e) {
      \CRM\Booking\Utils::logError('Échec création Activity', ['error' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Annuler un rendez-vous.
   */
  public static function cancel(int $id, string $reason = ''): void {
    // Charger le RDV AVANT de l'annuler (pour récupérer caldav_event_id)
    $appt = self::getById($id);

    \CRM_Core_DAO::executeQuery(
      'UPDATE civicrm_booking_appointment
       SET status = "cancelled", notes = CONCAT(IFNULL(notes,""), %1)
       WHERE id = %2',
      [
        1 => [$reason ? "\nAnnulation : {$reason}" : '', 'String'],
        2 => [$id, 'Integer'],
      ]
    );

    if (!$appt) return;

    // Mettre à jour l'Activity CiviCRM
    if (!empty($appt['civicrm_activity_id'])) {
      try {
        \civicrm_api3('Activity', 'create', [
          'id'        => $appt['civicrm_activity_id'],
          'status_id' => 'Cancelled',
        ]);
      }
      catch (\Throwable $e) {}
    }

    // Prévenir le patient et le intervenant·e — non bloquant
    try {
      $notifier = new \CRM\Booking\Service\NotificationService();
      $notifier->sendCancellation($appt, $reason);
    }
    catch (\Throwable $e) {
      \CRM\Booking\Utils::logError('Email d\'annulation échoué', [
        'appointment_id' => $id,
        'error'          => $e->getMessage(),
      ]);
    }

    // Retirer l'événement des agendas — non bloquant
    self::removeFromCalendars($id, $appt);
  }

  /**
   * Retirer un rendez-vous de l'agenda de l'intervenant et de celui du local.
   */
  private static function removeFromCalendars(int $id, array $appt): void {
    $hasPersonal = !empty($appt['caldav_event_id']);
    $hasLocation = !empty($appt['location_event_id']) && !empty($appt['location_id']);

    if (!$hasPersonal && !$hasLocation) {
      return;
    }

    try {
      $provider = new \CRM\Booking\Service\CalendarProvider\InformaniakCalDavProvider();
      if (!$provider->isAvailable()) return;

      if ($hasPersonal) {
        $provider->deleteEvent((int) $appt['therapist_id'], $appt['caldav_event_id']);
        \CRM_Core_DAO::executeQuery(
          'UPDATE civicrm_booking_appointment SET caldav_event_id = NULL WHERE id = %1',
          [1 => [$id, 'Integer']]
        );
      }

      if ($hasLocation) {
        $url = \CRM\Booking\BAO\Location::getCalendarUrl((int) $appt['location_id']);
        if ($url) {
          $provider->deleteEventInCalendar($url, $appt['location_event_id']);
          \CRM_Core_DAO::executeQuery(
            'UPDATE civicrm_booking_appointment SET location_event_id = NULL WHERE id = %1',
            [1 => [$id, 'Integer']]
          );
        }
      }
    }
    catch (\Throwable $e) {
      \CRM\Booking\Utils::logError('Retrait des agendas impossible', [
        'appointment_id' => $id,
        'error'          => $e->getMessage(),
      ]);
    }
  }

  /**
   * Marquer comme terminé.
   */
  public static function complete(int $id): void {
    \CRM_Core_DAO::executeQuery(
      'UPDATE civicrm_booking_appointment SET status = "completed" WHERE id = %1',
      [1 => [$id, 'Integer']]
    );
    $appt = self::getById($id);
    if ($appt && $appt['civicrm_activity_id']) {
      try {
        \civicrm_api3('Activity', 'create', [
          'id'        => $appt['civicrm_activity_id'],
          'status_id' => 'Completed',
        ]);
      }
      catch (\Throwable $e) {}
    }
  }

  /**
   * Obtenir ou créer le type d'Activity "Rendez-vous" dans CiviCRM.
   */
  public static function getActivityTypeId(): int {
    static $typeId = NULL;
    if ($typeId) return $typeId;

    try {
      $result = \civicrm_api3('OptionValue', 'get', [
        'option_group_id' => 'activity_type',
        'name'            => 'booking_appointment',
      ]);
      if ($result['count'] > 0) {
        $typeId = (int) reset($result['values'])['value'];
        return $typeId;
      }
      // Créer le type s'il n'existe pas
      $created = \civicrm_api3('OptionValue', 'create', [
        'option_group_id' => 'activity_type',
        'name'            => 'booking_appointment',
        'label'           => 'Rendez-vous',
        'is_active'       => 1,
      ]);
      $typeId = (int) $created['values'][$created['id']]['value'];
      return $typeId;
    }
    catch (\Throwable $e) {
      return 1; // Fallback: Meeting
    }
  }

  /**
   * Récupérer le contact_id CiviCRM d'un intervenant·e.
   */
  private static function getTherapistContactId(int $therapistId): ?int {
    $contactId = \CRM_Core_DAO::singleValueQuery(
      'SELECT contact_id FROM civicrm_booking_therapist WHERE id = %1',
      [1 => [$therapistId, 'Integer']]
    );
    return $contactId ? (int) $contactId : NULL;
  }
}
