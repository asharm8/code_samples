<?php

namespace Drupal\aap_utilities\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;
use Drupal\Core\Routing\RoutingEvents;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Use our own controller for altering node view routes.
    if ($route = $collection->get('entity.node.canonical')) {
      $route->setDefaults([
        '_controller' => '\Drupal\aap_utilities\Controller\AapUtilitiesNodeViewController::view',
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -9999];
    return $events;
  }

}