<?php

namespace Drupal\Tests\datetime\Functional\Handler;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Tests exposed Drupal\datetime\Plugin\views\filter\Date handler.
 *
 * @group datetime
 */
class FilterDateTest extends ViewTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_filter_datetime_exposed'];

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node', 'datetime', 'datetime_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE) {
    parent::setUp($import_test_views);

    // Add a 'datetime' date field to page node bundle.
    NodeType::create([
      'type' => 'page',
      'name' => 'Page',
    ])->save();
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_date',
      'type' => 'datetime',
      'entity_type' => 'node',
      'settings' => ['datetime_type' => DateTimeItem::DATETIME_TYPE_DATETIME],
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_name' => 'field_date',
      'entity_type' => 'node',
      'bundle' => 'page',
    ]);
    $field->save();

    // Views needs to be aware of the new field.
    $this->container->get('views.views_data')->clear();

    // Load test views.
    ViewTestData::createTestViews(get_class($this), ['datetime_test']);
  }

  /**
   * Test 'datetime' type exposed filter.
   */
  public function testDateTimeExposedFilter() {
    $this->drupalLogin($this->drupalCreateUser(['access content']));

    // Verify that exposed input element exists in the output with the proper
    // types.
    $this->drupalGet('test-filter-datetime-exposed');
    $this->assertSession()->elementExists('css', 'input[id="edit-field-date-value-date"][type="date"]');
    $this->assertSession()->elementExists('css', 'input[id="edit-field-date-value-time"][type="time"]');

  }

  /**
   * Test 'date' only type exposed filter.
   */
  public function testDateOnlyExposedFilter() {
    $this->drupalLogin($this->drupalCreateUser(['access content']));

    // Change field storage to date-only.
    $storage = FieldStorageConfig::load('node.field_date');
    $storage->setSetting('datetime_type', DateTimeItem::DATETIME_TYPE_DATE);
    $storage->save();

    // Views needs to be aware of the field change.
    $this->container->get('views.views_data')->clear();

    // Verify that exposed input element exists in the output with only the
    // date component.
    $this->drupalGet('test-filter-datetime-exposed');
    $this->assertSession()->elementExists('css', 'input[id="edit-field-date-value-date"][type="date"]');
    $this->assertSession()->elementNotExists('css', 'input[id="edit-field-date-value-time"]');

  }

}
