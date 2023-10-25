<?php

/**
 * @file
 * Contains \Drupal\agnesian_custom\Form\PDFDirectoryForm.
 */

namespace Drupal\agnesian_custom\Form;

use Drupal\agnesian_custom\Controller\AgnesianCustomController;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Import physicians from a file.
 */
class PDFDirectoryForm extends ConfigFormBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The plugin manager for our Print engines.
   *
   * @var \Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface
   */
  protected $pluginManager;

  /**
   * The state keyvalue collection.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, AccountInterface $current_user, StateInterface $state) {
    parent::__construct($config_factory);
    $this->currentUser = $current_user;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pdf_directory_form';
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

    $cache = \Drupal::cache();
    $cid = 'agnesian_full_provider_directory';
    $generated = $cache->get($cid);

    $form['status'] = [
      '#type' => 'details',
      '#title' => $this->t('Find a Doctor PDF Directory Information'),
      '#open' => TRUE,
    ];

    $last_execution = \Drupal::state()
      ->get('agnesian_custom.last_pdf_execution');
    $last_execution = !empty($last_execution) ? $last_execution : 0;

    $args = [
      '%time' => date_iso8601(\Drupal::state()
        ->get('agnesian_eventbrite.last_execution')),
      '%seconds' => REQUEST_TIME - $last_execution,
    ];
    $form['status']['last'] = [
      '#type' => 'item',
      '#markup' => $this->t('Find a Doctor PDF Directory was last generated %time (%seconds seconds ago).', $args),
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Regenerate Doctor Directory PDF'),
      '#button_type' => 'primary',
    );

    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('agnesian_custom.pdfgen_no_cache');
  }


}
