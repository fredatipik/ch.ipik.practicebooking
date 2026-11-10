<?php
namespace CRM\Booking\WordPress;

use CRM\Booking\BAO\AppointmentType;
use CRM\Booking\Service\SlotService;
use CRM\Booking\Service\BookingService;
use CRM\Booking\Service\MessageService;
use CRM\Booking\Utils;

/**
 * BookingShortcode — [ipik_booking] et endpoints AJAX WordPress.
 * Usage : [ipik_booking] ou [ipik_booking type="3"]
 */
class BookingShortcode {

  public static function register(): void {
    add_shortcode('ipik_booking', [self::class, 'render']);
    $actions = ['get_slots', 'submit_booking'];
    foreach ($actions as $action) {
      add_action("wp_ajax_ipik_booking_{$action}",        [self::class, "ajax_{$action}"]);
      add_action("wp_ajax_nopriv_ipik_booking_{$action}", [self::class, "ajax_{$action}"]);
    }
  }

  // -------------------------------------------------------------------------
  // Shortcode HTML
  // -------------------------------------------------------------------------

  public static function render(array $atts): string {
    $atts = shortcode_atts(['type' => ''], $atts);

    $nonce = wp_create_nonce('ipik_booking_nonce');

    // Le numéro de version des ressources suit la date de modification des
    // fichiers : remplacer un fichier par FTP force le navigateur à le
    // recharger, sans qu'il soit nécessaire de toucher à un numéro.
    $root   = dirname(__DIR__);
    $cssVer = (string) (@filemtime($root . '/css/booking.css') ?: '0.5.0');
    $jsVer  = (string) (@filemtime($root . '/js/booking-form.js') ?: '0.5.0');

    wp_enqueue_style('ipik-booking', Utils::resourceUrl() . '/css/booking.css', [], $cssVer);
    wp_enqueue_script('ipik-booking', Utils::resourceUrl() . '/js/booking-form.js', [], $jsVer, TRUE);

    // Types proposés : tous, ou ceux listés dans l'attribut « type ».
    // Un seul type retenu saute l'étape de choix.
    $types   = self::selectTypes((string) $atts['type']);
    $preType = (trim((string) $atts['type']) !== '' && count($types) === 1)
      ? (int) $types[0]['id']
      : 0;

    // Données utilisateur connecté pour pré-remplissage
    $currentUser = NULL;
    if (is_user_logged_in()) {
      $civiContact = MemberGate::getLoggedInCiviContact();
      if ($civiContact) {
        $currentUser = [
          'first_name' => $civiContact['first_name'] ?? '',
          'last_name'  => $civiContact['last_name']  ?? '',
          'email'      => $civiContact['email']       ?? '',
          'contact_id' => $civiContact['id']          ?? 0,
        ];
      }
    }

    // Où ramener la personne après connexion : la page du formulaire
    $loginUrl = wp_login_url(get_permalink() ?: home_url('/'));

    wp_localize_script('ipik-booking', 'ipikBooking', [
      'ajaxUrl'     => admin_url('admin-ajax.php'),
      'nonce'       => $nonce,
      'preType'     => $preType,
      'currentUser' => $currentUser,
      'isLoggedIn'  => MemberGate::isLoggedIn(),
      'loginUrl'    => $loginUrl,
      'l10n'        => [
        'selectType'      => __('Choisissez un type de rendez-vous', 'ipik-booking'),
        'selectDate'      => __('Sélectionnez une date pour voir les créneaux disponibles.', 'ipik-booking'),
        'selectSlot'      => __('Choisissez un créneau', 'ipik-booking'),
        'noSlots'         => MessageService::get(MessageService::NO_SLOTS),
        'loading'         => __('Chargement…', 'ipik-booking'),
        'errorGeneric'    => __('Une erreur est survenue. Veuillez réessayer.', 'ipik-booking'),
        'confirmSuccess'  => __('Votre rendez-vous est confirmé ! Un email de confirmation vous a été envoyé.', 'ipik-booking'),
        'loginRequired'   => MessageService::get(MessageService::EMAIL_GATE),
        'contactNotFound' => MessageService::get(MessageService::CONTACT_NOT_FOUND),
      ],
    ]);

    $messages = MessageService::all();

    ob_start();
    // Injecter le nonce dans le formulaire HTML via un champ hidden
    echo '<input type="hidden" id="ipik-nonce-value" value="' . esc_attr($nonce) . '" />';
    include dirname(__DIR__) . '/templates/wordpress/booking-form.php';
    return ob_get_clean();
  }

  /**
   * Types de rendez-vous à proposer.
   *
   * Sans attribut, tous les types actifs. Avec « 2,3 », seulement ceux-là,
   * dans l'ordre indiqué. Les identifiants inconnus ou inactifs sont
   * ignorés ; si plus rien ne reste, la liste est vide — mieux vaut un
   * formulaire sans choix que l'exposition de types que l'on voulait masquer.
   */
  private static function selectTypes(string $raw): array {
    $all = AppointmentType::getAll();

    // Aucun attribut : tous les types
    if (trim($raw) === '') {
      return $all;
    }

    // Attribut renseigné mais illisible (« abc ») : aucun type, plutôt que
    // de retomber sur la liste complète
    $wanted = self::parseTypeIds($raw);
    if (!$wanted) {
      return [];
    }

    $byId = [];
    foreach ($all as $type) {
      $byId[(int) $type['id']] = $type;
    }

    $selected = [];
    foreach ($wanted as $id) {
      if (isset($byId[$id])) {
        $selected[] = $byId[$id];
      }
    }
    return $selected;
  }

  /**
   * « 2, 3 » ou « 2;3 » → [2, 3]. Les éléments non numériques sont écartés,
   * les doublons aussi.
   *
   * @return int[]
   */
  private static function parseTypeIds(string $raw): array {
    $ids = [];
    foreach (preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) as $part) {
      if (!ctype_digit($part)) continue;
      $id = (int) $part;
      if ($id > 0 && !in_array($id, $ids, TRUE)) {
        $ids[] = $id;
      }
    }
    return $ids;
  }

  /**
   * Message adressé au visiteur lorsque l'accès à un type est refusé.
   */
  private static function gateMessage(?string $reason): string {
    return match ($reason) {
      'login_required'    => MessageService::get(MessageService::EMAIL_GATE),
      'contact_not_found' => MessageService::get(MessageService::CONTACT_NOT_FOUND),
      default             => 'Ce type de rendez-vous n\'est pas disponible.',
    };
  }

  // -------------------------------------------------------------------------
  // AJAX
  // -------------------------------------------------------------------------

  public static function ajax_get_slots(): void {
    ob_start();
    try {
      self::doGetSlots();
    }
    catch (\Throwable $e) {
      Utils::logError('Erreur fatale dans get_slots', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile() . ':' . $e->getLine(),
      ]);
      self::sendJson(FALSE, ['message' => 'Impossible de charger les créneaux.']);
    }
  }

  private static function doGetSlots(): void {
    self::verifyNonce();
    $typeId   = intval($_POST['type_id'] ?? 0);
    $dateFrom = sanitize_text_field($_POST['date_from'] ?? date('Y-m-d'));
    $dateTo   = sanitize_text_field($_POST['date_to']   ?? date('Y-m-d', strtotime('+30 days')));
    if (!$typeId) self::sendJson(FALSE, ['message' => 'Type invalide.']);

    // Un type réservé n'expose pas ses disponibilités aux visiteurs non connectés
    $gate = MemberGate::check($typeId);
    if (!$gate['allowed']) {
      self::sendJson(FALSE, ['message' => self::gateMessage($gate['reason'])]);
    }

    $slotService = new SlotService();
    $slots = $slotService->getAvailableSlots($typeId, $dateFrom, $dateTo);

    $grouped = [];
    foreach ($slots as $slot) {
      $date = substr($slot, 0, 10);
      $time = substr($slot, 11, 5);
      $grouped[$date][] = ['datetime' => $slot, 'time' => $time];
    }
    self::sendJson(TRUE, ['slots' => $grouped]);
  }

  public static function ajax_submit_booking(): void {
    ob_start();
    try {
      self::doSubmitBooking();
    }
    catch (\Throwable $e) {
      Utils::logError('Erreur fatale dans submit_booking', [
        'error' => $e->getMessage(),
        'file'  => $e->getFile() . ':' . $e->getLine(),
      ]);
      self::sendJson(FALSE, ['message' => 'Une erreur technique est survenue. Veuillez réessayer.']);
    }
  }

  private static function doSubmitBooking(): void {
    self::verifyNonce();
    $typeId        = intval($_POST['appointment_type_id'] ?? 0);
    $startDatetime = sanitize_text_field($_POST['start_datetime'] ?? '');
    $firstName     = sanitize_text_field($_POST['first_name']     ?? '');
    $lastName      = sanitize_text_field($_POST['last_name']      ?? '');
    $email         = sanitize_email($_POST['email']               ?? '');
    $phone         = sanitize_text_field($_POST['phone']          ?? '');
    $notes         = sanitize_textarea_field($_POST['notes']      ?? '');

    if (!$typeId || !$startDatetime || !$email) {
      self::sendJson(FALSE, ['message' => 'Données manquantes.']);
    }

    $wpUserId = is_user_logged_in() ? get_current_user_id() : NULL;

    // Type réservé aux patients déjà suivis : la personne doit être connectée,
    // et c'est SA fiche qui est utilisée — jamais l'adresse saisie dans le
    // formulaire, qui permettrait de réserver au nom de quelqu'un d'autre.
    $gate = MemberGate::check($typeId);
    if (!$gate['allowed']) {
      self::sendJson(FALSE, ['message' => self::gateMessage($gate['reason'])]);
    }

    $forcedContactId = 0;
    $type = AppointmentType::getById($typeId);
    if (!empty($type['requires_existing_contact'])) {
      $member          = MemberGate::getLoggedInCiviContact();
      $forcedContactId = (int) ($member['contact_id'] ?? $member['id'] ?? 0);
      if (!empty($member['email'])) {
        $email = $member['email'];
      }
    }

    $bookingService = new BookingService();
    $result = $bookingService->book([
      'appointment_type_id' => $typeId,
      'start_datetime'      => $startDatetime,
      'contact'             => [
        'first_name'        => $firstName,
        'last_name'         => $lastName,
        'email'             => $email,
        'phone'             => $phone,
        'forced_contact_id' => $forcedContactId,
      ],
      'wp_user_id' => $wpUserId,
      'notes'      => $notes,
    ]);

    if ($result['success']) {
      self::sendJson(TRUE, ['appointment_id' => $result['appointment_id']]);
    } else {
      self::sendJson(FALSE, ['message' => $result['error']]);
    }
  }

  // -------------------------------------------------------------------------

  /**
   * Envoyer une réponse JSON propre en vidant tout output parasite.
   * Indispensable : CiviCRM peut émettre des warnings PHP qui cassent le JSON.
   */
  private static function sendJson(bool $success, array $data, int $status = 200): void {
    // Jeter uniquement le buffer ouvert par le handler AJAX (warnings, notices).
    // Ne pas toucher aux buffers de WordPress lui-même.
    if (ob_get_level() > 0) {
      $noise = ob_get_clean();
      if ($noise !== '' && $noise !== FALSE) {
        Utils::logError('Sortie parasite avant réponse AJAX', [
          'output' => substr($noise, 0, 300),
        ]);
      }
    }
    if ($status !== 200) {
      status_header($status);
    }
    // wp_send_json gère les en-têtes, l'encodage et la terminaison
    $success ? wp_send_json_success($data) : wp_send_json_error($data);
  }

  private static function verifyNonce(): void {
    $nonce = $_POST['nonce'] ?? $_REQUEST['nonce'] ?? '';
    if (!wp_verify_nonce($nonce, 'ipik_booking_nonce')) {
      self::sendJson(FALSE, ['message' => 'Sécurité : token invalide.'], 403);
    }
  }
}
