<?php

/**
 * @file
 * Contains Drupal\agnesian_eventbrite\Plugin\QueueWorker\ImportEventbriteEventsQueueBase
 */

namespace Drupal\agnesian_eventbrite\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\agnesian_eventbrite\Controller\AgnesianEventBriteController;

/**
 * Provides base functionality for the Import Content From XML Queue Workers.
 */
abstract class ImportEventbriteEventsQueueBase extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($item) {

    //Dataset will be an object if being processed through BatchAPI
    if (is_object($item)) {
      $item = (array) $item;
      $data = $item['data'];
    } else {
      $data = $item;
    }
    $eventbrite = new AgnesianEventBriteController();
    $eventbrite->processEventBriteEvent($data);
  }

}