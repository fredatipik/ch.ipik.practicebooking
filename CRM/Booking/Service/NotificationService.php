<?php
namespace CRM\Booking\Service;

use CRM\Booking\BAO\Therapist;
use CRM\Booking\Utils;

/**
 * NotificationService — emails de confirmation et de rappel.
 *
 * Les contenus proviennent de modèles de messages CiviCRM créés à
 * l'installation et modifiables dans :
 *   Administration → Communications → Modèles de messages
 *
 * Voir TemplateService pour la liste et les identifiants.
 *
 * Si un modèle est introuvable, un contenu de repli minimal est utilisé.
 * L'envoi passe par le SMTP configuré dans CiviCRM.
 */
class NotificationService {

  private const TPL_CLIENT    = TemplateService::CONFIRMATION_CLIENT;
  private const TPL_THERAPIST = TemplateService::CONFIRMATION_THERAPIST;
  private const TPL_REMINDER  = TemplateService::REMINDER_CLIENT;
  private const TPL_CANCEL    = TemplateService::CANCELLATION;

  /**
   * Envoyer les confirmations (patient + intervenant·e) après une réservation.
   */
  public function sendConfirmation(array $appointment, array $contactData): void {
    $this->sendToClient($appointment, $contactData);
    $this->sendToTherapist($appointment);
  }

  /**
   * Prévenir le patient et le intervenant·e d'une annulation.
   */
  public function sendCancellation(array $appointment, string $reason = ''): void {
    $appointment['cancel_reason'] = $reason;

    // Patient
    $clientEmail = $this->getContactEmail((int) ($appointment['contact_id'] ?? 0));
    if ($clientEmail) {
      $this->sendTemplate(
        self::TPL_CANCEL,
        (int) $appointment['contact_id'],
        $clientEmail,
        $appointment['contact_name'] ?? '',
        $appointment
      );
    }

    // Intervenant·e
    $therapistId = (int) ($appointment['therapist_id'] ?? 0);
    $therapistEmail = Therapist::getEmail($therapistId);
    if ($therapistEmail) {
      $therapist = Therapist::getById($therapistId);
      $this->sendTemplate(
        self::TPL_CANCEL,
        $therapist ? (int) $therapist['contact_id'] : 0,
        $therapistEmail,
        $appointment['therapist_name'] ?? '',
        $appointment
      );
    }
  }

  /**
   * Email principal d'un contact CiviCRM.
   */
  private function getContactEmail(int $contactId): ?string {
    if (!$contactId) return NULL;
    $email = \CRM_Core_DAO::singleValueQuery(
      'SELECT email FROM civicrm_email WHERE contact_id = %1 AND is_primary = 1 LIMIT 1',
      [1 => [$contactId, 'Integer']]
    );
    return $email ?: NULL;
  }

  /**
   * Rappel au patient (appelé par le cron ReminderJob).
   */
  public function sendReminder(array $appointment): void {
    $email = $appointment['contact_email'] ?? '';
    if (!$email) return;

    $this->sendTemplate(
      self::TPL_REMINDER,
      (int) $appointment['contact_id'],
      $email,
      $appointment['contact_name'] ?? '',
      $appointment
    );
  }

  // -------------------------------------------------------------------------
  // Envois
  // -------------------------------------------------------------------------

  private function sendToClient(array $appointment, array $contactData): void {
    $email = trim($contactData['email'] ?? '');
    if (!$email) return;

    $name = trim(($contactData['first_name'] ?? '') . ' ' . ($contactData['last_name'] ?? ''));

    $this->sendTemplate(
      self::TPL_CLIENT,
      (int) ($appointment['contact_id'] ?? 0),
      $email,
      $name,
      $appointment
    );
  }

  private function sendToTherapist(array $appointment): void {
    $therapistId = (int) ($appointment['therapist_id'] ?? 0);
    $email = Therapist::getEmail($therapistId);
    if (!$email) return;

    $therapist = Therapist::getById($therapistId);
    $contactId = $therapist ? (int) $therapist['contact_id'] : 0;

    $this->sendTemplate(
      self::TPL_THERAPIST,
      $contactId,
      $email,
      $appointment['therapist_name'] ?? '',
      $appointment
    );
  }

  /**
   * Rendre un modèle CiviCRM et l'envoyer.
   *
   * @param string $workflowName Nom technique du modèle
   * @param int    $contactId    Destinataire (pour les tokens {contact.*})
   */
  private function sendTemplate(
    string $workflowName,
    int    $contactId,
    string $toEmail,
    string $toName,
    array  $appointment
  ): void {
    try {
      $tokens = $this->buildTokens($appointment);
      $body   = $this->renderTemplate($workflowName, $contactId, $tokens);

      if ($body === NULL) {
        Utils::logError('Modèle de message introuvable', ['workflow' => $workflowName]);
        return;
      }

      // CRM_Utils_Mail::send() reçoit son argument par référence :
      // il faut lui passer une variable, non un tableau littéral.
      $from = $this->getFromAddress();
      $mailParams = [
        'groupName' => 'booking_notification',
        'from'      => $from,
        'toName'    => $toName,
        'toEmail'   => $toEmail,
        'subject'   => $body['subject'],
        'html'      => $body['html'],
        'text'      => $body['text'],
      ];

      // send() ne lève pas d'exception lorsque le serveur SMTP refuse le
      // message : il renvoie FALSE. Sans ce contrôle, l'échec passerait
      // inaperçu — ni journal côté extension, ni indice sur l'expéditeur.
      if (!\CRM_Utils_Mail::send($mailParams)) {
        Utils::logError('Envoi email refusé par le serveur SMTP', [
          'workflow' => $workflowName,
          'from'     => $from,
          'to'       => $toEmail,
        ]);
      }
    }
    catch (\Throwable $e) {
      Utils::logError('Envoi email échoué', [
        'workflow' => $workflowName,
        'to'       => $toEmail,
        'error'    => $e->getMessage(),
      ]);
    }
  }

  /**
   * Rendre un modèle CiviCRM avec les tokens contact + booking.
   * Retourne ['subject' => …, 'html' => …, 'text' => …] ou NULL.
   */
  private function renderTemplate(string $templateKey, int $contactId, array $tokens): ?array {
    $tpl = TemplateService::getTemplate($templateKey);

    if ($tpl === NULL || (empty($tpl['html']) && empty($tpl['text']))) {
      return $this->fallbackBody($templateKey, $tokens);
    }

    // Nos tokens d'abord : le processeur de CiviCRM efface les tokens
    // qu'il ne connaît pas, ce qui viderait les {booking.*} avant qu'on
    // ait pu les substituer.
    $tpl = [
      'subject' => $this->replaceTokens($tpl['subject'], $tokens, FALSE),
      'html'    => $this->replaceTokens($tpl['html'],    $tokens, TRUE),
      'text'    => $this->replaceTokens($tpl['text'],    $tokens, FALSE),
    ];

    // Tokens CiviCRM standards {contact.*} ensuite
    if ($contactId) {
      $tpl = $this->resolveCiviTokens($tpl, $contactId);
    }

    return $tpl;
  }

  /**
   * Résoudre les tokens CiviCRM ({contact.first_name}, etc.) pour un contact.
   */
  private function resolveCiviTokens(array $tpl, int $contactId): array {
    try {
      $processor = new \Civi\Token\TokenProcessor(\Civi::dispatcher(), [
        'controller' => __CLASS__,
        'schema'     => ['contactId'],
      ]);
      $processor->addMessage('subject', $tpl['subject'], 'text/plain');
      $processor->addMessage('html',    $tpl['html'],    'text/html');
      $processor->addMessage('text',    $tpl['text'],    'text/plain');
      $processor->addRow(['contactId' => $contactId]);
      $processor->evaluate();

      foreach ($processor->getRows() as $row) {
        return [
          'subject' => $row->render('subject'),
          'html'    => $row->render('html'),
          'text'    => $row->render('text'),
        ];
      }
    }
    catch (\Throwable $e) {
      Utils::logError('Résolution des tokens CiviCRM échouée', ['error' => $e->getMessage()]);
    }
    return $tpl;
  }

  /**
   * Construire les valeurs des tokens {booking.*}.
   */
  private function buildTokens(array $appointment): array {
    $start = !empty($appointment['start_datetime'])
      ? new \DateTime($appointment['start_datetime'])
      : NULL;

    return [
      'booking.type'         => $appointment['type_label'] ?? '',
      'booking.date'         => $start ? $this->formatDateFr($start) : '',
      'booking.time'         => $start ? $start->format('H:i') : '',
      'booking.duration'     => (string) ($appointment['duration_minutes'] ?? ''),
      'booking.therapist'    => $appointment['therapist_name'] ?? '',
      'booking.contact_name' => $appointment['contact_name'] ?? '',
      'booking.notes'        => $appointment['notes'] ?? '',
      'booking.location'     => $appointment['location_name'] ?? '',
      'booking.location_address' => $appointment['location_address'] ?? '',
      'booking.cancel_reason'=> !empty($appointment['cancel_reason'])
        ? 'Motif : ' . $appointment['cancel_reason']
        : '',
    ];
  }

  /**
   * Remplacer les tokens {booking.*} dans un texte.
   */
  private function replaceTokens(string $text, array $tokens, bool $escapeHtml = TRUE): string {
    foreach ($tokens as $key => $value) {
      $value = (string) $value;
      if ($escapeHtml) {
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
      }
      $text = str_replace('{' . $key . '}', $value, $text);
    }
    return $text;
  }

  /**
   * Contenu de repli si le modèle CiviCRM est absent.
   */
  private function fallbackBody(string $workflowName, array $tokens): array {
    $date = $tokens['booking.date'] ?? '';
    $time = $tokens['booking.time'] ?? '';
    $type = $tokens['booking.type'] ?? 'Rendez-vous';

    if ($workflowName === self::TPL_CANCEL) {
      $subject = "Annulation du rendez-vous du {$date}";
      $html = "<p>Le rendez-vous <strong>{$type}</strong> du <strong>{$date}</strong> "
            . "à <strong>{$time}</strong> a été annulé.</p>";
    }
    elseif ($workflowName === self::TPL_THERAPIST) {
      $subject = "Nouveau rendez-vous — {$date} à {$time}";
      $html = "<p>Un nouveau rendez-vous a été enregistré :</p>"
            . "<ul><li>{$type}</li><li>{$date} à {$time}</li>"
            . "<li>Patient : " . ($tokens['booking.contact_name'] ?? '') . "</li></ul>";
    }
    elseif ($workflowName === self::TPL_REMINDER) {
      $subject = "Rappel : votre rendez-vous du {$date}";
      $html = "<p>Rappel : vous avez rendez-vous <strong>{$date}</strong> "
            . "à <strong>{$time}</strong> pour {$type}.</p>";
    }
    else {
      $subject = "Confirmation de votre rendez-vous du {$date}";
      $html = "<p>Votre rendez-vous <strong>{$type}</strong> est confirmé "
            . "pour le <strong>{$date}</strong> à <strong>{$time}</strong>.</p>";
    }

    return [
      'subject' => $subject,
      'html'    => $html,
      'text'    => strip_tags(str_replace(['</p>', '</li>'], "\n", $html)),
    ];
  }

  /**
   * Date en français, ex. « lundi 28 septembre 2026 ».
   */
  private function formatDateFr(\DateTime $date): string {
    $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $mois  = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
              'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    return sprintf(
      '%s %d %s %d',
      $jours[(int) $date->format('w')],
      (int) $date->format('j'),
      $mois[(int) $date->format('n')],
      (int) $date->format('Y')
    );
  }

  /**
   * Adresse d'expédition au format « "Nom" <email> » exigé par CRM_Utils_Mail.
   */
  private function getFromAddress(): string {
    // 1. Adresse choisie dans les paramètres de l'extension. Elle détermine
    //    aussi le serveur SMTP retenu lorsque ch.ipik.smtprouter est installée.
    $stored = trim((string) Utils::getSetting('from_email', ''));
    if ($stored !== '') {
      $chosen = Utils::resolveFromSetting($stored);
      if ($chosen !== '') {
        return $this->normalizeFrom($chosen);
      }
      Utils::logError('Adresse d\'expédition choisie introuvable dans CiviCRM', [
        'reglage' => $stored,
      ]);
    }

    // 2. Adresse d'expédition par défaut du domaine
    $default = Utils::getDefaultFromAddress();
    if ($default !== '') {
      return $this->normalizeFrom($default);
    }

    // 3. Dernier recours. Une adresse inventée sera très probablement
    //    refusée par le SMTP (« Sender mismatch ») : on le signale.
    $host = parse_url(\CRM_Utils_System::baseURL(), PHP_URL_HOST) ?: 'localhost';
    Utils::logError('Aucune adresse d\'expédition configurée : repli sur une adresse générique', [
      'repli' => 'noreply@' . $host,
    ]);
    return $this->normalizeFrom('noreply@' . $host);
  }

  /**
   * Ramener une adresse à la forme « Nom » <adresse> attendue par
   * CRM_Utils_Mail, qu'elle soit nue ou déjà libellée.
   */
  private function normalizeFrom(string $value, string $fallbackName = ''): string {
    $name  = $fallbackName;
    $email = trim($value);

    if (preg_match('/^"?([^"<]*)"?\s*<([^>]+)>/', $email, $m)) {
      $label = trim($m[1]);
      if ($label !== '') $name = $label;
      $email = trim($m[2]);
    }

    if ($name === '') {
      try {
        $name = \CRM_Core_BAO_Domain::getDomain()->name ?: 'Cabinet';
      }
      catch (\Throwable $e) {
        $name = 'Cabinet';
      }
    }

    return sprintf('"%s" <%s>', $name, $email);
  }
}
