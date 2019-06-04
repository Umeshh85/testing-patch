<?php

namespace Drupal\Tests\views\Functional\Handler;

use Drupal\config\Tests\SchemaCheckTestTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\views\Views;

/**
 * Tests the core Drupal\views\Plugin\views\filter\Date handler.
 *
 * @group views
 */
class FilterDateTest extends ViewTestBase {
  use SchemaCheckTestTrait;

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = [
    'test_filter_date_between',
    'test_filter_date_between_exposed',
    'test_filter_date_exposed_operators',
  ];

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node', 'views_ui', 'datetime'];

  protected function setUp($import_test_views = TRUE) {
    parent::setUp($import_test_views);

    // Add a date field so we can test datetime handling.
    NodeType::create([
      'type' => 'page',
      'name' => 'Page',
    ])->save();

    // Setup a field storage and field, but also change the views data for the
    // entity_test entity type.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_date',
      'type' => 'datetime',
      'entity_type' => 'node',
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_name' => 'field_date',
      'entity_type' => 'node',
      'bundle' => 'page',
    ]);
    $field->save();

    // Add some basic test nodes.
    $this->nodes = [];
    $this->nodes[] = $this->drupalCreateNode(['created' => 100000, 'field_date' => 10000]);
    $this->nodes[] = $this->drupalCreateNode(['created' => 200000, 'field_date' => 20000]);
    $this->nodes[] = $this->drupalCreateNode(['created' => 300000, 'field_date' => 30000]);
    $this->nodes[] = $this->drupalCreateNode(['created' => time() + 86400, 'field_date' => time() + 86400]);

    $this->map = [
      'nid' => 'nid',
    ];
  }

  /**
   * Runs other test methods.
   */
  public function testDateFilter() {
    $this->_testOffset();
    $this->_testBetween();
    $this->_testUiValidation();
    $this->_testFilterDateUI();
    $this->_testFilterDatetimeUI();
    $this->_testExposedFilterTimestampUI();
    $this->_testExposedFilterExposedOperator();
  }

  /**
   * Test the general offset functionality.
   */
  protected function _testOffset() {
    $view = Views::getView('test_filter_date_between');

    // Test offset for simple operator.
    $view->initHandlers();
    $view->filter['created']->operator = '>';
    $view->filter['created']->value['type'] = 'offset';
    $view->filter['created']->value['value'] = '+1 hour';
    $view->executeDisplay('default');
    $expected_result = [
      ['nid' => $this->nodes[3]->id()],
    ];
    $this->assertIdenticalResultset($view, $expected_result, $this->map);
    $view->destroy();

    // Test offset for between operator.
    $view->initHandlers();
    $view->filter['created']->operator = 'between';
    $view->filter['created']->value['type'] = 'offset';
    $view->filter['created']->value['max'] = '+2 days';
    $view->filter['created']->value['min'] = '+1 hour';
    $view->executeDisplay('default');
    $expected_result = [
      ['nid' => $this->nodes[3]->id()],
    ];
    $this->assertIdenticalResultset($view, $expected_result, $this->map);
  }

  /**
   * Tests the filter operator between/not between.
   */
  protected function _testBetween() {
    $view = Views::getView('test_filter_date_between');

    // Test between with min and max.
    $view->initHandlers();
    $view->filter['created']->operator = 'between';
    $view->filter['created']->value['min'] = format_date(150000, 'custom', 'Y-m-d H:i:s');
    $view->filter['created']->value['max'] = format_date(200000, 'custom', 'Y-m-d H:i:s');
    $view->executeDisplay('default');
    $expected_result = [
      ['nid' => $this->nodes[1]->id()],
    ];
    $this->assertIdenticalResultset($view, $expected_result, $this->map);
    $view->destroy();

    // Test between with just max.
    $view->initHandlers();
    $view->filter['created']->operator = 'between';
    $view->filter['created']->value['max'] = format_date(200000, 'custom', 'Y-m-d H:i:s');
    $view->executeDisplay('default');
    $expected_result = [
      ['nid' => $this->nodes[0]->id()],
      ['nid' => $this->nodes[1]->id()],
    ];
    $this->assertIdenticalResultset($view, $expected_result, $this->map);
    $view->destroy();

    // Test not between with min and max.
    $view->initHandlers();
    $view->filter['created']->operator = 'not between';
    $view->filter['created']->value['min'] = format_date(100000, 'custom', 'Y-m-d H:i:s');
    $view->filter['created']->value['max'] = format_date(200000, 'custom', 'Y-m-d H:i:s');

    $view->executeDisplay('default');
    $expected_result = [
      ['nid' => $this->nodes[2]->id()],
      ['nid' => $this->nodes[3]->id()],
    ];
    $this->assertIdenticalResultset($view, $expected_result, $this->map);
    $view->destroy();

    // Test not between with just max.
    $view->initHandlers();
    $view->filter['created']->operator = 'not between';
    $view->filter['created']->value['max'] = format_date(200000, 'custom', 'Y-m-d H:i:s');
    $view->executeDisplay('default');
    $expected_result = [
      ['nid' => $this->nodes[2]->id()],
      ['nid' => $this->nodes[3]->id()],
    ];
    $this->assertIdenticalResultset($view, $expected_result, $this->map);
  }

  /**
   * Make sure the validation callbacks works.
   */
  protected function _testUiValidation() {

    $this->drupalLogin($this->drupalCreateUser(['administer views', 'administer site configuration']));

    $this->drupalGet('admin/structure/views/view/test_filter_date_between/edit');
    $this->drupalGet('admin/structure/views/nojs/handler/test_filter_date_between/default/filter/created');

    $edit = [];
    // Generate a definitive wrong value, which should be checked by validation.
    $edit['options[value][value]'] = $this->randomString() . '-------';
    $this->drupalPostForm(NULL, $edit, t('Apply'));
    $this->assertText(t('Invalid date format.'), 'Make sure that validation is run and the invalidate date format is identified.');
  }

  /**
   * Test date filter UI.
   */
  protected function _testFilterDateUI() {
    $this->drupalLogin($this->drupalCreateUser(['administer views']));
    $this->drupalGet('admin/structure/views/nojs/handler/test_filter_date_between/default/filter/created');
    $this->drupalPostForm(NULL, [], t('Expose filter'));
    $this->drupalPostForm(NULL, [], t('Grouped filters'));

    $edit = [];
    $edit['options[group_info][group_items][1][title]'] = 'simple-offset';
    $edit['options[group_info][group_items][1][operator]'] = '>';
    $edit['options[group_info][group_items][1][value][type]'] = 'offset';
    $edit['options[group_info][group_items][1][value][value]'] = '+1 hour';
    $edit['options[group_info][group_items][2][title]'] = 'between-offset';
    $edit['options[group_info][group_items][2][operator]'] = 'between';
    $edit['options[group_info][group_items][2][value][type]'] = 'offset';
    $edit['options[group_info][group_items][2][value][min]'] = '+1 hour';
    $edit['options[group_info][group_items][2][value][max]'] = '+2 days';
    $edit['options[group_info][group_items][3][title]'] = 'between-date';
    $edit['options[group_info][group_items][3][operator]'] = 'between';
    $edit['options[group_info][group_items][3][value][min]'] = format_date(150000, 'custom', 'Y-m-d H:i:s');
    $edit['options[group_info][group_items][3][value][max]'] = format_date(250000, 'custom', 'Y-m-d H:i:s');

    $this->drupalPostForm(NULL, $edit, t('Apply'));

    $this->drupalGet('admin/structure/views/nojs/handler/test_filter_date_between/default/filter/created');
    foreach ($edit as $name => $value) {
      $this->assertFieldByName($name, $value);
      if (strpos($name, '[value][type]')) {
        $radio = $this->cssSelect('input[name="' . $name . '"][checked="checked"][type="radio"]');
        $this->assertEqual($radio[0]->getAttribute('value'), $value);
      }
    }

    $this->drupalPostForm('admin/structure/views/view/test_filter_date_between', [], t('Save'));
    $this->assertConfigSchemaByName('views.view.test_filter_date_between');

    // Test that the exposed filter works as expected.
    $path = 'test_filter_date_between-path';
    $this->drupalPostForm('admin/structure/views/view/test_filter_date_between/edit', [], 'Add Page');
    $this->drupalPostForm('admin/structure/views/nojs/display/test_filter_date_between/page_1/path', ['path' => $path], 'Apply');
    $this->drupalPostForm(NULL, [], t('Save'));

    $this->drupalGet($path);
    $this->drupalPostForm(NULL, [], 'Apply');
    $results = $this->cssSelect('.view-content .field-content');
    $this->assertEqual(count($results), 4);
    $this->drupalPostForm(NULL, ['created' => '1'], 'Apply');
    $results = $this->cssSelect('.view-content .field-content');
    $this->assertEqual(count($results), 1);
    $this->assertEqual($results[0]->getText(), $this->nodes[3]->id());
    $this->drupalPostForm(NULL, ['created' => '2'], 'Apply');
    $results = $this->cssSelect('.view-content .field-content');
    $this->assertEqual(count($results), 1);
    $this->assertEqual($results[0]->getText(), $this->nodes[3]->id());
    $this->drupalPostForm(NULL, ['created' => '3'], 'Apply');
    $results = $this->cssSelect('.view-content .field-content');
    $this->assertEqual(count($results), 1);
    $this->assertEqual($results[0]->getText(), $this->nodes[1]->id());

    // Change the filter to a single filter to test the schema when the operator
    // is not exposed.
    $this->drupalPostForm('admin/structure/views/nojs/handler/test_filter_date_between/default/filter/created', [], t('Single filter'));
    $edit = [];
    $edit['options[operator]'] = '>';
    $edit['options[value][type]'] = 'date';
    $edit['options[value][value]'] = format_date(350000, 'custom', 'Y-m-d H:i:s');
    $this->drupalPostForm(NULL, $edit, t('Apply'));
    $this->drupalPostForm('admin/structure/views/view/test_filter_date_between', [], t('Save'));
    $this->assertConfigSchemaByName('views.view.test_filter_date_between');

    // Test that the filter works as expected.
    $this->drupalGet($path);
    $results = $this->cssSelect('.view-content .field-content');
    $this->assertEqual(count($results), 1);
    $this->assertEqual($results[0]->getText(), $this->nodes[3]->id());
    $this->drupalPostForm(NULL, [
      'created[date]' => format_date(250000, 'custom', 'Y-m-d'),
      'created[time]' => format_date(250000, 'custom', 'H:i:s'),
    ], 'Apply');
    $results = $this->cssSelect('.view-content .field-content');
    $this->assertEqual(count($results), 2);
    $this->assertEqual($results[0]->getText(), $this->nodes[2]->id());
    $this->assertEqual($results[1]->getText(), $this->nodes[3]->id());
  }

  /**
   * Test datetime grouped filter UI.
   */
  protected function _testFilterDatetimeUI() {
    $this->drupalLogin($this->drupalCreateUser(['administer views']));
    $this->drupalPostForm('admin/structure/views/nojs/add-handler/test_filter_date_between/default/filter', ['name[node__field_date.field_date_value]' => 'node__field_date.field_date_value'], t('Add and configure filter criteria'));

    $this->drupalPostForm(NULL, [], t('Expose filter'));
    $this->drupalPostForm(NULL, [], t('Grouped filters'));

    $edit = [];
    $edit['options[group_info][group_items][1][title]'] = 'simple-offset';
    $edit['options[group_info][group_items][1][operator]'] = '>';
    $edit['options[group_info][group_items][1][value][type]'] = 'offset';
    $edit['options[group_info][group_items][1][value][value]'] = '+1 hour';
    $edit['options[group_info][group_items][2][title]'] = 'between-offset';
    $edit['options[group_info][group_items][2][operator]'] = 'between';
    $edit['options[group_info][group_items][2][value][type]'] = 'offset';
    $edit['options[group_info][group_items][2][value][min]'] = '+1 hour';
    $edit['options[group_info][group_items][2][value][max]'] = '+2 days';
    $edit['options[group_info][group_items][3][title]'] = 'between-date';
    $edit['options[group_info][group_items][3][operator]'] = 'between';
    $edit['options[group_info][group_items][3][value][min]'] = format_date(150000, 'custom', 'Y-m-d H:i:s');
    $edit['options[group_info][group_items][3][value][max]'] = format_date(250000, 'custom', 'Y-m-d H:i:s');

    $this->drupalPostForm(NULL, $edit, t('Apply'));

    $this->drupalPostForm('admin/structure/views/view/test_filter_date_between', [], t('Save'));
    $this->assertConfigSchemaByName('views.view.test_filter_date_between');
  }

  /**
   * Tests that the exposed date filter is displayed without errors.
   */
  public function testExposedFilter() {
    $this->drupalLogin($this->drupalCreateUser(['administer views']));
    $this->drupalPostForm('admin/structure/views/nojs/handler/test_filter_date_between/default/filter/created', [], t('Expose filter'));
    $this->drupalPostForm('admin/structure/views/view/test_filter_date_between/edit', [], t('Add Page'));
    $edit = [
      'path' => 'exposed-date-filter',
    ];
    $this->drupalPostForm('admin/structure/views/nojs/display/test_filter_date_between/page_1/path', $edit, t('Apply'));

    $this->drupalPostForm(NULL, [], t('Save'));

    $this->drupalGet('exposed-date-filter');
    $this->assertField('created[date]');
    $this->assertField('created[time]');
  }

  /**
   * Make sure the exposed timestamp filters work.
   */
  protected function _testExposedFilterTimestampUI() {
    $this->drupalLogin($this->drupalCreateUser(['access content']));

    // Test the exposed "=" filter.
    $this->drupalGet('test-filter-date-exposed');

    // Verify that exposed input elements exists in the output with the proper
    // types.
    $this->assertFieldByXPath('//input[@id="edit-created-date" and @type="date"]', '', 'Found date input element.');
    $this->assertFieldByXPath('//input[@id="edit-created-time" and @type="time"]', '', 'Found time input element.');

    // Verify the node list.
    $this->assertText($this->nodes[0]->getTitle());
    $this->assertText($this->nodes[1]->getTitle());
    $this->assertText($this->nodes[2]->getTitle());
    $this->assertText($this->nodes[3]->getTitle());

    // Apply the filter.
    $timezone = $this->config('system.date')->get('timezone.default');
    $created = $this->nodes[1]->getCreatedTime();
    $date = format_date($created, 'custom', 'Y-m-d', $timezone);
    $time = format_date($created, 'custom', 'H:i:s', $timezone);

    $edit = [
      'created[date]' => $date,
      'created[time]' => $time,
    ];

    $this->drupalGet('test-filter-date-exposed', ['query' => $edit]);

    // Verify the exposed inputs have the values being filtered on.
    $this->assertFieldByXPath('//input[@id="edit-created-date" and @type="date"]', $date, 'Found populated date input element.');
    $this->assertFieldByXPath('//input[@id="edit-created-time" and @type="time"]', $time, 'Found populated time input element.');

    // Verify the node list.
    $this->assertNoText($this->nodes[0]->getTitle());
    $this->assertText($this->nodes[1]->getTitle());
    $this->assertNoText($this->nodes[2]->getTitle());
    $this->assertNoText($this->nodes[3]->getTitle());

    // Test the exposed "between" filter.
    $this->drupalGet('test-filter-date-between-exposed');

    // Verify that exposed input elements exists in the output with the proper
    // types.
    $this->assertFieldByXPath('//input[@id="edit-created-min-date" and @type="date"]', '', 'Found min date input element.');
    $this->assertFieldByXPath('//input[@id="edit-created-min-time" and @type="time"]', '', 'Found min time input element.');
    $this->assertFieldByXPath('//input[@id="edit-created-max-date" and @type="date"]', '', 'Found max date input element.');
    $this->assertFieldByXPath('//input[@id="edit-created-max-time" and @type="time"]', '', 'Found max time input element.');

    // Verify the node list.
    $this->assertText($this->nodes[0]->getTitle());
    $this->assertText($this->nodes[1]->getTitle());
    $this->assertText($this->nodes[2]->getTitle());
    $this->assertText($this->nodes[3]->getTitle());

    // Apply the filter.
    $timezone = $this->config('system.date')->get('timezone.default');
    $created = $this->nodes[1]->getCreatedTime();
    $min_date = format_date($created - 3600, 'custom', 'Y-m-d', $timezone);
    $min_time = format_date($created - 3600, 'custom', 'H:i:s', $timezone);
    $max_date = format_date($created + 3600, 'custom', 'Y-m-d', $timezone);
    $max_time = format_date($created + 3600, 'custom', 'H:i:s', $timezone);

    $edit = [
      'created[min][date]' => $min_date,
      'created[min][time]' => $min_time,
      'created[max][date]' => $max_date,
      'created[max][time]' => $max_time,
    ];

    $this->drupalGet('test-filter-date-between-exposed', ['query' => $edit]);

    // Verify the exposed inputs have the values being filtered on.
    $this->assertFieldByXPath('//input[@id="edit-created-min-date" and @type="date"]', $min_date, 'Found populated min date input element.');
    $this->assertFieldByXPath('//input[@id="edit-created-min-time" and @type="time"]', $min_time, 'Found populated min time input element.');
    $this->assertFieldByXPath('//input[@id="edit-created-max-date" and @type="date"]', $max_date, 'Found populated max date input element.');
    $this->assertFieldByXPath('//input[@id="edit-created-max-time" and @type="time"]', $max_time, 'Found populated max time input element.');

    // Verify the node list.
    $this->assertNoText($this->nodes[0]->getTitle());
    $this->assertText($this->nodes[1]->getTitle());
    $this->assertNoText($this->nodes[2]->getTitle());
    $this->assertNoText($this->nodes[3]->getTitle());
  }

  /**
   * Make sure the date time widgets work properly with exposed operator.
   */
  protected function _testExposedFilterExposedOperator() {
    $this->drupalLogin($this->drupalCreateUser(['access content']));

    // Test the exposed "=" filter.
    $this->drupalGet('test-filter-date-exposed-operators');

    // Verify that exposed input elements exists in the output with the proper
    // types.
    $this->assertFieldByXPath('//input[@id="edit-created-value-date" and @type="date"]', '', 'Found date input element.');
    $this->assertFieldByXPath('//input[@id="edit-created-value-time" and @type="time"]', '', 'Found time input element.');

    // Verify the node list.
    $this->assertText($this->nodes[0]->getTitle());
    $this->assertText($this->nodes[1]->getTitle());
    $this->assertText($this->nodes[2]->getTitle());
    $this->assertText($this->nodes[3]->getTitle());

    // Apply the filter.
    $timezone = $this->config('system.date')->get('timezone.default');
    $created = $this->nodes[1]->getCreatedTime();
    $date = format_date($created, 'custom', 'Y-m-d', $timezone);
    $time = format_date($created, 'custom', 'H:i:s', $timezone);

    // When operator is exposed, when the operator is not 'between' or
    // 'not-between', the date goes inside the value key in the array
    // corresponding to the identifier.
    $edit = [
      'created[value][date]' => $date,
      'created[value][time]' => $time,
      'created_op' => '=',
    ];

    $this->drupalGet('test-filter-date-exposed-operators', ['query' => $edit]);

    // Verify the exposed inputs have the values being filtered on.
    $this->assertFieldByXPath('//input[@id="edit-created-value-date" and @type="date"]', $date, 'Found populated date input element.');
    $this->assertFieldByXPath('//input[@id="edit-created-value-time" and @type="time"]', $time, 'Found populated time input element.');

    // Verify the node list.
    $this->assertNoText($this->nodes[0]->getTitle());
    $this->assertText($this->nodes[1]->getTitle());
    $this->assertNoText($this->nodes[2]->getTitle());
    $this->assertNoText($this->nodes[3]->getTitle());

    $min_date = format_date($created - 3600, 'custom', 'Y-m-d', $timezone);
    $min_time = format_date($created - 3600, 'custom', 'H:i:s', $timezone);
    $max_date = format_date($created + 3600, 'custom', 'Y-m-d', $timezone);
    $max_time = format_date($created + 3600, 'custom', 'H:i:s', $timezone);

    $edit = [
      'created_op' => 'between',
      'created[min][date]' => $min_date,
      'created[min][time]' => $min_time,
      'created[max][date]' => $max_date,
      'created[max][time]' => $max_time,
    ];

    $this->drupalGet('test-filter-date-exposed-operators', ['query' => $edit]);

    // Verify the exposed inputs have the values being filtered on.
    $this->assertFieldByXPath('//input[@id="edit-created-min-date" and @type="date"]', $min_date, 'Found populated min date input element.');
    $this->assertFieldByXPath('//input[@id="edit-created-min-time" and @type="time"]', $min_time, 'Found populated min time input element.');
    $this->assertFieldByXPath('//input[@id="edit-created-max-date" and @type="date"]', $max_date, 'Found populated max date input element.');
    $this->assertFieldByXPath('//input[@id="edit-created-max-time" and @type="time"]', $max_time, 'Found populated max time input element.');

    // Verify the node list.
    $this->assertNoText($this->nodes[0]->getTitle());
    $this->assertText($this->nodes[1]->getTitle());
    $this->assertNoText($this->nodes[2]->getTitle());
    $this->assertNoText($this->nodes[3]->getTitle());
  }

}
