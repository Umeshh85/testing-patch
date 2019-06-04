<?php

namespace Drupal\KernelTests\Core\Common;

use Drupal\KernelTests\KernelTestBase;

/**
 * @covers ::drupal_process_states
 * @group Common
 */
class DrupalProcessStatesTest extends KernelTestBase {

  /**
   * Tests that drupal_process_states() doesn't cause any notices.
   */
  public function testProcessStates() {
    // Create a form element without specifying a '#type'.
    $form_element = [
      '#markup' => 'Custom markup',
      '#states' => [
        'visible' => [
          ':select[name="method"]' => ['value' => 'email'],
        ],
      ],
    ];
    drupal_process_states($form_element);
    $this->assertArrayHasKey('#attributes', $form_element);
  }

}
