<?php

namespace Drupal\datetime_states\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a settings page for the File Hosting module.
 */
class StateForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return "datetime_states_form";
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['toggle_invisible'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Invisible'),
    );
    $form['toggle_disabled'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Disabled'),
    );
    $form['toggle_required'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Required'),
    );


    $form['date'] = array(
      '#type' => 'datetime',
      '#title' => $this->t('Datetime'),
      '#description' => $this->t('A datetime form element.'),
      '#states' => [
        'invisible' => [
          ':input[name="toggle_invisible"]' => ['checked' => TRUE],
        ],
        'disabled' => [
          ':input[name="toggle_disabled"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="toggle_required"]' => ['checked' => TRUE],
        ],
      ],
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

}
