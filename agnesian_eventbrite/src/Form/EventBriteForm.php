<?php

/**
 * @file
 * Contains \Drupal\agnesian_eventbrite\Form\EventBriteForm.
 */

namespace Drupal\agnesian_eventbrite\Form;

use Drupal\agnesian_eventbrite\Controller\AgnesianEventBriteController;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\CronInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Import physicians from a file.
 */
class EventBriteForm extends ConfigFormBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;


  /**
   * The queue object.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queue;

  /**
   * The state keyvalue collection.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, AccountInterface $current_user, QueueFactory $queue, StateInterface $state) {
    parent::__construct($config_factory);
    $this->currentUser = $current_user;
    $this->queue = $queue;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('queue'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'eventbrite_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['agnesian_eventbrite.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $eventbrite_config = $this->config('agnesian_eventbrite.settings');

    $form['status'] = [
      '#type' => 'details',
      '#title' => $this->t('Eventbrite API Import Information'),
      '#open' => TRUE,
    ];

    $next_execution = \Drupal::state()
      ->get('agnesian_eventbrite.next_execution');
    $next_execution = !empty($next_execution) ? $next_execution : REQUEST_TIME;

    $args = [
      '%time' => date_iso8601(\Drupal::state()
        ->get('agnesian_eventbrite.next_execution')),
      '%seconds' => $next_execution - REQUEST_TIME,
    ];
    $form['status']['last'] = [
      '#type' => 'item',
      '#markup' => $this->t('Eventbrite events import will occur after %time (%seconds seconds from now).', $args),
    ];

    $queue_factory = \Drupal::service('queue');
    $queue = $queue_factory->get('import_eventbrite_event');
    //$queue->numberOfItems();
    $number_in_queue = $queue->numberOfItems();
    $args = [
      '%queue' => $number_in_queue,
    ];
    $form['status']['queue'] = [
      '#type' => 'item',
      '#markup' => $this->t('%queue events in queue pending entity creation.', $args),
    ];

    $hit_rate_limit = \Drupal::state()
      ->get('agnesian_eventbrite.hit_rate_limit');
    $hit_rate_limit = !empty($hit_rate_limit) ? $hit_rate_limit : REQUEST_TIME;
    if ($hit_rate_limit > REQUEST_TIME) {
      $args = [
        '%time' => date_iso8601(\Drupal::state()
          ->get('agnesian_eventbrite.hit_rate_limit')),
        '%seconds' => $hit_rate_limit - REQUEST_TIME,
      ];

      $form['status']['hit_rate_limit'] = [
        '#type' => 'item',
        '#markup' => $this->t('Maximum Connections to Eventbrite API has been reached. Restrictions will be removed after %time (%seconds seconds from now). ', $args),
      ];
    }

    $form['configuration'] = [
      '#type' => 'details',
      '#title' => $this->t('Configuration of Eventbrite API'),
      '#open' => TRUE,
    ];
    $form['configuration']['interval'] = [
      '#type' => 'select',
      '#title' => $this->t('Cron interval'),
      '#description' => $this->t('Time after which agnesian_eventbrite_cron will respond to a processing request.'),
      '#default_value' => $eventbrite_config->get('interval'),
      '#options' => [
        60 => $this->t('1 minute'),
        300 => $this->t('5 minutes'),
        3600 => $this->t('1 hour'),
        86400 => $this->t('1 day'),
      ],
    ];

    $form['configuration']['batch'] = [
      '#type' => 'select',
      '#title' => $this->t('Batch Size'),
      '#description' => $this->t('Number of queued items to process during cron runs.'),
      '#default_value' => $eventbrite_config->get('batch'),
      '#options' => [
        50 => $this->t('50'),
        100 => $this->t('100'),
        200 => $this->t('200'),
        300 => $this->t('300'),
        400 => $this->t('400'),
        500 => $this->t('500'),
        1000 => $this->t('1000'),
      ],
    ];

    $form['configuration']['base_api_url'] = array(
      '#type' => 'textfield',
      '#title' => t('Eventbrite API URL'),
      '#maxlength' => 255,
      '#default_value' => $eventbrite_config->get('base_api_url') ? $eventbrite_config->get('base_api_url') : 'https://www.eventbriteapi.com/v3/',
      '#description' => t('The default Eventbrite API URL should be entered here. Remember to include trailing slash.'),
    );

    $form['configuration']['auth_token'] = array(
      '#type' => 'textfield',
      '#title' => t('Eventbrite Authorization Token'),
      '#maxlength' => 255,
      '#default_value' => $eventbrite_config->get('auth_token') ? $eventbrite_config->get('auth_token') : 'R23I2YX6HU4AIGGSLB63',
      '#description' => t('Eventbrite oAuth Token should be entered here. Use private token and not anonymous token.'),
    );

    $form['configuration']['event_reload'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Force update all events regardless if it already exists in system."),
      '#default_value' => $eventbrite_config->get('force_reload') ? $eventbrite_config->get('force_reload') : 0,
    ];

    if ($this->currentUser->hasPermission('administer site configuration')) {
      $form['import_run'] = [
        '#type' => 'details',
        '#title' => $this->t('Run Eventbrite Import Manually'),
        '#open' => TRUE,
      ];
      $form['import_run']['import_reset'] = [
        '#type' => 'checkbox',
        '#title' => $this->t("Run import regardless of whether interval has expired."),
        '#default_value' => FALSE,
      ];

      $form['cron_run']['cron_trigger']['actions'] = ['#type' => 'actions'];
      $form['cron_run']['cron_trigger']['actions']['sumbit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Run Import Now'),
        '#submit' => [[$this, 'importRun']],
      ];
    }

    return parent::buildForm($form, $form_state);
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('agnesian_eventbrite.settings')
      ->set('base_api_url', $form_state->getValue('base_api_url'))
      ->set('auth_token', $form_state->getValue('auth_token'))
      ->set('interval', $form_state->getValue('interval'))
      ->set('batch', $form_state->getValue('batch'))
      ->set('force_reload', $form_state->getValue('event_reload'))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Allow user to directly execute cron, optionally forcing it.
   */
  public function importRun(array &$form, FormStateInterface &$form_state) {
    $config = $this->configFactory->getEditable('agnesian_eventbrite.settings');
    //Load Controller
    $eventbrite = new AgnesianEventBriteController();

    $import_reset = $form_state->getValue('import_reset');
    if (!empty($import_reset)) {
      \Drupal::state()->set('agnesian_eventbrite.next_execution', 0);
    }


    // Use a state variable to signal that cron was run manually from this form.
    $this->state->set('agnesian_eventbrite_show_status_message', TRUE);

    if ($eventbrite->eventBriteImportRun(TRUE)) {
      drupal_set_message($this->t('Import ran successfully.'));
    }
    else {
      drupal_set_message($this->t('Import run failed.'), 'error');
    }
  }
}
