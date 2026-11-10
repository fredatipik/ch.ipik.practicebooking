<?php
namespace CRM\Booking\Form;

use CRM\Booking\BAO\AppointmentType as BAOAppointmentType;
use CRM\Booking\BAO\Therapist;
use CRM\Booking\Utils;

/**
 * Formulaire CiviCRM — création et édition d'un type de rendez-vous.
 */
class AppointmentType extends \CRM_Core_Form {

  protected ?array $_type = NULL;
  protected int $_id = 0;
  protected array $_therapistOptions = [];

  public function preProcess(): void {
    parent::preProcess();
    \CRM_Core_Permission::check('administer booking') || \CRM_Core_Error::statusBounce(ts('Accès refusé.'));
    $this->_id = (int) \CRM_Utils_Request::retrieve('id', 'Positive', $this, FALSE, 0);
    if ($this->_id) {
      $this->_type = BAOAppointmentType::getById($this->_id);
      if (!$this->_type) \CRM_Core_Error::statusBounce(ts('Type introuvable.'));
    }
    $this->setTitle($this->_id ? ts('Modifier le type de rendez-vous') : ts('Nouveau type de rendez-vous'));
    \CRM\Booking\Utils::setBreadCrumb([[
      'title' => ts('Types de rendez-vous'),
      'url'   => \CRM_Utils_System::url('civicrm/booking/appointment-types', 'reset=1'),
    ]]);
  }

  public function buildQuickForm(): void {
    $this->add('text', 'label', ts('Libellé'), ['maxlength' => 255], TRUE);
    $this->add('textarea', 'description', ts('Description'), ['rows' => 3, 'maxlength' => 1000]);
    $this->add('text', 'duration_minutes', ts('Durée (minutes)'), ['size' => 5], TRUE);
    $this->addRule('duration_minutes', ts('Entier positif requis.'), 'positiveInteger');
    $this->add('text', 'color', ts('Couleur (hex)'), ['maxlength' => 7, 'class' => 'ipik-color-input'], TRUE);
    $this->add('select', 'therapist_selector', ts('Stratégie d\'attribution'), Utils::getSelectorOptions());
    $this->add('text', 'slot_interval_minutes', ts('Intervalle entre créneaux (minutes)'), ['size' => 5]);
    $this->addRule('slot_interval_minutes', ts('Entier positif requis.'), 'positiveInteger');
    $this->add('checkbox', 'requires_existing_contact', ts('Réservé aux patients connectés'));
    $this->add('checkbox', 'requires_account_creation', ts('Création de compte WordPress obligatoire'));
    $this->add('text', 'weight', ts('Ordre d\'affichage'), ['size' => 4]);
    $this->add('checkbox', 'is_active', ts('Actif'));

    // Intervenant·es éligibles
    $this->_therapistOptions = [];
    foreach (Therapist::getAll() as $t) {
      $this->_therapistOptions[$t['id']] = $t['display_name'];
      $this->addElement('checkbox', 'therapist_ids_' . $t['id'], NULL, '');
    }
    $this->assign('therapistOptions', $this->_therapistOptions);

    $this->addButtons([['type' => 'submit', 'name' => ts('Enregistrer'), 'isDefault' => TRUE]]);
    $cancelURL = \CRM_Utils_System::url('civicrm/booking/appointment-types');
    $this->assign('cancelURL', $cancelURL);
    $this->assign('recordId', $this->_id);
  }

  public function setDefaultValues(): array {
    if ($this->_type) {
      $defaults = $this->_type;
      foreach ($this->_type['therapist_ids'] as $tid) {
        $defaults['therapist_ids_' . $tid] = 1;
      }
      return $defaults;
    }
    return [
      'duration_minutes'   => 60,
      'color'              => '#10b981',
      'therapist_selector' => 'round_robin',
      'is_active'          => 1,
      'weight'             => 0,
    ];
  }

  public function postProcess(): void {
    $values = $this->exportValues();

    $therapistIds = [];
    foreach ($this->_therapistOptions as $tid => $tname) {
      if (!empty($values['therapist_ids_' . $tid])) {
        $therapistIds[] = (int) $tid;
      }
    }

    $params = [
      'id'                        => $this->_id ?: NULL,
      'label'                     => $values['label'],
      'description'               => $values['description'] ?? NULL,
      'duration_minutes'          => (int) $values['duration_minutes'],
      'color'                     => $values['color'],
      'therapist_selector'        => $values['therapist_selector'],
      'requires_existing_contact' => !empty($values['requires_existing_contact']) ? 1 : 0,
      'requires_account_creation' => !empty($values['requires_account_creation']) ? 1 : 0,
      'weight'                    => (int) ($values['weight'] ?? 0),
      'slot_interval_minutes'     => !empty($values['slot_interval_minutes'])
        ? (int) $values['slot_interval_minutes'] : NULL,
      'is_active'                 => !empty($values['is_active']) ? 1 : 0,
      'therapist_ids'             => $therapistIds,
    ];

    BAOAppointmentType::save($params);

    \CRM_Core_Session::setStatus(ts('Type de rendez-vous enregistré.'), ts('Succès'), 'success');
    \CRM_Utils_System::redirect(\CRM_Utils_System::url('civicrm/booking/appointment-types'));
  }
}
