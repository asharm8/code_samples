<?php

namespace Drupal\agnesian_eventbrite\Plugin\QueueWorker;

/**
 * Create node object from the imported XML content
 *
 * @QueueWorker(
 *   id = "import_eventbrite_event",
 *   title = @Translation("Import Eventbrite Events"),
 * )
 */
class ImportEventbriteEventsQueue extends ImportEventbriteEventsQueueBase  {}