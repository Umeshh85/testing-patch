<?php

namespace Drupal\Tests\system\Kernel\Entity;

use Drupal\entity_test\Entity\EntityTestWithBundle;
use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests handlers for entities declared in the entity_test module.
 *
 * @group system
 */
class EntityTestEntityHandlersTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['system', 'entity_test'];

  /**
   * Tests form handlers in entity type declarations for the entity_test module.
   */
  public function testEntityFormHandlerDefinitions() {
    /** @var \Drupal\Core\Entity\EntityTypeInterface $definition */
    $definitions = $this
      ->container
      ->get('entity_type.manager')
      ->getDefinitions();

    foreach ($definitions as $plugin_id => $definition) {
      $handlers = $definition->getHandlerClasses();
      if (array_key_exists('form', $handlers)) {
        // Loop through the list of form classes from the entity annotation.
        $form_classes = $handlers['form'];
        foreach ($form_classes as $operation => $class) {
          // Confirm that the class of the
          $form_object = $this->container->get('entity_type.manager')
            ->getFormObject($plugin_id, $operation);
          $this->assertEquals($form_classes[$operation], get_class($form_object), 'The form class matches the class in the entity annotation.');
        }
      }
    }
  }

}
