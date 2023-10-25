<?php

namespace Drupal\aap_utilities\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Event Date Block
 *
 * Outputs the Date/Time of the event CT in the desired format.
 *
 * @Block(
 *   id = "aap_event_date_block",
 *   admin_label = @Translation("AAP Event Date/Time Output"),
 * )
 */
class AAPEventDateBlock extends BlockBase {
  public function build() {
    $currentNode = \Drupal::routeMatch()->getParameter('node');
    if ($currentNode) {
      if ($currentNode->getType() == 'event') {
        return array(
          '#date_string' => _aap_utilities_make_event_date_string($currentNode, 'full', FALSE),
          '#time_string' => _aap_utilities_make_event_date_string($currentNode, 'full', TRUE),
          '#theme' => 'aap_utilities_event_date',
        );
      }

    }

  }
}