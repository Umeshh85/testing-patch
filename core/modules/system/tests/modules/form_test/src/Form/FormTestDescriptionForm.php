<?php

namespace Drupal\form_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form for testing form element description display options.
 *
 * @internal
 *
 * @see \Drupal\system\Tests\Form\ElementsLabelsTest::testFormDescriptions()
 */
class FormTestDescriptionForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'form_test_description_display';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $types = ['textfield', 'datetime'];

    foreach ($types as $type) {

      $form['form_' . $type . '_test_description_before'] = [
        '#type' => $type,
        '#title' => ucfirst($type) . ' test for description before element',
        '#description' => 'Textfield test for description before element',
        '#description_display' => 'before',
        '#default_value' => '',
      ];

      $form['form_' . $type . '_test_description_after'] = [
        '#type' => $type,
        '#title' => ucfirst($type) . ' test for description after element',
        '#description' => 'Textfield test for description after element',
        '#description_display' => 'after',
        '#default_value' => '',
      ];

      $form['form_' . $type . '_test_description_invisible'] = [
        '#type' => $type,
        '#title' => ucfirst($type) . ' test for visually-hidden description',
        '#description' => ucfirst($type) . ' test for visually-hidden description',
        '#description_display' => 'invisible',
        '#default_value' => '',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // The test that uses this form does not submit the form so this is empty.
  }

}
