<?php
/**
 * ch.ipik.practicebooking v0.6.0 — Extension CiviCRM de prise de rendez-vous
 * Fichier principal — hooks CiviCRM
 */

function booking_civicrm_config(&$config): void {
  static $configured = FALSE;
  if ($configured) return;
  $configured = TRUE;

  $d = __DIR__;
  set_include_path($d . PATH_SEPARATOR . get_include_path());

  $tpl = CRM_Core_Smarty::singleton();
  $tpl->addTemplateDir($d . '/templates');

  _booking_load_classes();

  if (function_exists('add_action')) {
    // Selon la version de CiviCRM, son démarrage peut survenir avant, pendant
    // ou après l'action « init » de WordPress. Si « init » a déjà commencé,
    // y accrocher un rappel ne sert plus à rien : le shortcode et les actions
    // AJAX ne seraient alors jamais enregistrés, et la page afficherait le texte
    // brut « [ipik_booking] ». On enregistre donc tout de suite dans ce cas.
    // L'enregistrement est idempotent : le refaire plus tard est sans effet.
    if (function_exists('did_action') && did_action('init')) {
      \CRM\Booking\WordPress\BookingShortcode::register();
    }
    else {
      add_action('init', ['CRM\Booking\WordPress\BookingShortcode', 'register']);
    }
  }
}

function _booking_load_classes(): void {
  static $loaded = FALSE;
  if ($loaded) return;
  $loaded = TRUE;

  $d = __DIR__;

  if (!class_exists('CRM\Booking\Utils', FALSE))                                         require_once $d . '/CRM/Booking/Utils.php';
  if (!class_exists('CRM\Booking\BAO\Therapist', FALSE))                                 require_once $d . '/CRM/Booking/BAO/Therapist.php';
  if (!class_exists('CRM\Booking\BAO\AppointmentType', FALSE))                           require_once $d . '/CRM/Booking/BAO/AppointmentType.php';
  if (!class_exists('CRM\Booking\BAO\Availability', FALSE))                              require_once $d . '/CRM/Booking/BAO/Availability.php';
  if (!class_exists('CRM\Booking\BAO\Appointment', FALSE))                               require_once $d . '/CRM/Booking/BAO/Appointment.php';
  if (!class_exists('CRM\Booking\BAO\Location', FALSE))                              require_once $d . '/CRM/Booking/BAO/Location.php';
  if (!class_exists('CRM\Booking\BAO\Workday', FALSE))                               require_once $d . '/CRM/Booking/BAO/Workday.php';
  if (!interface_exists('CRM\Booking\Service\CalendarProvider\CalendarProviderInterface', FALSE)) require_once $d . '/CRM/Booking/Service/CalendarProvider/CalendarProviderInterface.php';
  if (!class_exists('CRM\Booking\Service\CalendarProvider\NullCalendarProvider', FALSE)) require_once $d . '/CRM/Booking/Service/CalendarProvider/NullCalendarProvider.php';
  if (!class_exists('CRM\Booking\Service\CalendarProvider\InformaniakCalDavProvider', FALSE))  require_once $d . '/CRM/Booking/Service/CalendarProvider/InformaniakCalDavProvider.php';
  if (!interface_exists('CRM\Booking\Service\TherapistSelector\TherapistSelectorInterface', FALSE)) require_once $d . '/CRM/Booking/Service/TherapistSelector/TherapistSelectorInterface.php';
  if (!class_exists('CRM\Booking\Service\TherapistSelector\TherapistSelectorFactory', FALSE)) require_once $d . '/CRM/Booking/Service/TherapistSelector/TherapistSelectorFactory.php';
  if (!class_exists('CRM\Booking\Service\SlotService', FALSE))                           require_once $d . '/CRM/Booking/Service/SlotService.php';
  if (!class_exists('CRM\Booking\Service\TemplateService', FALSE))                       require_once $d . '/CRM/Booking/Service/TemplateService.php';
  if (!class_exists('CRM\Booking\Service\MessageService', FALSE))                    require_once $d . '/CRM/Booking/Service/MessageService.php';
  if (!class_exists('CRM\Booking\Service\NotificationService', FALSE))                   require_once $d . '/CRM/Booking/Service/NotificationService.php';
  if (!class_exists('CRM\Booking\Service\InvoiceService', FALSE))                        require_once $d . '/CRM/Booking/Service/InvoiceService.php';
  if (!class_exists('CRM\Booking\Service\BookingService', FALSE))                        require_once $d . '/CRM/Booking/Service/BookingService.php';
  if (!class_exists('CRM\Booking\Page\AppointmentList', FALSE))                          require_once $d . '/CRM/Booking/Page/AppointmentList.php';
  if (!class_exists('CRM\Booking\Page\AppointmentTypeList', FALSE))                      require_once $d . '/CRM/Booking/Page/AppointmentTypeList.php';
  if (!class_exists('CRM\Booking\Page\TherapistList', FALSE))                            require_once $d . '/CRM/Booking/Page/TherapistList.php';
  if (!class_exists('CRM\Booking\Page\LocationList', FALSE))                        require_once $d . '/CRM/Booking/Page/LocationList.php';
  if (!class_exists('CRM\Booking\Page\Workdays', FALSE))                            require_once $d . '/CRM/Booking/Page/Workdays.php';
  if (!class_exists('CRM\Booking\Page\WorkdaySave', FALSE))                         require_once $d . '/CRM/Booking/Page/WorkdaySave.php';
  if (!class_exists('CRM\Booking\Page\TherapistAgenda', FALSE))                          require_once $d . '/CRM/Booking/Page/TherapistAgenda.php';
  if (!class_exists('CRM\Booking\Page\ContactAppointments', FALSE))                   require_once $d . '/CRM/Booking/Page/ContactAppointments.php';
  if (!class_exists('CRM\Booking\Page\Dashlet', FALSE))                             require_once $d . '/CRM/Booking/Page/Dashlet.php';
  if (!class_exists('CRM\Booking\Page\AppointmentSlots', FALSE))                     require_once $d . '/CRM/Booking/Page/AppointmentSlots.php';
  if (!class_exists('CRM\Booking\Page\AppointmentAction', FALSE))                        require_once $d . '/CRM/Booking/Page/AppointmentAction.php';
  if (!class_exists('CRM\Booking\Page\InvoiceAction', FALSE))                            require_once $d . '/CRM/Booking/Page/InvoiceAction.php';
  if (!class_exists('CRM\Booking\Form\Appointment', FALSE))                         require_once $d . '/CRM/Booking/Form/Appointment.php';
  if (!class_exists('CRM\Booking\Form\AppointmentType', FALSE))                          require_once $d . '/CRM/Booking/Form/AppointmentType.php';
  if (!class_exists('CRM\Booking\Form\Therapist', FALSE))                                require_once $d . '/CRM/Booking/Form/Therapist.php';
  if (!class_exists('CRM\Booking\Form\Availability', FALSE))                             require_once $d . '/CRM/Booking/Form/Availability.php';
  if (!class_exists('CRM\Booking\Form\ExceptionForm', FALSE))                            require_once $d . '/CRM/Booking/Form/ExceptionForm.php';
  if (!class_exists('CRM\Booking\Form\Settings', FALSE))                                 require_once $d . '/CRM/Booking/Form/Settings.php';
  if (!class_exists('CRM\Booking\Form\LocationForm', FALSE))                        require_once $d . '/CRM/Booking/Form/LocationForm.php';
  if (!class_exists('CRM\Booking\Cron\ReminderJob', FALSE))                              require_once $d . '/CRM/Booking/Cron/ReminderJob.php';

  // Classes WordPress : chargées inconditionnellement. Elles vérifient
  // elles-mêmes la présence des fonctions WordPress avant de s'en servir,
  // ce qui les rend inoffensives en ligne de commande.
  if (!class_exists('CRM\Booking\WordPress\RoleManager', FALSE))      require_once $d . '/wordpress/RoleManager.php';
  if (!class_exists('CRM\Booking\WordPress\MemberGate', FALSE))       require_once $d . '/wordpress/MemberGate.php';
  if (!class_exists('CRM\Booking\WordPress\BookingShortcode', FALSE)) require_once $d . '/wordpress/BookingShortcode.php';

  // Aliases underscore pour callbacks de routes CiviCRM
  foreach ([
    'CRM_Booking_Page_AppointmentList'     => 'CRM\Booking\Page\AppointmentList',
    'CRM_Booking_Page_AppointmentAction'   => 'CRM\Booking\Page\AppointmentAction',
    'CRM_Booking_Page_AppointmentTypeList' => 'CRM\Booking\Page\AppointmentTypeList',
    'CRM_Booking_Page_TherapistAgenda'     => 'CRM\Booking\Page\TherapistAgenda',
    'CRM_Booking_Page_ContactAppointments' => 'CRM\Booking\Page\ContactAppointments',
    'CRM_Booking_Page_Dashlet'             => 'CRM\Booking\Page\Dashlet',
    'CRM_Booking_Page_AppointmentSlots'    => 'CRM\Booking\Page\AppointmentSlots',
    'CRM_Booking_Form_Appointment'         => 'CRM\Booking\Form\Appointment',
    'CRM_Booking_Page_TherapistList'       => 'CRM\Booking\Page\TherapistList',
    'CRM_Booking_Page_InvoiceAction'       => 'CRM\Booking\Page\InvoiceAction',
    'CRM_Booking_Form_AppointmentType'     => 'CRM\Booking\Form\AppointmentType',
    'CRM_Booking_Form_Therapist'           => 'CRM\Booking\Form\Therapist',
    'CRM_Booking_Form_Availability'        => 'CRM\Booking\Form\Availability',
    'CRM_Booking_Form_ExceptionForm'       => 'CRM\Booking\Form\ExceptionForm',
    'CRM_Booking_Form_Settings'            => 'CRM\Booking\Form\Settings',
    'CRM_Booking_Form_LocationForm'        => 'CRM\Booking\Form\LocationForm',
    'CRM_Booking_Page_LocationList'        => 'CRM\Booking\Page\LocationList',
    'CRM_Booking_Page_Workdays'            => 'CRM\Booking\Page\Workdays',
    'CRM_Booking_Page_WorkdaySave'         => 'CRM\Booking\Page\WorkdaySave',
  ] as $alias => $original) {
    if (!class_exists($alias, FALSE) && class_exists($original, FALSE)) {
      class_alias($original, $alias);
    }
  }
}

function booking_civicrm_install(): void {
  _booking_run_migrations();
  _booking_load_classes();

  // Modèles d'e-mail, modifiables ensuite dans l'administration CiviCRM
  if (class_exists('CRM\Booking\Service\TemplateService', FALSE)) {
    try {
      \CRM\Booking\Service\TemplateService::installTemplates();
    }
    catch (Throwable $e) {
    }
  }

  // Rôle WordPress — RoleManager ne fait rien hors contexte WordPress
  try {
    \CRM\Booking\WordPress\RoleManager::ensureTherapistRole();
  }
  catch (Throwable $e) {
  }
}

function booking_civicrm_enable(): void {
  // Relancer les migrations si elles n'ont pas tourné à l'installation
  _booking_run_migrations();
  _booking_load_classes();

  if (class_exists('CRM\Booking\Service\TemplateService', FALSE)) {
    try {
      \CRM\Booking\Service\TemplateService::installTemplates();
    }
    catch (Throwable $e) {
    }
  }
  // shell_exec désactivé sur hébergement mutualisé Infomaniak
  // Pour Phase 2 (Google Calendar) : lancer manuellement "composer install --no-dev"
}

function booking_civicrm_disable(): void {}

function booking_civicrm_uninstall(): void {
  _booking_load_classes();

  // Modèles d'e-mail
  if (class_exists('CRM\Booking\Service\TemplateService', FALSE)) {
    try {
      \CRM\Booking\Service\TemplateService::removeTemplates();
    }
    catch (Throwable $e) {
      // La désinstallation ne doit pas échouer pour cela
    }
  }

  // Mettre à la corbeille les activités dont le rendez-vous disparaît.
  // Les activités ne sont pas détruites : l'historique des consultations
  // garde sa valeur même sans l'extension.
  _booking_trash_orphan_activities();

  _booking_run_sql_file(__DIR__ . '/sql/uninstall.sql');

  // Rôle WordPress — RoleManager ne fait rien hors contexte WordPress
  try {
    \CRM\Booking\WordPress\RoleManager::removeTherapistRole();
  }
  catch (Throwable $e) {
  }
}

function booking_civicrm_navigationMenu(&$menu): void {
  _booking_insert_nav($menu, NULL, [
    'label'      => ts('Booking'),
    'name'       => 'booking_root',
    'url'        => 'civicrm/booking/appointments',
    'permission' => 'access booking',
    'operator'   => 'OR',
    'separator'  => 0,
    'is_active'  => 1,
    'icon'       => 'crm-i fa-calendar-check-o',
    'weight'     => 37,
  ]);
  foreach ([
    ['label' => ts('Tous les rendez-vous'), 'name' => 'booking_appointments', 'url' => 'civicrm/booking/appointments',      'permission' => 'access booking'],
    ['label' => ts('Types de rendez-vous'), 'name' => 'booking_types',        'url' => 'civicrm/booking/appointment-types', 'permission' => 'administer booking'],
    ['label' => ts('Intervenant·es'),       'name' => 'booking_therapists',   'url' => 'civicrm/booking/therapists',        'permission' => 'administer booking'],
    ['label' => ts('Locaux'),               'name' => 'booking_locations',    'url' => 'civicrm/booking/locations',         'permission' => 'administer booking'],
    ['label' => ts('Paramètres'),           'name' => 'booking_settings',     'url' => 'civicrm/booking/settings',          'permission' => 'administer booking'],
  ] as $item) {
    _booking_insert_nav($menu, 'booking_root', $item + ['is_active' => 1]);
  }
}

function booking_civicrm_permission(&$permissions): void {
  $permissions['access booking']     = ['label' => ts('Booking : accéder aux rendez-vous')];
  $permissions['administer booking'] = ['label' => ts('Booking : administrer')];
}

function booking_civicrm_tabset($tabsetName, &$tabs, $context): void {
  if ($tabsetName !== 'civicrm/contact/view') return;

  $contactId = (int) ($context['contact_id'] ?? 0);
  if (!$contactId) return;

  _booking_load_classes();

  // Thérapeute : agenda complet (disponibilités, congés, rendez-vous)
  if (\CRM\Booking\BAO\Therapist::isTherapist($contactId)) {
    $tabs['booking_agenda'] = [
      'title'  => ts('Agenda'),
      'link'   => \CRM\Booking\Utils::agendaTabUrl($contactId),
      'valid'  => TRUE, 'active' => TRUE, 'current' => FALSE, 'weight' => 100,
    ];
    return;
  }

  // Patient : onglet Rendez-vous, affiché seulement s'il en a
  $count = (int) CRM_Core_DAO::singleValueQuery(
    "SELECT COUNT(*) FROM civicrm_booking_appointment
     WHERE contact_id = %1 AND status != 'cancelled'",
    [1 => [$contactId, 'Integer']]
  );
  if (!$count) return;

  $tabs['booking_appointments'] = [
    'title'  => ts('Rendez-vous'),
    'count'  => $count,
    'link'   => \CRM_Utils_System::url('civicrm/booking/contact-appointments', ['cid' => $contactId]),
    'valid'  => TRUE, 'active' => TRUE, 'current' => FALSE, 'weight' => 100,
  ];
}

function booking_civicrm_alterMenu(&$items): void {
  _booking_load_classes();
  foreach (_booking_get_routes() as $path => $route) {
    $items[$path] = [
      'title'            => $route['title'],
      'page_callback'    => $route['page_callback'],
      'access_arguments' => [[$route['permission']]],
    ];
  }
}

function booking_civicrm_managed(array &$entities): void {
  $files = glob(__DIR__ . '/managed/*.mgd.php') ?: [];
  sort($files);

  foreach ($files as $file) {
    $declared = require $file;
    if (!is_array($declared)) {
      continue;
    }
    foreach ($declared as $item) {
      if (!is_array($item) || empty($item['entity'])) {
        continue;
      }
      // Le champ "module" est obligatoire pour CRM_Core_ManagedEntities
      $item['module'] = 'ch.ipik.practicebooking';
      $entities[] = $item;
    }
  }
}

function booking_civicrm_alterAPIPermissions($entity, $action, &$params, &$permissions): void {
  if (strtolower($entity) === 'bookingeminder' && $action === 'run') {
    $permissions['booking_reminder']['run'] = ['administer CiviCRM'];
  }
}

/**
 * hook_civicrm_pageRun — CSS inline sur toutes les pages CiviCRM.
 */
function booking_civicrm_pageRun(&$page): void {
  _booking_check_schema();
  _booking_add_menu_css();
}

/**
 * Appliquer les migrations en attente si la version du code a changé.
 *
 * Les hooks install/enable ne se rejouent pas lorsqu'on remplace simplement
 * les fichiers de l'extension. Cette vérification, très légère, garantit que
 * le schéma suit toujours le code.
 */
function _booking_check_schema(): void {
  static $checked = FALSE;
  if ($checked) return;
  $checked = TRUE;

  try {
    $codeVersion = _booking_code_version();
    if ($codeVersion === '') return;

    $dbVersion = CRM_Core_DAO::singleValueQuery(
      "SELECT `value` FROM `civicrm_booking_settings` WHERE `key` = 'schema_version'"
    );

    if ($dbVersion === $codeVersion) return;

    _booking_run_migrations();
    _booking_load_classes();

    if (class_exists('CRM\Booking\Service\TemplateService', FALSE)) {
      \CRM\Booking\Service\TemplateService::installTemplates();
    }

    CRM_Core_DAO::executeQuery(
      "INSERT INTO `civicrm_booking_settings` (`key`, `value`) VALUES ('schema_version', %1)
       ON DUPLICATE KEY UPDATE `value` = %1",
      [1 => [$codeVersion, 'String']]
    );
  }
  catch (\Throwable $e) {
    // Ne jamais bloquer l'affichage d'une page pour cela
  }
}

/**
 * Version déclarée dans info.xml.
 */
function _booking_code_version(): string {
  static $version = NULL;
  if ($version !== NULL) return $version;

  $version = '';
  $file = __DIR__ . '/info.xml';
  if (file_exists($file)) {
    $xml = @simplexml_load_file($file);
    if ($xml !== FALSE && isset($xml->version)) {
      $version = (string) $xml->version;
    }
  }
  return $version;
}

/**
 * hook_civicrm_buildForm — même injection sur les formulaires.
 */
function booking_civicrm_buildForm($formName, &$form): void {
  _booking_add_menu_css();
}

/**
 * Masquer les chevrons des entrées de menu Booking sans sous-niveau.
 * Injecté via CRM_Core_Resources, qui écrit dans le <head> rendu par CiviCRM.
 */
function _booking_add_menu_css(): void {
  static $added = FALSE;
  if ($added) return;
  $added = TRUE;

  $css = '
    #civicrm-menu li[data-name="booking_appointments"] > a > span.sub-arrow,
    #civicrm-menu li[data-name="booking_types"] > a > span.sub-arrow,
    #civicrm-menu li[data-name="booking_therapists"] > a > span.sub-arrow,
    #civicrm-menu li[data-name="booking_settings"] > a > span.sub-arrow,
    li[data-name="booking_appointments"] span.sub-arrow,
    li[data-name="booking_types"] span.sub-arrow,
    li[data-name="booking_therapists"] span.sub-arrow,
    li[data-name="booking_locations"] span.sub-arrow,
    li[data-name="booking_settings"] span.sub-arrow { display: none !important; }
  ';

  try {
    \CRM_Core_Resources::singleton()->addStyle($css, 100, 'html-header');
  }
  catch (\Throwable $e) {
    // Injection best-effort : une erreur ici ne doit rien casser
  }
}

// =============================================================================
// Fonctions internes
// =============================================================================


function _booking_get_routes(): array {
  return [
    'civicrm/booking/appointments'          => ['page_callback' => 'CRM_Booking_Page_AppointmentList',    'title' => 'Rendez-vous',           'permission' => 'access booking'],
    'civicrm/booking/therapist-agenda'      => ['page_callback' => 'CRM_Booking_Page_TherapistAgenda',    'title' => 'Agenda',                'permission' => 'access booking'],
    'civicrm/booking/contact-appointments'  => ['page_callback' => 'CRM_Booking_Page_ContactAppointments','title' => 'Rendez-vous',           'permission' => 'access booking'],
    'civicrm/booking/dashlet'               => ['page_callback' => 'CRM_Booking_Page_Dashlet',           'title' => 'Mes rendez-vous',       'permission' => 'access booking'],
    'civicrm/booking/appointment/new'       => ['page_callback' => 'CRM_Booking_Form_Appointment',       'title' => 'Nouveau rendez-vous',   'permission' => 'access booking'],
    'civicrm/booking/appointment/edit'      => ['page_callback' => 'CRM_Booking_Form_Appointment',       'title' => 'Modifier un rendez-vous','permission' => 'access booking'],
    'civicrm/booking/appointment/slots'     => ['page_callback' => 'CRM_Booking_Page_AppointmentSlots',  'title' => 'Creneaux disponibles',  'permission' => 'access booking'],
    'civicrm/booking/appointment/cancel'    => ['page_callback' => 'CRM_Booking_Page_AppointmentAction',  'title' => 'Annuler RDV',           'permission' => 'access booking'],
    'civicrm/booking/appointment/complete'  => ['page_callback' => 'CRM_Booking_Page_AppointmentAction',  'title' => 'Terminer RDV',          'permission' => 'access booking'],
    'civicrm/booking/invoice/generate'      => ['page_callback' => 'CRM_Booking_Page_InvoiceAction',      'title' => 'Generer facture',       'permission' => 'access booking'],
    'civicrm/booking/availability/add'      => ['page_callback' => 'CRM_Booking_Form_Availability',       'title' => 'Ajouter disponibilite', 'permission' => 'access booking'],
    'civicrm/booking/availability/delete'   => ['page_callback' => 'CRM_Booking_Page_AppointmentAction',  'title' => 'Suppr. disponibilite',  'permission' => 'access booking'],
    'civicrm/booking/exception/add'         => ['page_callback' => 'CRM_Booking_Form_ExceptionForm',      'title' => 'Ajouter exception',     'permission' => 'access booking'],
    'civicrm/booking/exception/delete'      => ['page_callback' => 'CRM_Booking_Page_AppointmentAction',  'title' => 'Suppr. exception',      'permission' => 'access booking'],
    'civicrm/booking/appointment-types'     => ['page_callback' => 'CRM_Booking_Page_AppointmentTypeList','title' => 'Types de RDV',          'permission' => 'administer booking'],
    'civicrm/booking/appointment-type/edit' => ['page_callback' => 'CRM_Booking_Form_AppointmentType',    'title' => 'Editer type',           'permission' => 'administer booking'],
    'civicrm/booking/therapists'            => ['page_callback' => 'CRM_Booking_Page_TherapistList',      'title' => 'Therapeutes',           'permission' => 'administer booking'],
    'civicrm/booking/therapist/edit'        => ['page_callback' => 'CRM_Booking_Form_Therapist',          'title' => 'Editer therapeute',     'permission' => 'administer booking'],
    'civicrm/booking/settings'              => ['page_callback' => 'CRM_Booking_Form_Settings',           'title' => 'Parametres Booking',    'permission' => 'administer booking'],
    'civicrm/booking/locations'             => ['page_callback' => 'CRM_Booking_Page_LocationList',      'title' => 'Locaux',                'permission' => 'administer booking'],
    'civicrm/booking/location/edit'         => ['page_callback' => 'CRM_Booking_Form_LocationForm',      'title' => 'Editer un local',       'permission' => 'administer booking'],
    'civicrm/booking/workdays'              => ['page_callback' => 'CRM_Booking_Page_Workdays',          'title' => 'Jours de travail',      'permission' => 'access booking'],
    'civicrm/booking/workdays/save'         => ['page_callback' => 'CRM_Booking_Page_WorkdaySave',       'title' => 'Enregistrer une journee','permission' => 'access booking'],
  ];
}

function _booking_run_migrations(): void {
  CRM_Core_DAO::executeQuery("
    CREATE TABLE IF NOT EXISTS `civicrm_booking_migrations` (
      `id` varchar(64) NOT NULL,
      `applied_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");
  $files = glob(__DIR__ . '/sql/migrations/*.sql') ?: [];
  sort($files);
  foreach ($files as $file) {
    $id = basename($file, '.sql');
    $already = CRM_Core_DAO::singleValueQuery(
      'SELECT COUNT(*) FROM civicrm_booking_migrations WHERE id = %1',
      [1 => [$id, 'String']]
    );
    if ($already) continue;
    $sql = file_get_contents($file);
    foreach (_booking_split_sql($sql, TRUE) as $stmt) {
      try {
        CRM_Core_DAO::executeQuery($stmt);
      }
      catch (Throwable $e) {
        if (_booking_is_idempotent_error($e->getMessage())) {
          // Le schéma est déjà dans l'état voulu : rien à faire
          continue;
        }
        throw $e;
      }
    }
    CRM_Core_DAO::executeQuery('INSERT IGNORE INTO civicrm_booking_migrations (id) VALUES (%1)', [1 => [$id, 'String']]);
  }
}
/**
 * Une erreur SQL est-elle « inoffensive » (schéma déjà dans l'état voulu) ?
 * Cas typiques : install fraîche où 0001 crée déjà ce que 0002/0003 modifient.
 */
function _booking_is_idempotent_error(string $msg): bool {
  // CiviCRM enveloppe les erreurs MySQL dans des messages PEAR laconiques :
  // « DB Error: no such field » plutôt que « Unknown column ». Les deux
  // formulations sont donc testées.
  $harmless = [
    // Messages PEAR / CiviCRM
    'no such field',
    'no such table',
    'already exists',
    // Messages MySQL bruts
    'Duplicate entry',
    'Duplicate column',
    'Duplicate key',
    'Unknown column',
    'check that column/key exists',
    "doesn't exist",
  ];
  foreach ($harmless as $needle) {
    if (stripos($msg, $needle) !== FALSE) {
      return TRUE;
    }
  }
  return FALSE;
}

/**
 * Mettre à la corbeille les activités « Rendez-vous » qui ne correspondent
 * plus à aucun rendez-vous enregistré. Elles ne sont pas supprimées :
 * restaurables depuis la corbeille CiviCRM si besoin.
 */
function _booking_trash_orphan_activities(): void {
  try {
    $typeId = (int) CRM_Core_DAO::singleValueQuery(
      "SELECT value FROM civicrm_option_value v
       JOIN civicrm_option_group g ON g.id = v.option_group_id
       WHERE g.name = 'activity_type' AND v.name = 'booking_appointment'
       LIMIT 1"
    );
    if (!$typeId) return;

    CRM_Core_DAO::executeQuery(
      "UPDATE civicrm_activity a
       LEFT JOIN civicrm_booking_appointment b ON b.civicrm_activity_id = a.id
       SET a.is_deleted = 1
       WHERE a.activity_type_id = %1
         AND a.is_deleted = 0
         AND b.id IS NULL",
      [1 => [$typeId, 'Integer']]
    );
  }
  catch (Throwable $e) {
    // Nettoyage secondaire : ne doit jamais empêcher la désinstallation
  }
}

function _booking_run_sql_file(string $path): void {
  if (!file_exists($path)) return;
  foreach (_booking_split_sql(file_get_contents($path)) as $stmt) {
    try { CRM_Core_DAO::executeQuery($stmt); } catch (Throwable $e) {}
  }
}

/**
 * Découper un script SQL en instructions.
 *
 * Un simple explode(';') casse dès qu'un point-virgule apparaît à l'intérieur
 * d'une chaîne — typiquement dans un COMMENT. Ce découpage suit l'état des
 * guillemets pour ne couper qu'aux véritables fins d'instruction.
 *
 * @return string[] Instructions non vides, lignes de commentaire retirées.
 */
function _booking_split_sql(string $sql, bool $honourGuards = FALSE): array {
  $kept  = [];
  $guard = NULL;

  foreach (explode("\n", $sql) as $line) {
    // Garde : « -- @skipIfColumnExists table colonne »
    // La prochaine instruction est ignorée si la colonne est déjà en place.
    if ($honourGuards
      && preg_match('/^\s*--\s*@skipIfColumnExists\s+(\S+)\s+(\S+)/', $line, $m)) {
      $guard = _booking_column_exists($m[1], $m[2]) ? 'skip' : NULL;
      $kept[] = $guard === 'skip' ? '/*__BOOKING_SKIP__*/' : '';
      continue;
    }
    if (preg_match('/^\s*--/', $line)) {
      continue;
    }
    $kept[] = $line;
  }

  $sql = implode("\n", $kept);

  $statements = [];
  $current    = '';
  $quote      = NULL;
  $length     = strlen($sql);

  for ($i = 0; $i < $length; $i++) {
    $char = $sql[$i];

    if ($quote !== NULL) {
      $current .= $char;
      if ($char === $quote) {
        // Guillemet doublé : caractère littéral, la chaîne continue
        if ($i + 1 < $length && $sql[$i + 1] === $quote) {
          $current .= $sql[++$i];
        }
        else {
          $quote = NULL;
        }
      }
      elseif ($char === '\\' && $i + 1 < $length) {
        $current .= $sql[++$i];
      }
      continue;
    }

    if ($char === "'" || $char === '"' || $char === '`') {
      $quote    = $char;
      $current .= $char;
      continue;
    }

    if ($char === ';') {
      $trimmed = trim($current);
      if ($trimmed !== '') $statements[] = $trimmed;
      $current = '';
      continue;
    }

    $current .= $char;
  }

  $trimmed = trim($current);
  if ($trimmed !== '') $statements[] = $trimmed;

  // Écarter les instructions désactivées par une garde
  return array_values(array_filter($statements, function ($stmt) {
    return strpos($stmt, '__BOOKING_SKIP__') === FALSE;
  }));
}

/**
 * Une colonne existe-t-elle dans une table ?
 * Utilisé par les gardes des migrations, pour éviter d'exécuter une
 * altération déjà satisfaite par le schéma initial.
 */
function _booking_column_exists(string $table, string $column): bool {
  try {
    $count = CRM_Core_DAO::singleValueQuery(
      'SELECT COUNT(*) FROM information_schema.COLUMNS
       WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = %1
         AND COLUMN_NAME = %2',
      [
        1 => [$table, 'String'],
        2 => [$column, 'String'],
      ]
    );
    return (int) $count > 0;
  }
  catch (Throwable $e) {
    return FALSE;
  }
}

function _booking_insert_nav(array &$menu, ?string $parentName, array $item): void {
  if ($parentName === NULL) {
    foreach ($menu as $node) {
      if (($node['attributes']['name'] ?? '') === $item['name']) return;
    }
    $menu[] = ['attributes' => $item, 'child' => []];
    return;
  }
  foreach ($menu as &$node) {
    if (($node['attributes']['name'] ?? '') === $parentName) {
      foreach ($node['child'] ?? [] as $child) {
        if (($child['attributes']['name'] ?? '') === $item['name']) return;
      }
      $node['child'][] = ['attributes' => $item, 'child' => []];
      return;
    }
    if (!empty($node['child'])) _booking_insert_nav($node['child'], $parentName, $item);
  }
}
