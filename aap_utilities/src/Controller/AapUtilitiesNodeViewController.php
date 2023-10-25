<?php

namespace Drupal\aap_utilities\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Controller\EntityViewController;

/**
 * Defines a controller to render a single node.
 */
class AapUtilitiesNodeViewController extends EntityViewController {

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $node, $view_mode = 'full', $langcode = NULL) {
    $build = parent::view($node, $view_mode, $langcode);

    return $build;
  }

  /**
   * The _title_callback for the page that renders a single node.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   The current node.
   *
   * @return string
   *   The page title.
   */
  public function title(EntityInterface $node) {
    return $this->entityManager->getTranslationFromContext($node)->label();
  }

}