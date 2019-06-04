<?php

namespace Drupal\content_moderation;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\workflows\Entity\Workflow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class for dynamic permissions based on transitions.
 *
 * @internal
 */
class Permissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The bundle information service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * The entity type definitions.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface[]
   */
  protected $entityDefinitions;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * Permissions constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info
   *   The entity bundle information service.
   * @param \Drupal\Core\Entity\EntityTypeInterface[] $entity_definitions
   *   An array of entity type definitions.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_information
   *   The moderation information service.
   */
  public function __construct(EntityTypeBundleInfoInterface $bundle_info, array $entity_definitions, ModerationInformationInterface $moderation_information) {
    $this->bundleInfo = $bundle_info;
    $this->entityDefinitions = $entity_definitions;
    $this->moderationInfo = $moderation_information;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager')->getDefinitions(),
      $container->get('content_moderation.moderation_information')
    );
  }

  /**
   * Returns an array of transition permissions.
   *
   * @return array
   *   The transition permissions.
   */
  public function transitionPermissions() {
    $permissions = [];
    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    foreach (Workflow::loadMultipleByType('content_moderation') as $id => $workflow) {
      foreach ($workflow->getTypePlugin()->getTransitions() as $transition) {
        $permissions['use ' . $workflow->id() . ' transition ' . $transition->id()] = [
          'title' => $this->t('%workflow workflow: Use %transition transition.', [
            '%workflow' => $workflow->label(),
            '%transition' => $transition->label(),
          ]),
        ];
      }
    }

    return $permissions;
  }

  /**
   * Returns an array of per-entity and bundle unpublished permissions.
   *
   * @return array
   *   The per-entity-bundle permissions for viewing unpublished content.
   */
  public function perBundleUnpublishedPermissions() {
    $permissions = [];
    foreach ($this->entityDefinitions as $entity_type) {
      if ($this->moderationInfo->canModerateEntitiesOfEntityType($entity_type) && $entity_type->getPermissionGranularity() === 'bundle') {
        foreach ($this->bundleInfo->getBundleInfo($entity_type->id()) as $bundle_id => $bundle_information) {
          if ($this->moderationInfo->shouldModerateEntitiesOfBundle($entity_type, $bundle_id)) {
            $permissions["view any unpublished {$entity_type->id()}:{$bundle_id} content"] = [
              'title' => $this->t('%entity_type: View any unpublished %bundle content', [
                '%entity_type' => $entity_type->getLabel(),
                '%bundle' => $bundle_information['label'],
              ]),
            ];
          }
        }
      }
    }
    return $permissions;
  }

}
