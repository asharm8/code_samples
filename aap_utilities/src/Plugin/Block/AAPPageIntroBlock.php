<?php

namespace Drupal\aap_utilities\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 *
 * @Block(
 *  id = "aap_page_intro_block",
 *  admin_label = @Translation("Page Intro Block"),
 *  )
 */

class AAPPageIntroBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['aap_page_intro_text'] = array(
      '#type' => 'text_format',
      '#title' => $this->t('Page Introduction Text'),
      '#description' => $this->t('Enter the introduction text.'),
      '#default_value' => $this->configuration['aap_page_intro_text']['value'],
    );
    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['aap_page_intro_text']
      = $form_state->getValue('aap_page_intro_text');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#type' => 'markup',
      '#markup' => $this->configuration['aap_page_intro_text']['value'],
    );

  }

}