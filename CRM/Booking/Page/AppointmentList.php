<?php
namespace CRM\Booking\Page;

use CRM\Booking\BAO\Appointment;
use CRM\Booking\BAO\Therapist;
use CRM\Booking\BAO\AppointmentType;
use CRM\Booking\Utils;

/**
 * Page AppointmentList — liste globale de tous les RDV (vue admin).
 */
class AppointmentList extends \CRM_Core_Page {

  public function run(): void {
    \CRM_Core_Permission::check('access booking') || \CRM_Core_Error::statusBounce(ts('Accès refusé.'));
    Utils::setBreadCrumb();

    $filters = [
      'therapist_id' => (int) \CRM_Utils_Request::retrieve('therapist_id', 'Positive', $this, FALSE, 0) ?: NULL,
      // Par défaut : depuis le début du mois en cours, sans borne haute.
      // Une page de rendez-vous sert d'abord à voir ce qui vient — un
      // rendez-vous pris pour le mois suivant ne doit pas disparaître.
      'date_from'    => \CRM_Utils_Request::retrieve('date_from', 'String', $this, FALSE, date('Y-m-01')),
      'date_to'      => \CRM_Utils_Request::retrieve('date_to', 'String', $this, FALSE, ''),
      'status'       => \CRM_Utils_Request::retrieve('status', 'String', $this, FALSE, ''),
      'order'        => 'ASC',
    ];

    $appointments = Appointment::getAll($filters, 100, 0);

    // URLs de facturation et lien vers l'agenda du intervenant·e
    foreach ($appointments as &$appt) {
      $cid = (int) $appt['contact_id'];

      $therapistContactId = (int) \CRM_Core_DAO::singleValueQuery(
        'SELECT contact_id FROM civicrm_booking_therapist WHERE id = %1',
        [1 => [(int) $appt['therapist_id'], 'Integer']]
      );
      $appt['therapist_url'] = $therapistContactId
        ? \CRM_Utils_System::url('civicrm/booking/therapist-agenda', [
            'cid' => $therapistContactId, 'reset' => 1,
          ])
        : NULL;

      $appt['invoice_url'] = \CRM_Utils_System::url('civicrm/swissqr/invoice/new', [
        'cid'   => $cid,
        'reset' => 1,
      ]);
      $appt['contribution_url'] = \CRM_Utils_System::url('civicrm/contribute/add', [
        'cid'     => $cid,
        'reset'   => 1,
        'action'  => 'add',
        'context' => 'standalone',
      ] + $this->contributionDefaults());
    }
    unset($appt);

    $this->assign('appointments', $appointments);
    $this->assign('filters', $filters);
    $this->assign('therapists', Therapist::getAll());
    $this->assign('types', AppointmentType::getAll());
    $this->assign('statusOptions', [
      ''          => ts('Tous les statuts'),
      'confirmed' => ts('Confirmé'),
      'pending'   => ts('En attente'),
      'completed' => ts('Terminé'),
      'cancelled' => ts('Annulé'),
    ]);
    $this->assign('invoiceAvailable', Utils::isSwissQRInvoiceActive());

    \CRM_Core_Resources::singleton()->addStyleFile('ch.ipik.practicebooking', 'css/booking.css');

    parent::run();
  }

  /**
   * Paramètres pré-remplis pour le formulaire de contribution CiviCRM.
   */
  private function contributionDefaults(): array {
    $params = [];

    $financialTypeId = (int) Utils::getSetting('contribution_financial_type_id', 0);
    if ($financialTypeId) {
      $params['financial_type_id'] = $financialTypeId;
    }

    $statusId = (int) Utils::getSetting('contribution_status_id', 0);
    if ($statusId) {
      $params['contribution_status_id'] = $statusId;
    }

    return $params;
  }
}
