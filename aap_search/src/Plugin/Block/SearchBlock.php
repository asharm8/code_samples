<?php

namespace Drupal\aap_search\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;
use Drupal\Component\Utility\Xss;


/**
 * Provides a 'article' block.
 *
 * @Block(
 *   id = "search_block",
 *   admin_label = @Translation("Search Form block"),
 * )
 */
class SearchBlock extends BlockBase {
  public function build() {
    $form = \Drupal::formBuilder()->getForm('\Drupal\aap_search\Form\SearchForm');
//    $form = [
//      '#type' => 'markup',
//      '#markup' => "HELLO",
//    ];
    return $form;
  }
}