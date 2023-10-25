<?php

namespace Drupal\aap_utilities\EventSubscriber;

use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class ResponseSubscriber.
 *
 * Subscribe drupal events.
 *
 * @package Drupal\my_module
 */
class RedirectArchivedSubscriber implements EventSubscriberInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a new ResponseSubscriber instance.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.   */
  public function __construct(AccountInterface $current_user) {
    $this->currentUser = $current_user;
  }


  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE] = 'alterResponse';
    return $events;
  }
  /**
   * Redirect if 403 and node an event.
   *
   * @param FilterResponseEvent $event
   *   The route building event.
   */
  public function alterResponse(FilterResponseEvent $event) {

    if ($event->getRequest()->attributes->get('node') != NULL && $event->getResponse()->getStatusCode() == 404) {
        /** @var \Symfony\Component\HttpFoundation\Request $request */
        $request = $event->getRequest();
        $node = $request->attributes->get('node');
        $nodetype = $node->getType();
        if ($node->getType() == 'article' || $node->getType() == 'how_to') {
          $state_id = $node->moderation_state->get(0)->getValue()['value'];
          if ($state_id == 'archived') {
            $path =  \Drupal::service('path.alias_manager')->getAliasByPath('/r/');
            $event->setResponse(new RedirectResponse($path, 301));
          }
        }
    }
  }
}
