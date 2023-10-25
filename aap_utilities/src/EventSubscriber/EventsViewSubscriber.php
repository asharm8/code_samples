<?php

namespace Drupal\aap_utilities\EventSubscriber;


use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\Datetime\DrupalDateTime as DateTime;

class EventsViewSubscriber implements EventSubscriberInterface {
  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = array('checkEventsView');
    return $events;
  }


  public function checkEventsView(GetResponseEvent $event) {
    $request = $event->getRequest();
    $path = stripslashes($request->getPathInfo());

    if ($path == "/events/" || $path == "/events") {

      // If no dates are provided, provide some defaults
      $startDateCheck = $request->query->get('start_date');
      if (!isset($startDateCheck)) {
        // Get today's date
        $todaysDate = new DateTime('now');

        // Add two full months
        $plusTwoMonths = new DateTime($todaysDate->format('Y-m-d') . "+ 2 months");
        // Get the last day of thefuture month
        $lastDayOfPlusTwoMonths = new DateTime('last day of ' . $plusTwoMonths->format('Y-m-d'));
        // Add a day to get the UI tweak working
        $firstDayofTheNextMonth = new DateTime($lastDayOfPlusTwoMonths->format('Y-m-d') . '+ 1 day');
        // Set the dates!
        $request->query->set('start_date', $todaysDate->format('Y-m-d'));
        $request->query->set('start_date_2', $firstDayofTheNextMonth->format('Y-m-d'));
      }

      // Handle pre-configured queries while allowing further usage of the filters.
      // Speed Perks
      if ($request->query->get('SpeedPerks') > 0) {
        $request->query->set('speed_perks', explode(',', $request->query->get('SpeedPerks')));
      }

      // Event Type
      if ($request->query->get('EventType') > 0) {
        $request->query->set('event_type', explode(',', $request->query->get('EventType')));
      }
    }
  }
}