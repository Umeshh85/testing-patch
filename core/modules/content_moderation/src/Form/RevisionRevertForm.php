<?php

namespace Drupal\content_moderation\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\content_moderation\StateTransitionValidationInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Form\NodeRevisionRevertForm;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Content moderation-specific revision revert form.
 */
class RevisionRevertForm extends NodeRevisionRevertForm {

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * The transition validation service.
   *
   * @var \Drupal\content_moderation\StateTransitionValidationInterface
   */
  protected $validation;

  /**
   * Creates a new RevisionRevertForm instance.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $node_storage
   *   The node storage.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_information
   *   The moderation information service.
   * @param \Drupal\content_moderation\StateTransitionValidationInterface $validation
   *   The state transition validation service.
   */
  public function __construct(EntityStorageInterface $node_storage, DateFormatterInterface $date_formatter, TimeInterface $time, ModerationInformationInterface $moderation_information, StateTransitionValidationInterface $validation) {
    parent::__construct($node_storage, $date_formatter, $time);
    $this->moderationInformation = $moderation_information;
    $this->validation = $validation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')->getStorage('node'),
      $container->get('date.formatter'),
      $container->get('datetime.time'),
      $container->get('content_moderation.moderation_information'),
      $container->get('content_moderation.state_transition_validation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node_revision = NULL) {
    $form = parent::buildForm($form, $form_state, $node_revision);

    // Add the moderation state select list for moderated entities.
    if ($this->moderationInformation->isModeratedEntity($this->revision)) {
      $current_state = $this->revision->moderation_state->value;
      $workflow = $this->moderationInformation->getWorkflowForEntity($this->revision);

      // Valid transitions are determined not from the this revision, but
      // rather, from the default state of a new revision. If a user has no
      // permissions for any of these transitions, the form is hidden.
      $new_entity = $this->revision->createDuplicate();

      /** @var \Drupal\workflows\Transition[] $transitions */
      $transitions = $this->validation->getValidTransitions($new_entity, $this->currentUser());

      $target_states = [];

      foreach ($transitions as $transition) {
        $target_states[$transition->to()->id()] = $transition->to()->label();
      }

      if (!count($target_states)) {
        // Set to the initial state for this workflow since the user has no
        // access to transitions from this state.
        $form['new_state'] = [
          '#type' => 'value',
          '#value' => $workflow->getTypePlugin()->getInitialState($new_entity)->id(),
        ];
        return $form;
      }

      if ($current_state) {
        $form['current'] = [
          '#type' => 'item',
          '#title' => $this->t('Revision moderation state'),
          '#markup' => $workflow->getTypePlugin()->getState($current_state)->label(),
        ];
      }

      $form['new_state'] = [
        '#type' => 'select',
        '#title' => $this->t('Revert and set to'),
        '#options' => $target_states,
        '#default_value' => $current_state,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareRevertedRevision(NodeInterface $revision, FormStateInterface $form_state) {
    if (!$this->moderationInformation->isModeratedEntity($revision)) {
      // Default behavior if this isn't a moderated entity.
      return parent::prepareRevertedRevision($revision, $form_state);
    }

    $revision->moderation_state = $form_state->getValue('new_state');

    return $revision;
  }

}
