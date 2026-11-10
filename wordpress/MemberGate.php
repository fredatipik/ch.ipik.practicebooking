<?php
namespace CRM\Booking\WordPress;

use CRM\Booking\BAO\AppointmentType;

/**
 * MemberGate — vérifie les droits d'accès au formulaire de booking
 * selon le type de rendez-vous et le statut du visiteur.
 *
 * Un type réservé aux patients déjà suivis exige une connexion au site, avec
 * un compte rattaché à une fiche CiviCRM. Il n'existe volontairement aucune
 * vérification « par adresse e-mail » : elle ne prouverait rien — n'importe
 * qui peut saisir l'adresse d'autrui — et permettrait de sonder quelles
 * adresses appartiennent à des patients du cabinet.
 */
class MemberGate {

  /**
   * Le visiteur peut-il accéder à ce type de rendez-vous ?
   *
   * @return array{allowed: bool, reason: string|null}
   *   reason : invalid_type | login_required | contact_not_found
   */
  public static function check(int $appointmentTypeId): array {
    $type = AppointmentType::getById($appointmentTypeId);
    if (!$type || !$type['is_active']) {
      return ['allowed' => FALSE, 'reason' => 'invalid_type'];
    }

    if ($type['requires_existing_contact']) {
      if (!self::isLoggedIn()) {
        return ['allowed' => FALSE, 'reason' => 'login_required'];
      }
      if (!self::getLoggedInCiviContact()) {
        return ['allowed' => FALSE, 'reason' => 'contact_not_found'];
      }
    }

    return ['allowed' => TRUE, 'reason' => NULL];
  }

  /**
   * Un utilisateur WordPress est-il connecté ?
   */
  public static function isLoggedIn(): bool {
    return function_exists('is_user_logged_in') && is_user_logged_in();
  }

  /**
   * Récupérer le contact CiviCRM lié à l'utilisateur WP connecté.
   * Retourne null si non connecté ou pas de lien CiviCRM.
   */
  public static function getLoggedInCiviContact(): ?array {
    if (!function_exists('get_current_user_id')) return NULL;
    $wpUserId = get_current_user_id();
    if (!$wpUserId) return NULL;

    try {
      $result = \civicrm_api3('UFMatch', 'get', [
        'uf_id'  => $wpUserId,
        'return' => 'contact_id',
      ]);
      if ($result['count'] > 0) {
        $contactId = (int) reset($result['values'])['contact_id'];
        $contact   = \civicrm_api3('Contact', 'getsingle', [
          'id'     => $contactId,
          'return' => 'id,first_name,last_name,email',
        ]);
        return $contact;
      }
    }
    catch (\Throwable $e) {}

    return NULL;
  }
}
