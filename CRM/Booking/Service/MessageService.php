<?php
namespace CRM\Booking\Service;

use CRM\Booking\Utils;

/**
 * MessageService — textes du formulaire public.
 *
 * Ces quelques phrases s'adressent directement aux patients : chaque cabinet
 * a sa façon de les formuler. Elles sont donc modifiables depuis les
 * paramètres de l'extension, sans toucher au code.
 *
 * Un message laissé vide reprend le texte livré par défaut.
 */
class MessageService {

  public const EMAIL_GATE       = 'msg_email_gate';
  public const CONTACT_NOT_FOUND = 'msg_contact_not_found';
  public const NO_SLOTS         = 'msg_no_slots';
  public const CONFIRMATION     = 'msg_confirmation';

  /**
   * Définition des messages : libellé pour les paramètres, texte par défaut,
   * et brève indication du moment où le patient le lit.
   */
  public static function definitions(): array {
    return [
      self::EMAIL_GATE => [
        'label'   => ts('Connexion requise'),
        'help'    => ts('Affiché lorsqu\'un visiteur non connecté choisit un type de rendez-vous réservé aux patients déjà suivis.'),
        'default' => ts('Ce type de rendez-vous est réservé aux patients déjà suivis. Merci de vous connecter pour poursuivre.'),
      ],
      self::CONTACT_NOT_FOUND => [
        'label'   => ts('Compte sans dossier patient'),
        'help'    => ts('Affiché lorsqu\'une personne connectée n\'est rattachée à aucun dossier de patient·e.'),
        'default' => ts('Votre compte n\'est rattaché à aucun dossier de patient·e. Si vous êtes déjà suivi·e chez nous, contactez-nous directement pour convenir d\'un rendez-vous.'),
      ],
      self::NO_SLOTS => [
        'label'   => ts('Aucun créneau disponible'),
        'help'    => ts('Affiché lorsque la période consultée ne comporte aucune disponibilité.'),
        'default' => ts('Aucun créneau disponible sur cette période.'),
      ],
      self::CONFIRMATION => [
        'label'   => ts('Confirmation de réservation'),
        'help'    => ts('Affiché après l\'enregistrement du rendez-vous.'),
        'default' => ts('Un e-mail de confirmation vous a été envoyé. À bientôt !'),
      ],
    ];
  }

  /**
   * Texte configuré, ou texte par défaut si aucun n'a été saisi.
   */
  public static function get(string $key): string {
    $custom = trim((string) Utils::getSetting($key, ''));
    if ($custom !== '') {
      return $custom;
    }

    $definitions = self::definitions();
    return $definitions[$key]['default'] ?? '';
  }

  /**
   * Tous les messages, prêts à être transmis au formulaire public.
   *
   * @return array<string, string>
   */
  public static function all(): array {
    $messages = [];
    foreach (array_keys(self::definitions()) as $key) {
      $messages[$key] = self::get($key);
    }
    return $messages;
  }
}
