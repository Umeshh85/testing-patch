<?php

namespace Drupal\content_moderation\EventSubscriber;

use Drupal\content_moderation\Form\RevisionRevertForm;
use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Route subscriber for content moderation.
 */
class RouteSubscriber implements EventSubscriberInterface {

  /**
   * Change revision revert form to use content moderation's version.
   *
   * @param \Drupal\Core\Routing\RouteBuildEvent $event
   *   The routing alter event.
   */
  public function onRoutingRouteAlterRevisionForm(RouteBuildEvent $event) {
    // @todo Make this applicable to all revisionable entities.
    // @see https://www.drupal.org/project/drupal/issues/2350939
    foreach ($event->getRouteCollection() as $name => $route) {
      if ($name === 'node.revision_revert_confirm') {
        $route->setDefault('_form', RevisionRevertForm::class);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[RoutingEvents::ALTER][] = ['onRoutingRouteAlterRevisionForm', -150];
    return $events;
  }

}
