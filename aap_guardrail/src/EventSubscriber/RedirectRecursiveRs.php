<?php

namespace Drupal\aap_guardrail\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\Url;
use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Routing\TrustedRedirectResponse;



class RedirectRecursiveRs implements EventSubscriberInterface {
  public function checkRs(GetResponseEvent $event) {
    $uri = \Drupal::request()->getRequestUri();
      if(substr_count($uri, "/r/r/") > 0) {
        $gen_array = explode('/', $uri);
        $ct = 0;
        foreach($gen_array as $key => $val) {
          if($ct > 0 && $val == 'r') {
            unset($gen_array[$key]);
          }
          if ($val == 'r') {
            $ct++;
          }
        }
        $new_uri = implode('/', $gen_array);
        $this->eventRedirectToPath($event, $new_uri);
    }
  }
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = array('checkRs');
    return $events;
  }

  /**
   * Sets a redirect response on a GetResponseEvent.
   *
   * Redirection may not work if the originally-requested
   * path does not exist (404). In this case you may be redirected
   * to the front page.
   *
   * @param GetResponseEvent $event
   *   Event to update.
   * @param string $path
   *   Drupal path.
   */
  private function eventRedirectToPath(GetResponseEvent &$event, $path) {
    \Drupal::service('page_cache_kill_switch')->trigger();
    $response = new TrustedRedirectResponse($path, 301);
    $response->send();
  }
}