<?php

namespace Drupal\Tests\field\Functional\EntityReference\Views;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Site\Settings;
use Drupal\field\Entity\FieldConfig;
use Drupal\Tests\BrowserTestBase;
use Drupal\views\Views;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests entity reference selection handler.
 *
 * @group entity_reference
 */
class SelectionTest extends BrowserTestBase {

  public static $modules = ['node', 'views', 'entity_reference_test', 'entity_test'];

  /**
   * An array of node titles, keyed by content type and node ID.
   *
   * @var array
   */
  protected $nodes = [];

  /**
   * The entity reference field to test.
   *
   * @var \Drupal\Core\Field\FieldDefinitionInterface
   */
  protected $field;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create content types and nodes.
    $type1 = $this->drupalCreateContentType()->id();
    $type2 = $this->drupalCreateContentType()->id();
    $node1 = $this->drupalCreateNode(['type' => $type1, 'title' => 'Test first node']);
    $node2 = $this->drupalCreateNode(['type' => $type1, 'title' => 'Test second node']);
    $node3 = $this->drupalCreateNode(['type' => $type2, 'title' => 'Test third node']);

    foreach ([$node1, $node2, $node3] as $node) {
      $this->nodes[] = $node;
      $this->nodeTitles[$node->getType()][$node->id()] = $node->label();
    }

    // Create a field.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'test_field',
      'entity_type' => 'entity_test',
      'translatable' => FALSE,
      'settings' => [
        'target_type' => 'node',
      ],
      'type' => 'entity_reference',
      'cardinality' => '1',
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'test_bundle',
      'settings' => [
        'handler' => 'views',
        'handler_settings' => [
          'view' => [
            'view_name' => 'test_entity_reference',
            'display_name' => 'entity_reference_1',
            'arguments' => [],
          ],
        ],
      ],
    ]);
    $field->save();
    $this->field = $field;
  }

  /**
   * Tests that the Views selection handles the views output properly.
   */
  public function testAutocompleteOutput() {
    // Reset any internal static caching.
    \Drupal::service('entity_type.manager')->getStorage('node')->resetCache();

    $view = Views::getView('test_entity_reference');
    $view->setDisplay();

    // Enable the display of the 'type' field so we can test that the output
    // does not contain only the entity label.
    $fields = $view->displayHandlers->get('entity_reference_1')->getOption('fields');
    $fields['type']['exclude'] = FALSE;
    $view->displayHandlers->get('entity_reference_1')->setOption('fields', $fields);
    $view->save();

    // Prepare the selection settings key needed by the entity reference
    // autocomplete route.
    $target_type = 'node';
    $selection_handler = $this->field->getSetting('handler');
    $selection_settings = $this->field->getSetting('handler_settings');
    $selection_settings_key = Crypt::hmacBase64(serialize($selection_settings) . $target_type . $selection_handler, Settings::getHashSalt());
    \Drupal::keyValue('entity_autocomplete')->set($selection_settings_key, $selection_settings);

    $result = Json::decode($this->drupalGet('entity_reference_autocomplete/' . $target_type . '/' . $selection_handler . '/' . $selection_settings_key, ['query' => ['q' => 't']]));

    $expected = [
      0 => [
        'value' => $this->nodes[0]->bundle() . ': ' . $this->nodes[0]->label() . ' (' . $this->nodes[0]->id() . ')',
        'label' => '<span class="views-field views-field-type"><span class="field-content">' . $this->nodes[0]->bundle() . '</span></span>: <span class="views-field views-field-title"><span class="field-content">' . $this->nodes[0]->label() . '</span></span>',
      ],
      1 => [
        'value' => $this->nodes[1]->bundle() . ': ' . $this->nodes[1]->label() . ' (' . $this->nodes[1]->id() . ')',
        'label' => '<span class="views-field views-field-type"><span class="field-content">' . $this->nodes[1]->bundle() . '</span></span>: <span class="views-field views-field-title"><span class="field-content">' . $this->nodes[1]->label() . '</span></span>',
      ],
      2 => [
        'value' => $this->nodes[2]->bundle() . ': ' . $this->nodes[2]->label() . ' (' . $this->nodes[2]->id() . ')',
        'label' => '<span class="views-field views-field-type"><span class="field-content">' . $this->nodes[2]->bundle() . '</span></span>: <span class="views-field views-field-title"><span class="field-content">' . $this->nodes[2]->label() . '</span></span>',
      ],
    ];
    $this->assertEqual($result, $expected, 'The autocomplete result of the Views entity reference selection handler contains the proper output.');
  }

  /**
   * Confirm the expected results are returned.
   *
   * @param array $result
   *   Query results keyed by node type and nid.
   */
  protected function assertResults(array $result) {
    $success = FALSE;
    foreach ($result as $node_type => $values) {
      foreach ($values as $nid => $label) {
        if (!$success = $this->nodeTitles[$node_type][$nid] == trim(strip_tags($label))) {
          // There was some error, so break.
          break;
        }
      }
    }
    $this->assertTrue($success, 'Views selection handler returned expected values.');
  }

  /**
   * Tests the selection handler.
   */
  public function testSelectionHandler() {
    // Get values from selection handler.
    $handler = $this->container->get('plugin.manager.entity_reference_selection')->getSelectionHandler($this->field);
    $result = $handler->getReferenceableEntities();
    $this->assertResults($result);
  }

  /**
   * Tests the selection handler with a relationship.
   */
  public function testSelectionHandlerRelationship() {
    // Add a relationship to the view.
    $view = Views::getView('test_entity_reference');
    $view->setDisplay();
    $view->displayHandlers->get('default')->setOption('relationships', [
      'test_relationship' => [
        'id' => 'uid',
        'table' => 'node_field_data',
        'field' => 'uid',
      ],
    ]);

    // Add a filter depending on the relationship to the test view.
    $view->displayHandlers->get('default')->setOption('filters', [
      'uid' => [
        'id' => 'uid',
        'table' => 'users_field_data',
        'field' => 'uid',
        'relationship' => 'test_relationship',
      ],
    ]);

    // Set view to distinct so only one row per node is returned.
    $query_options = $view->display_handler->getOption('query');
    $query_options['options']['distinct'] = TRUE;
    $view->display_handler->setOption('query', $query_options);
    $view->save();

    // Get values from the selection handler.
    $handler = $this->container->get('plugin.manager.entity_reference_selection')->getSelectionHandler($this->field);
    $result = $handler->getReferenceableEntities();
    $this->assertResults($result);
  }

}
