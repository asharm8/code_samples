<?php

namespace Drupal\aap_utilities\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure where tag links should point to on certain pages.
 */
class TagLinksForm extends ConfigFormBase {
  /**
   * Constructs a MenuForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'aap_tag_links_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['aap.tagLinks'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('aap.tagLinks');
    $form['tagLinkPattern'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Tag Link Pattern'),
      '#description' => $this->t('Provide the path to be used when generating links from Tags. Use [termname] to indicate where the string of the term name should placed.'),
      '#default_value' => $config->get('tagLinkPattern'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('aap.tagLinks')
      ->set('tagLinkPattern', $form_state->getValue('tagLinkPattern'))
      ->save();
    parent::submitForm($form, $form_state);
  }
}