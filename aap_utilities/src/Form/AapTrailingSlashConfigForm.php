<?php

namespace Drupal\aap_utilities\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class AapTrailingSlashConfigForm extends ConfigFormBase {

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
    return 'aap_trailing_slash_settings';
  }


  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['aap.trailingSlash'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('aap.trailingSlash');
    $form['addTrailingSlash'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add trailing slash'),
      '#description' => $this->t('When checked, a trailing slash will be added to programmatically-generated URLs. NOTE: When changing this setting, a Drupal Cache Rebuild is required for the change to take effect!'),
      '#default_value' => $config->get('addTrailingSlash'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('aap.trailingSlash')
      ->set('addTrailingSlash', $form_state->getValue('addTrailingSlash'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}