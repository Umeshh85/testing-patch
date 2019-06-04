<?php

namespace Drupal\datetime\Plugin\views\filter;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\views\FieldAPIHandlerTrait;
use Drupal\views\Plugin\views\filter\Date as NumericDate;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Date/time views filter.
 *
 * Even thought dates are stored as strings, the numeric filter is extended
 * because it provides more sensible operators.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("datetime")
 */
class Date extends NumericDate implements ContainerFactoryPluginInterface {

  use FieldAPIHandlerTrait;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Date format for SQL conversion.
   *
   * @var string
   *
   * @see \Drupal\views\Plugin\views\query\Sql::getDateFormat()
   */
  protected $dateFormat = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;

  /**
   * Determines if the timezone offset is calculated.
   *
   * @var bool
   */
  protected $calculateOffset = TRUE;

  /**
   * The request stack used to determine current time.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new Date handler.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack used to determine the current time.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, DateFormatterInterface $date_formatter, RequestStack $request_stack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->dateFormatter = $date_formatter;
    $this->requestStack = $request_stack;

    $definition = $this->getFieldStorageDefinition();
    if ($definition->getSetting('datetime_type') === DateTimeItem::DATETIME_TYPE_DATE) {
      // Date format depends on field storage format.
      $this->dateFormat = DateTimeItemInterface::DATE_STORAGE_FORMAT;
      // Timezone offset calculation is not applicable to dates that are stored
      // as date-only.
      $this->calculateOffset = FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('date.formatter'),
      $container->get('request_stack')
    );
  }

  /**
   * Override parent method, which deals with dates as integers.
   */
  protected function opBetween($field) {
    $timezone = $this->getTimezone();
    $origin_offset = $this->getOffset($this->value['min'], $timezone);

    // Although both 'min' and 'max' values are required, default empty 'min'
    // value as UNIX timestamp 0.
    $min = (!empty($this->value['min'])) ? $this->value['min'] : '@0';

    // Convert to ISO format and format for query. UTC timezone is used since
    // dates are stored in UTC.
    $a = new DateTimePlus($min, new \DateTimeZone($timezone));
    $a = $this->query->getDateFormat($this->query->getDateField("'" . $this->dateFormatter->format($a->getTimestamp() + $origin_offset, 'custom', DateTimeItemInterface::DATETIME_STORAGE_FORMAT, DateTimeItemInterface::STORAGE_TIMEZONE) . "'", TRUE, $this->calculateOffset), $this->dateFormat, TRUE);
    $b = new DateTimePlus($this->value['max'], new \DateTimeZone($timezone));
    $b = $this->query->getDateFormat($this->query->getDateField("'" . $this->dateFormatter->format($b->getTimestamp() + $origin_offset, 'custom', DateTimeItemInterface::DATETIME_STORAGE_FORMAT, DateTimeItemInterface::STORAGE_TIMEZONE) . "'", TRUE, $this->calculateOffset), $this->dateFormat, TRUE);

    // This is safe because we are manually scrubbing the values.
    $operator = strtoupper($this->operator);
    $field = $this->query->getDateFormat($this->query->getDateField($field, TRUE, $this->calculateOffset), $this->dateFormat, TRUE);
    $this->query->addWhereExpression($this->options['group'], "$field $operator $a AND $b");
  }

  /**
   * Override parent method, which deals with dates as integers.
   */
  protected function opSimple($field) {
    $timezone = $this->getTimezone();
    $origin_offset = $this->getOffset($this->value['value'], $timezone);

    // Convert to ISO. UTC timezone is used since dates are stored in UTC.
    $value = new DateTimePlus($this->value['value'], new \DateTimeZone($timezone));
    $value = $this->query->getDateFormat($this->query->getDateField("'" . $this->dateFormatter->format($value->getTimestamp() + $origin_offset, 'custom', DateTimeItemInterface::DATETIME_STORAGE_FORMAT, DateTimeItemInterface::STORAGE_TIMEZONE) . "'", TRUE, $this->calculateOffset), $this->dateFormat, TRUE);

    // This is safe because we are manually scrubbing the value.
    $field = $this->query->getDateFormat($this->query->getDateField($field, TRUE, $this->calculateOffset), $this->dateFormat, TRUE);
    $this->query->addWhereExpression($this->options['group'], "$field $this->operator $value");
  }

  /**
   * Get the proper time zone to use in computations.
   *
   * Date-only fields do not have a time zone associated with them, so the
   * filter input needs to use UTC for reference. Otherwise, use the time zone
   * for the current user.
   *
   * @return string
   *   The time zone name.
   */
  protected function getTimezone() {
    return $this->dateFormat === DateTimeItemInterface::DATE_STORAGE_FORMAT
      ? DateTimeItemInterface::STORAGE_TIMEZONE
      : drupal_get_user_timezone();
  }

  /**
   * Get the proper offset from UTC to use in computations.
   *
   * @param string $time
   *   A date/time string compatible with \DateTime. It is used as the
   *   reference for computing the offset, which can vary based on the time
   *   zone rules.
   * @param string $timezone
   *   The time zone that $time is in.
   *
   * @return int
   *   The computed offset in seconds.
   */
  protected function getOffset($time, $timezone) {
    // Date-only fields do not have a time zone or offset from UTC associated
    // with them. For relative (i.e. 'offset') comparisons, we need to compute
    // the user's offset from UTC for use in the query.
    $origin_offset = 0;
    if ($this->dateFormat === DateTimeItemInterface::DATE_STORAGE_FORMAT && $this->value['type'] === 'offset') {
      $origin_offset = $origin_offset + timezone_offset_get(new \DateTimeZone(drupal_get_user_timezone()), new \DateTime($time, new \DateTimeZone($timezone)));
    }

    return $origin_offset;
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposedForm(&$form, FormStateInterface $form_state) {
    parent::buildExposedForm($form, $form_state);

    // Hide the date_time_element for date only fields.
    if ($this->fieldStorageDefinition->get('settings')['datetime_type'] === DateTimeItem::DATETIME_TYPE_DATE) {
      $field_identifier = $this->options['expose']['identifier'];

      // If the operator is between/not between or if the operator is exposed,
      // modify the form element to remove the time part.
      if (in_array($this->operator, ['between', 'not between']) || $this->options['expose']['use_operator']) {
        $form[$field_identifier . '_wrapper'][$field_identifier]['min']['#date_time_element'] = 'none';
        $form[$field_identifier . '_wrapper'][$field_identifier]['max']['#date_time_element'] = 'none';
      }

      // If the operator is something other than between/not between,
      // Modify the date widget accordingly.
      if ((!in_array($this->operator, ['between', 'not between']))) {
        // If the operator is exposed, the date field is inside the value
        // array. See parent method's valueForm for details.
        if ($this->options['expose']['use_operator']) {
          $form[$field_identifier . '_wrapper'][$field_identifier]['value']['#date_time_element'] = 'none';
        }
        // If the operator is exposed, the date field is inside the identifier
        // array directly. See parent method's valueForm for details.
        elseif (empty($this->options['expose']['use_operator']) || empty($this->options['expose']['operator_id'])) {
          $form[$field_identifier]['#date_time_element'] = 'none';
        }
      }
    }
  }

}
