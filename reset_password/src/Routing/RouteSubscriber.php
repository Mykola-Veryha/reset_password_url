<?php

namespace Drupal\reset_password\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    if ($route = $collection->get('user.reset.login')) {
      $route->setDefault(
        '_controller',
        'Drupal\reset_password\Controller\OverrideUserController::resetPassLogin'
      );
    }
  }

}
