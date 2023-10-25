<?php

namespace Drupal\aap_utilities\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Provides a subscriber to set the properly exception.
 */
class Redirect403To404EventSubscriber implements EventSubscriberInterface {

  /**
   * The settings config.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The admin context.
   *
   * @var \Drupal\Core\Routing\AdminContext
   */
  protected $adminContext;

  /**
   * m4032404EventSubscriber constructor.
   *
   * @param ConfigFactoryInterface $config_factory
   *   The settings config.
   * @param \Drupal\Core\Routing\AdminContext $admin_context
   *   The admin context.
   */
  public function __construct() {
  }

  /**
   * Set the properly exception for event.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event
   *   The response for exception event.
   */
  public function onAccessDeniedException(GetResponseForExceptionEvent $event) {
    if ($event->getException() instanceof AccessDeniedHttpException) {
        $event->setException(new NotFoundHttpException());
    }
  }

  /**
   * Registers the methods in this class that should be listeners.
   *
   * @return array
   *   An array of event listener definitions.
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::EXCEPTION][] = array('onAccessDeniedException', 50);
    return $events;
  }
}
