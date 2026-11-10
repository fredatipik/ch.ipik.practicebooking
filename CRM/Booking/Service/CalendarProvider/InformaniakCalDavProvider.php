<?php
namespace CRM\Booking\Service\CalendarProvider;

use CRM\Booking\Utils;

/**
 * Accès CalDAV aux agendas Infomaniak.
 *
 * Trois opérations HTTP suffisent :
 *   REPORT  free-busy-query, pour connaître les créneaux occupés
 *   PUT     dépôt d'un fichier iCal, pour créer un événement
 *   DELETE  retrait de ce fichier
 *
 * Les identifiants sont globaux (civicrm_booking_settings), l'URL d'agenda
 * est propre à chaque intervenant ou local.
 *
 * Note Infomaniak : l'identifiant CalDAV est le code du compte (ex. FK03484),
 * pas l'adresse e-mail. Voir kSuite → Calendrier → paramètres → CalDAV.
 */
class InformaniakCalDavProvider implements CalendarProviderInterface {

  private const TIMEZONE = 'Europe/Zurich';
  private const TIMEOUT  = 10;

  private string $user;
  private string $password;

  public function __construct() {
    $this->user     = trim((string) Utils::getSetting('caldav_user', ''));
    $this->password = (string) Utils::getSetting('caldav_password', '');
  }

  public function isAvailable(): bool {
    return $this->user !== '' && $this->password !== '';
  }

  // -------------------------------------------------------------------------
  // Agenda d'un intervenant
  // -------------------------------------------------------------------------

  public function getBusySlots(int $therapistId, string $dateFrom, string $dateTo): array {
    $url = $this->therapistCalendarUrl($therapistId);
    return $url ? $this->getBusySlotsForUrl($url, $dateFrom, $dateTo) : [];
  }

  public function createEvent(array $appointment): ?string {
    $url = $this->therapistCalendarUrl((int) ($appointment['therapist_id'] ?? 0));
    if (!$url) return NULL;

    $summary = $appointment['type_label'] ?? 'Rendez-vous';
    $desc    = 'Patient : ' . ($appointment['contact_name'] ?? '');
    if (!empty($appointment['notes'])) {
      $desc .= "\n" . $appointment['notes'];
    }

    return $this->createEventInCalendar(
      $url,
      $appointment['start_datetime'],
      $appointment['end_datetime'],
      $summary,
      $desc
    );
  }

  public function deleteEvent(int $therapistId, string $externalEventId): void {
    $url = $this->therapistCalendarUrl($therapistId);
    if ($url) {
      $this->deleteEventInCalendar($url, $externalEventId);
    }
  }

  public function updateEvent(int $therapistId, string $externalEventId, array $appointment): void {
    $this->deleteEvent($therapistId, $externalEventId);
    $this->createEvent($appointment);
  }

  // -------------------------------------------------------------------------
  // Agenda quelconque, désigné par son URL
  // -------------------------------------------------------------------------

  /**
   * Ne lève jamais d'exception : en cas d'échec, retourne un tableau vide.
   */
  public function getBusySlotsForUrl(string $calendarUrl, string $dateFrom, string $dateTo): array {
    try {
      $url = $this->cleanUrl($calendarUrl);
      if (!$url) return [];

      $xml = sprintf(
        '<?xml version="1.0" encoding="utf-8" ?>' .
        '<C:free-busy-query xmlns:C="urn:ietf:params:xml:ns:caldav">' .
        '<C:time-range start="%s" end="%s"/>' .
        '</C:free-busy-query>',
        $this->toUtcIso($dateFrom),
        $this->toUtcIso($dateTo)
      );

      $response = $this->request('REPORT', $url, $xml, [
        'Content-Type: application/xml; charset=utf-8',
        'Depth: 1',
      ]);

      return $response === NULL ? [] : $this->parseFreeBusy($response);
    }
    catch (\Throwable $e) {
      Utils::logError('Lecture CalDAV', ['url' => $calendarUrl, 'error' => $e->getMessage()]);
      return [];
    }
  }

  public function createEventInCalendar(
    string $calendarUrl,
    string $startDatetime,
    string $endDatetime,
    string $summary,
    string $description = ''
  ): ?string {
    $url = $this->cleanUrl($calendarUrl);
    if (!$url) return NULL;

    $uid  = $this->generateUid();
    $ical = $this->buildIcal($uid, $startDatetime, $endDatetime, $summary, $description);

    $eventUrl = rtrim($url, '/') . '/' . rawurlencode($uid) . '.ics';
    $response = $this->request('PUT', $eventUrl, $ical, [
      'Content-Type: text/calendar; charset=utf-8',
      'If-None-Match: *',
    ]);

    return $response !== NULL ? $uid : NULL;
  }

  /**
   * Un événement absent (404) n'est pas considéré comme une erreur.
   */
  public function deleteEventInCalendar(string $calendarUrl, string $externalEventId): void {
    $url = $this->cleanUrl($calendarUrl);
    if (!$url) return;

    $eventUrl = rtrim($url, '/') . '/' . rawurlencode($externalEventId) . '.ics';
    $this->request('DELETE', $eventUrl, '', [], [404]);
  }

  // -------------------------------------------------------------------------
  // Construction iCal
  // -------------------------------------------------------------------------

  private function buildIcal(
    string $uid,
    string $start,
    string $end,
    string $summary,
    string $description
  ): string {
    $lines = [
      'BEGIN:VCALENDAR',
      'VERSION:2.0',
      'PRODID:-//IPIK//ch.ipik.practicebooking//FR',
      'CALSCALE:GREGORIAN',
      'BEGIN:VTIMEZONE',
      'TZID:Europe/Zurich',
      'BEGIN:STANDARD',
      'DTSTART:19701025T030000',
      'RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10',
      'TZOFFSETFROM:+0200',
      'TZOFFSETTO:+0100',
      'TZNAME:CET',
      'END:STANDARD',
      'BEGIN:DAYLIGHT',
      'DTSTART:19700329T020000',
      'RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3',
      'TZOFFSETFROM:+0100',
      'TZOFFSETTO:+0200',
      'TZNAME:CEST',
      'END:DAYLIGHT',
      'END:VTIMEZONE',
      'BEGIN:VEVENT',
      'UID:' . $uid,
      'DTSTAMP:' . gmdate('Ymd\THis\Z'),
      'DTSTART;TZID=Europe/Zurich:' . $this->toLocalIcal($start),
      'DTEND;TZID=Europe/Zurich:' . $this->toLocalIcal($end),
      'SUMMARY:' . $this->escapeText($summary),
      'DESCRIPTION:' . $this->escapeText($description),
      'STATUS:CONFIRMED',
      'END:VEVENT',
      'END:VCALENDAR',
    ];

    return implode("\r\n", $lines) . "\r\n";
  }

  /**
   * Échappement RFC 5545.
   */
  private function escapeText(string $text): string {
    return str_replace(
      ["\\", "\n", "\r", ';', ','],
      ['\\\\', '\\n', '', '\\;', '\\,'],
      trim($text)
    );
  }

  // -------------------------------------------------------------------------
  // HTTP
  // -------------------------------------------------------------------------

  /**
   * @param int[] $tolerate Codes HTTP à ne pas signaler comme erreurs.
   * @return string|null    Corps de la réponse, NULL en cas d'échec.
   */
  private function request(
    string $method,
    string $url,
    string $body = '',
    array  $headers = [],
    array  $tolerate = []
  ): ?string {
    $ch = curl_init($url);

    $opts = [
      CURLOPT_CUSTOMREQUEST  => $method,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_USERPWD        => $this->user . ':' . $this->password,
      CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
      CURLOPT_SSL_VERIFYPEER => TRUE,
      CURLOPT_TIMEOUT        => self::TIMEOUT,
      CURLOPT_CONNECTTIMEOUT => 5,
      CURLOPT_HTTPHEADER     => array_merge(
        ['User-Agent: ch.ipik.practicebooking/0.6.0'],
        $headers
      ),
    ];

    // CURLOPT_POST écraserait CURLOPT_CUSTOMREQUEST : ne jamais le poser.
    if ($body !== '') {
      $opts[CURLOPT_POSTFIELDS] = $body;
    }

    curl_setopt_array($ch, $opts);

    $response = curl_exec($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error !== '') {
      Utils::logError('CalDAV — erreur réseau', [
        'method' => $method, 'url' => $url, 'error' => $error,
      ]);
      return NULL;
    }

    if ($status >= 200 && $status < 300) {
      return is_string($response) ? $response : '';
    }

    if (in_array($status, $tolerate, TRUE)) {
      return '';
    }

    Utils::logError('CalDAV — réponse inattendue', [
      'method' => $method,
      'url'    => $url,
      'status' => $status,
      'body'   => substr((string) $response, 0, 400),
    ]);
    return NULL;
  }

  // -------------------------------------------------------------------------
  // Analyse et conversions
  // -------------------------------------------------------------------------

  /**
   * Extraire les plages FREEBUSY : « 20260901T090000Z/20260901T100000Z »
   * ou « 20260901T090000Z/PT1H ».
   */
  private function parseFreeBusy(string $response): array {
    $slots = [];
    if (!preg_match_all('/FREEBUSY[^:\r\n]*:([^\r\n]+)/i', $response, $matches)) {
      return $slots;
    }

    foreach ($matches[1] as $line) {
      foreach (explode(',', $line) as $range) {
        $parts = explode('/', trim($range));
        if (count($parts) !== 2) continue;

        $start = $this->fromUtcIso($parts[0]);
        if ($start === NULL) continue;

        $end = str_starts_with($parts[1], 'P')
          ? $this->addDuration($start, $parts[1])
          : $this->fromUtcIso($parts[1]);

        if ($end !== NULL) {
          $slots[] = ['start' => $start, 'end' => $end];
        }
      }
    }
    return $slots;
  }

  private function addDuration(string $start, string $duration): ?string {
    try {
      $dt = new \DateTime($start, new \DateTimeZone(self::TIMEZONE));
      $dt->add(new \DateInterval($duration));
      return $dt->format('Y-m-d H:i:s');
    }
    catch (\Throwable $e) {
      return NULL;
    }
  }

  /**
   * Heure locale → UTC au format compact, pour les requêtes CalDAV.
   */
  private function toUtcIso(string $datetime): string {
    $dt = new \DateTime($datetime, new \DateTimeZone(self::TIMEZONE));
    $dt->setTimezone(new \DateTimeZone('UTC'));
    return $dt->format('Ymd\THis\Z');
  }

  /**
   * UTC compact → heure locale lisible.
   */
  private function fromUtcIso(string $iso): ?string {
    try {
      $dt = new \DateTime(trim($iso), new \DateTimeZone('UTC'));
      $dt->setTimezone(new \DateTimeZone(self::TIMEZONE));
      return $dt->format('Y-m-d H:i:s');
    }
    catch (\Throwable $e) {
      return NULL;
    }
  }

  /**
   * Heure locale au format iCal, à coupler avec TZID.
   */
  private function toLocalIcal(string $datetime): string {
    return (new \DateTime($datetime, new \DateTimeZone(self::TIMEZONE)))
      ->format('Ymd\THis');
  }

  private function therapistCalendarUrl(int $therapistId): ?string {
    if (!$therapistId) return NULL;
    $url = \CRM_Core_DAO::singleValueQuery(
      'SELECT calendar_url FROM civicrm_booking_therapist WHERE id = %1',
      [1 => [$therapistId, 'Integer']]
    );
    return $this->cleanUrl((string) $url);
  }

  /**
   * Retirer les paramètres d'URL (?export) et valider.
   */
  private function cleanUrl(string $url): ?string {
    $url = preg_replace('/\?.*$/', '', trim($url));
    return $url !== '' ? $url : NULL;
  }

  private function generateUid(): string {
    return sprintf(
      'booking-%s-%s@ipik.ch',
      date('Ymd-His'),
      substr(md5(uniqid('', TRUE)), 0, 8)
    );
  }
}
