<?php
/**
 * @file
 * Provides Eventbrite API Functionality
 */
namespace Drupal\agnesian_eventbrite\Controller;


use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Controller\ControllerBase;
use \Drupal\Component\Utility\SafeMarkup;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Driver\Exception\Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ClientException;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Component\Serialization\Json;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Queue\QueueWorkerManager;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\SuspendQueueException;


/**
 * Class AgnesianEventBriteController
 * @package Drupal\agnesian_eventbrite\Controller
 */
class AgnesianEventBriteController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $queue_factory = $container->get('queue');
    $queue_manager = $container->get('plugin.manager.queue_worker');

    return new static($queue_factory, $queue_manager);
  }


  /**
   * @return array|bool
   */
  public function eventBriteContent() {

    $url = 'users/me/owned_events/';

    // Get the eventbrite events.
    //$data_set = $this->fetchData($url, ['status' => 'live'], '1');

    //dsm($data_set);

    //$result = $this->processEventBriteEvent($data_set);

    /** @var QueueFactory $queue_factory */
    $queue_factory = \Drupal::service('queue');
    $queue = $queue_factory->get('import_eventbrite_event');
    $result = $queue->numberOfItems();


    if ($result) {
      $markup = $result;
    }
    else {
      $markup = 'An error occurred.';
    }

    $build = [
      '#title' => 'Eventbrite Import',
      '#markup' => $markup,
    ];
    return $build;

  }

  /**
   * Function to Run Eventbrite Import through Cron
   */
  public function eventBritePopulateQueue() {

    \Drupal::logger('agnesian_eventbrite')
      ->notice('Eventbrite Event Import has started.');
    
    $url = 'users/me/owned_events/';
    // Get the eventbrite events.
    $data_set = $this->fetchData($url, ['status' => 'live'], '1');
    $eventbrite_config = $this->config('agnesian_eventbrite.settings');
    $force_reload = $eventbrite_config->get('force_reload');

    //Let's add check to see if Queue is full.
    //There is a 1000 request limit to API. We need to add logic to limit
    $queue_count = 0;
    $delete_count = 0;
    $filter_array = [];
    foreach ($data_set as $page => $eventbrite_list) {
      foreach ($eventbrite_list['events'] as $eventbrite_event) {

        //Should only return one result if exists.
        $row = \Drupal::entityQuery('eventbrite_event')
          ->condition('field_eventbrite_id', $eventbrite_event['id'])
          ->execute();
        $row_value = (string) reset($row);
        $filter_array[] = $eventbrite_event['id'];
        $event_object = $this->loadEventBriteEntity($row_value);
        $changed_date = new \Drupal\Core\Datetime\DrupalDateTime($eventbrite_event['changed'], drupal_get_user_timezone());
        $remote_event_changed_date = $changed_date->format(DATETIME_DATETIME_STORAGE_FORMAT);

        if ($force_reload == 1) {
          //Manually change the date so strcmp never matches causing event to be imported.
          $remote_event_changed_date = 'FORCE-UPDATE-EVENT';
        }

        //AHW-318: If $eventbrite_event['shareable'] is set to true, the event is publicly listed.
        if ((empty($event_object) || strcmp($event_object->get('field_eventbrite_changed_date')->value, $remote_event_changed_date) !== 0) && ($eventbrite_event['shareable'] == 1)) {
          /** @var QueueFactory $queue_factory */
          $queue_factory = \Drupal::service('queue');
          $queue = $queue_factory->get('import_eventbrite_event');
          $queue->createItem($eventbrite_event);
          $queue_count++;
        }

        if ($eventbrite_event['shareable'] != 1 && !empty($event_object)) {
          //Deleting event if it is not shareable.
          $event_object->delete();
          $delete_count++;
        }
      }
    }

    \Drupal::logger('agnesian_eventbrite')
      ->info('Eventbrite Import has finished. @count events are queued for creation.', ['@count' => $queue_count]);

    \Drupal::logger('agnesian_eventbrite')
      ->info('Removing Unpublished events from database');
    $rows = \Drupal::entityQuery('eventbrite_event')
      ->execute();

    foreach ($rows as $row) {
      $event_object = $this->loadEventBriteEntity($row);
      if (!in_array($event_object->get('field_eventbrite_id')->value, $filter_array)) {
        $delete_count++;
        $event_object->delete();
      }
    }

    \Drupal::logger('agnesian_eventbrite')
      ->info('Eventbrite Deletion has finished. @count unpublished events were removed.', ['@count' => $delete_count]);

  }

  /**
   * Sends a http request to the EventBrite API Server.
   *
   * @param string $url
   *   URL for http request.
   *
   * @param $params
   * @param $page
   * @return bool|mixed The encoded response containing the eventbrite data or FALSE.
   * The encoded response containing the eventbrite data or FALSE.
   */
  protected function fetchData($url, $params = [], $page = NULL) {
    $eventbrite_config = $this->config('agnesian_eventbrite.settings');
    $base_url = $eventbrite_config->get('base_api_url');
    $token = $eventbrite_config->get('auth_token');

    $client = \Drupal::httpClient();
    $currentPage = !empty($page) ? $page : '1';
    $endofResults = FALSE;
    $data_array = [];

    while ($endofResults != TRUE) {

      try {
        // Build url for http request.
        $uri = $base_url . $url;
        $options = [
          'query' => [
            'token' => $token,
            'page' => $currentPage,
          ],
        ];
        if (!empty($params)) {
          $options['query'] = $options['query'] + $params;
        }
        $rendered_url = Url::fromUri($uri, $options)->toString();

        // Create a request GET object.
        $response = $client->request('GET', $rendered_url, ['headers' => ['Accept' => 'application/json']]);
        $data = Json::decode($response->getBody());

        if (empty($data)) {
          //Empty Resultset?
          break;
        }

        //Only Events endpoint should currently have multiple pages so escaping loop is necessary.
        if (isset($data['pagination']['page_count']) && $data['pagination']['page_count'] > 1) {
          $data_array[] = $data;
        }
        else {
          $endofResults = TRUE;
          $data_array = $data;
        }
      }
      catch (ClientException $e) {
        //Logic needs to be created for End of Page Requests
        $response = Json::decode($e->getResponse()->getBody()->getContents());
        //This should set $endofResults to 'BAD_PAGE'
        if ($response['error'] == 'BAD_PAGE') {
          $endofResults = TRUE;
        }
        elseif ($response['error'] == 'HIT_RATE_LIMIT') {
          //If we encounter this error, we have reached the hourly allowed limit for the API (1000 calls/hr | 24,000 calls/day).
          // We will need to "save" our current point and resume later.
          \Drupal::state()
            ->set('agnesian_eventbrite.hit_rate_limit', REQUEST_TIME + 3600);
          throw new SuspendQueueException($response['error']);
        }
        break;
      }
      catch (RequestException $e) {
        //Logic needs to be created for End of Page Requests
        break;
      }

      $currentPage++;
    }

    return $data_array;

  }

  /**
   * @param array $eventbrite_event
   * @return string
   * @internal param $data_set
   */
  public function processEventBriteEvent($eventbrite_event = []) {

    //Should only return one result if exists.
    $row = \Drupal::entityQuery('eventbrite_event')
      ->condition('field_eventbrite_id', $eventbrite_event['id'])
      ->execute();
    $row_value = (string) reset($row);
    $event_object = $this->loadEventBriteEntity($row_value);

    //Create event if not in Drupal otherwise Update.
    try {
      if (empty($event_object)) {
        $event = $this->createEventBriteEntity($eventbrite_event);
      }
      else {
        $event = $this->populateFields($eventbrite_event, $event_object);
        $event->save();
      }
    }
    catch (EntityStorageException $e) {
      \Drupal::logger('agnesian_eventbrite')->error($e->getMessage());
    }

    return $event;
  }

  /**
   * @param $event_id
   * @return \Drupal\Core\Entity\EntityInterface|null
   */
  protected function loadEventBriteEntity($event_id) {
    $eventbrite_event = \Drupal::entityTypeManager()
      ->getStorage('eventbrite_event')
      ->load($event_id);
    return $eventbrite_event;
  }

  /**
   * @param $entity_object
   * @return \Drupal\Core\Entity\EntityInterface
   */
  protected function createEventBriteEntity($entity_object) {
    //Code to add entity.
    $eventbrite_event = \Drupal::entityTypeManager()
      ->getStorage('eventbrite_event')
      ->create(['type' => 'event', 'title' => $entity_object['name']['text']]);

    //Populate Fields
    $eventbrite_event->set('field_eventbrite_id', $entity_object['id']);
    $eventbrite_event = $this->populateFields($entity_object, $eventbrite_event);


    $eventbrite_event->save();

    return $eventbrite_event;
  }

  /**
   * @param $remote_event - Event object loaded from Eventbrite API .
   * @param $local_event - Event stored as entity on Drupal.
   * @return mixed
   */
  protected function populateFields($remote_event = [], $local_event = []) {

    //Load Dates
    $changed_date = new \Drupal\Core\Datetime\DrupalDateTime($remote_event['changed'], drupal_get_user_timezone());
    $start_date = new \Drupal\Core\Datetime\DrupalDateTime($remote_event['start']['utc'], drupal_get_user_timezone());
    $end_date = new \Drupal\Core\Datetime\DrupalDateTime($remote_event['end']['utc'], drupal_get_user_timezone());
    $local_event->set('field_start_date', [$start_date->format(DATETIME_DATETIME_STORAGE_FORMAT)]);
    $local_event->set('field_end_date', [$end_date->format(DATETIME_DATETIME_STORAGE_FORMAT)]);
    $local_event->set('field_eventbrite_changed_date', [$changed_date->format(DATETIME_DATETIME_STORAGE_FORMAT)]);

    //These Fields don't require formatting:
    $local_event->set('title', $remote_event['name']['text']);
    $local_event->field_description->setValue([
      'value' => $remote_event['description']['html'],
      'format' => 'rich_text',
    ]);
    $local_event->set('field_capacity', (int) $remote_event['capacity']);
    $local_event->set('field_url', [
      'uri' => $remote_event['url'],
      'title' => 'Register',
    ]);

    //Categories are called Organizers on Eventbrite API.
    $org_url = 'organizers/' . $remote_event['organizer_id'] . '/';
    $organizer = $this->fetchData($org_url);
    if ($terms = taxonomy_term_load_multiple_by_name($organizer['name'], 'eventbrite_category')) {
      $term = reset($terms);
    }
    else {
      $term = Term::create([
        'name' => $organizer['name'],
        'vid' => 'eventbrite_category',
      ]);
      $term->save();
    }
    $local_event->set('field_event_category', ['target_id' => $term->id()]);

    //Addresses are stored under Venue Objects
    $venue_url = 'venues/' . $remote_event['venue_id'];
    $venue_object = $this->fetchData($venue_url, NULL, '1');
    $local_event->field_address->setValue([
      'country_code' => $venue_object['address']['country'],
      'organization' => $venue_object['name'],
      'address_line1' => $venue_object['address']['address_1'],
      'address_line2' => $venue_object['address']['address_2'],
      'locality' => $venue_object['address']['city'],
      'administrative_area' => $venue_object['address']['region'],
      'postal_code' => $venue_object['address']['postal_code'],
    ]);

    //Load Event Prices.
    $ticket_url = 'events/' . $remote_event['id'] . '/ticket_classes/';
    $ticket_object = reset($this->fetchData($ticket_url, NULL, '1')['ticket_classes']);

    if ($ticket_object['free'] == TRUE) {
      $local_event->field_event_type->setValue(['value' => 'Free']);
    }
    elseif ($ticket_object['donation'] == TRUE) {
      $local_event->field_event_type->setValue(['value' => 'Donation']);
    }
    else {
      $local_event->field_event_type->setValue(['value' => 'Paid']);
    }

    $price = (!empty($ticket_object['cost']['major_value']) ? (float) $ticket_object['cost']['major_value'] : 0.00);
    $local_event->set('field_price', $price);

    return $local_event;
  }

  /**
   * Process all queued items with batch
   */
  public function processQueue() {

    \Drupal::logger('agnesian_eventbrite')
      ->notice('Eventbrite Queue Processing has started.');

    /** @var QueueFactory $queue_factory */
    $queue_factory = \Drupal::service('queue');
    $queue_manager = \Drupal::service('plugin.manager.queue_worker');
    $queue = $queue_factory->get('import_eventbrite_event');
    // Get the queue worker
    $queue_worker = $queue_manager->createInstance('import_eventbrite_event');
    $end = time() + 30;
    $lease_time = 30;
    $eventcount = 0;
    while (time() < $end && ($item = $queue->claimItem($lease_time))) {
      try {
        $queue_worker->processItem($item->data);
        $queue->deleteItem($item);
        $eventcount++;
      }
      catch (RequeueException $e) {
        // The worker requested the task be immediately requeued.
        $queue->releaseItem($item);
      }
      catch (SuspendQueueException $e) {
        // If the worker indicates there is a problem with the whole queue,
        // release the item and skip to the next queue.
        $queue->releaseItem($item);
        \Drupal::logger('agnesian_eventbrite')
          ->notice('Eventbrite Queue has been suspended. Hit Rate Limit has been reached.');
        break;
      }
      catch (\Exception $e) {
        // In case of any other kind of exception, log it and leave the item
        // in the queue to be processed again later.
        watchdog_exception('cron', $e);
      }
    }

    \Drupal::logger('agnesian_eventbrite')
      ->info('Eventbrite Queue Processing has finished. @count events have been created.', ['@count' => $eventcount]);
  }


  /**
   * Process queue items with batch
   */
  public function processQueueItemsWithBatch() {

    \Drupal::logger('agnesian_eventbrite')
      ->notice('Eventbrite Batch Processing has started.');

    $eventbrite_config = \Drupal::configFactory()
      ->getEditable('agnesian_eventbrite.settings');
    $batch_size = $eventbrite_config->get('batch');

    // Create batch which collects all the specified queue items and process them one after another
    $batch = array(
      'title' => $this->t("Process all Event Import queues with batch"),
      'operations' => array(),
      'finished' => 'Drupal\agnesian_eventbrite\Controller\AgnesianEventBriteController::batchFinished',
    );

    // Get the queue implementation for import_eventbrite_event queue
    $queue_factory = \Drupal::service('queue');
    $queue = $queue_factory->get('import_eventbrite_event');

    // Create batch operations
    $batch['operations'][] = array(
      'Drupal\agnesian_eventbrite\Controller\AgnesianEventBriteController::batchProcess',
      array(),
    );

    // Adds the batch sets
    batch_set($batch);
  }

  /**
   * Common batch processing callback for all operations.
   */
  public static function batchProcess(&$context) {

    $eventbrite_config = \Drupal::configFactory()
      ->getEditable('agnesian_eventbrite.settings');
    $batch_size = !empty($eventbrite_config->get('batch')) ? $eventbrite_config->get('batch') : 200;

    /** @var QueueFactory $queue_factory */
    $queue_factory = \Drupal::service('queue');
    $queue_manager = \Drupal::service('plugin.manager.queue_worker');

    // Get the queue implementation for import_content_from_xml queue
    $queue = $queue_factory->get('import_eventbrite_event');
    // Get the queue worker
    $queue_worker = $queue_manager->createInstance('import_eventbrite_event');

    // Get the number of items
    $number_of_queue = ($queue->numberOfItems() < $batch_size) ? $queue->numberOfItems() : $batch_size;

    // Repeat $number_of_queue times
    for ($i = 0; $i < $number_of_queue; $i++) {
      // Get a queued item
      if ($item = $queue->claimItem()) {
        try {
          // Process it
          $queue_worker->processItem($item);
          // If everything was correct, delete the processed item from the queue
          $queue->deleteItem($item);
        }
        catch (SuspendQueueException $e) {
          // If there was an Exception thrown because of an error
          // Releases the item that the worker could not process.
          // Another worker can come and process it
          $queue->releaseItem($item);
          \Drupal::logger('agnesian_eventbrite')
            ->notice('Eventbrite Queue has been suspended. Hit Rate Limit has been reached.');
          break;
        }
      }
    }
    $context['results'] = $i;
  }

  /**
   * Batch finished callback.
   */
  public static function batchFinished($success, $results, $operations) {
    if ($success) {
      \Drupal::logger('agnesian_eventbrite')
        ->info('Eventbrite Batch Processing has finished. @count events have been created.', ['@count' => $results]);
    }
    else {
      $error_operation = reset($operations);
      \Drupal::logger('agnesian_eventbrite')
        ->info('An error occurred while processing @operation with arguments : @args', [
          '@operation' => $error_operation[0],
          '@args' => print_r($error_operation[0], TRUE),
        ]);
    }
  }


  public function eventBriteImportRun($batchRun = FALSE) {
    // We access our configuration.
    $cron_config = $this->config('agnesian_eventbrite.settings');

    // Default to an hourly interval. Of course, cron has to be running at least
    // hourly for this to work.
    $interval = $cron_config->get('interval');
    $interval = !empty($interval) ? $interval : 86400;


    $hit_rate_limit = \Drupal::state()
      ->get('agnesian_eventbrite.hit_rate_limit');
    $hit_rate_limit = !empty($hit_rate_limit) ? $hit_rate_limit : 0;
    if (REQUEST_TIME >= $hit_rate_limit) {
      \Drupal::state()
        ->set('agnesian_eventbrite.hit_rate_limit', 0);

      /** @var QueueFactory $queue_factory */
      $queue_factory = \Drupal::service('queue');
      $queue = $queue_factory->get('import_eventbrite_event');
      $numberOfItems = $queue->numberOfItems();

      if ($numberOfItems > 0) {
        //Empty Queue before importing new events.
        if ($batchRun == TRUE) {
          $this->processQueueItemsWithBatch();
        } else {
          $this->processQueue();
        }
      }
      else {
        $next_execution = \Drupal::state()
          ->get('agnesian_eventbrite.next_execution');
        $next_execution = !empty($next_execution) ? $next_execution : 0;
        if (REQUEST_TIME >= $next_execution) {

          // Load eventbrite events.
          $this->eventBritePopulateQueue();

          \Drupal::state()
            ->set('agnesian_eventbrite.next_execution', REQUEST_TIME + $interval);

          //If submitted through Form, process one batch of events after queue has been loaded.
          if ($batchRun == TRUE) {
            $this->processQueueItemsWithBatch();
          } else {
            $this->processQueue();
          }
        }
        else {
          \Drupal::logger('agnesian_eventbrite')
            ->notice('Eventbrite Event Import has been skipped. Import will occur at the next interval.');
        }
      }
      return TRUE;
    }
    return FALSE;
  }
}