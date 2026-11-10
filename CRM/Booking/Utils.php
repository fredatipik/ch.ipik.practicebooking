<?php
namespace CRM\Booking;

/**
 * Utilitaires généraux pour ch.ipik.practicebooking.
 */
class Utils {

  /**
   * Lire un paramètre depuis civicrm_booking_settings.
   */
  public static function getSetting(string $key, mixed $default = NULL): mixed {
    $value = \CRM_Core_DAO::singleValueQuery(
      'SELECT `value` FROM `civicrm_booking_settings` WHERE `key` = %1',
      [1 => [$key, 'String']]
    );
    return $value !== NULL ? $value : $default;
  }

  /**
   * Écrire un paramètre dans civicrm_booking_settings.
   */
  public static function setSetting(string $key, mixed $value): void {
    \CRM_Core_DAO::executeQuery(
      'INSERT INTO `civicrm_booking_settings` (`key`, `value`)
       VALUES (%1, %2)
       ON DUPLICATE KEY UPDATE `value` = %2',
      [
        1 => [$key, 'String'],
        2 => [(string) $value, 'String'],
      ]
    );
  }

  /**
   * URL de l'onglet agenda pour un intervenant·e.
   */
  public static function agendaTabUrl(int $contactId): string {
    return \CRM_Utils_System::url(
      'civicrm/booking/therapist-agenda',
      ['cid' => $contactId]
    );
  }

  /**
   * URL de base du plugin (pour assets JS/CSS).
   */
  public static function resourceUrl(): string {
    return \CRM_Core_Resources::singleton()->getUrl('ch.ipik.practicebooking');
  }

  /**
   * Formater une datetime pour l'affichage (selon locale CiviCRM).
   */
  public static function formatDatetime(string $datetime): string {
    return \CRM_Utils_Date::customFormat($datetime, '%d.%m.%Y %H:%M');
  }

  /**
   * Vérifier si l'extension swissQRinvoice est active.
   */
  /**
   * Adresses d'expédition déclarées dans CiviCRM
   * (Administration → Communications → Adresses d'expédition).
   *
   * Lues par l'API, qui sait où CiviCRM les range : une requête SQL directe
   * sur le groupe d'options ne les retrouve pas sur toutes les versions.
   *
   * @return array<int, array{value: string, label: string, is_default: bool}>
   */
  private static function fromAddressRows(): array {
    static $rows = NULL;
    if ($rows !== NULL) {
      return $rows;
    }

    $rows = [];
    try {
      $result = \civicrm_api3('OptionValue', 'get', [
        'option_group_id' => 'from_email_address',
        'is_active'       => 1,
        'options'         => ['limit' => 0, 'sort' => 'weight'],
        'return'          => ['value', 'label', 'is_default'],
      ]);
      foreach ($result['values'] as $row) {
        $label = html_entity_decode((string) ($row['label'] ?? ''), ENT_QUOTES, 'UTF-8');
        if ($label === '') {
          continue;
        }
        $rows[] = [
          'value'      => (string) ($row['value'] ?? ''),
          'label'      => $label,
          'is_default' => !empty($row['is_default']),
        ];
      }
    }
    catch (\Throwable $e) {
      self::logError('Lecture des adresses d\'expédition impossible', [
        'error' => $e->getMessage(),
      ]);
    }
    return $rows;
  }

  /**
   * Adresses proposées dans les paramètres.
   *
   * @return array<string, string> valeur de l'option => libellé « "Nom" <adresse> »
   */
  public static function getFromAddressOptions(): array {
    $options = [];
    foreach (self::fromAddressRows() as $row) {
      $options[$row['value']] = $row['label'];
    }
    return $options;
  }

  /**
   * Adresse d'expédition par défaut du domaine, ou chaîne vide.
   */
  public static function getDefaultFromAddress(): string {
    $rows = self::fromAddressRows();
    foreach ($rows as $row) {
      if ($row['is_default']) {
        return $row['label'];
      }
    }
    return $rows[0]['label'] ?? '';
  }

  /**
   * Libellé complet correspondant au réglage enregistré, ou chaîne vide.
   *
   * Le réglage contient la valeur de l'option. Les versions antérieures y
   * stockaient le libellé lui-même : il est repris tel quel.
   */
  public static function resolveFromSetting(string $stored): string {
    $stored = trim($stored);
    if ($stored === '') {
      return '';
    }

    foreach (self::fromAddressRows() as $row) {
      if ($row['value'] === $stored) {
        return $row['label'];
      }
    }

    // Une valeur purement numérique qui ne correspond à rien : option
    // supprimée depuis, et non un libellé.
    if (ctype_digit($stored)) {
      return '';
    }
    return html_entity_decode($stored, ENT_QUOTES, 'UTF-8');
  }

  public static function isSwissQRInvoiceActive(): bool {
    static $active = NULL;
    if ($active !== NULL) return $active;

    try {
      $result = \civicrm_api3('Extension', 'get', [
        'key'    => 'ch.ipik.swissQRinvoice',
        'status' => 'installed',
      ]);
      $active = (int) ($result['count'] ?? 0) > 0;
    }
    catch (\Throwable $e) {
      $active = FALSE;
    }

    return $active;
  }

  /**
   * Retourner les stratégies d'attribution disponibles (pour les selects).
   */
  public static function getSelectorOptions(): array {
    return [
      'round_robin'  => ts('Round-robin (rotation équitable)'),
      'least_loaded' => ts('Moins chargé (moins de RDV sur la période)'),
      'random'       => ts('Aléatoire'),
    ];
  }

  /**
   * Poser le fil d'Ariane Booking sur une page/formulaire.
   * $extra = [['title' => '…', 'url' => '…'], …] pour les niveaux intermédiaires.
   */
  public static function setBreadCrumb(array $extra = []): void {
    $crumbs = [[
      'title' => ts('Booking'),
      'url'   => \CRM_Utils_System::url('civicrm/booking/appointments', 'reset=1'),
    ]];
    foreach ($extra as $c) {
      $crumbs[] = $c;
    }
    \CRM_Utils_System::appendBreadCrumb($crumbs);
  }

  /**
   * Logger une erreur dans le log CiviCRM.
   */
  public static function logError(string $message, array $context = []): void {
    \Civi::log()->error('[ch.ipik.practicebooking] ' . $message, $context);
  }
}
