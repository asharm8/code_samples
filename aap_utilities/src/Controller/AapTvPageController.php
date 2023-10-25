<?php
namespace Drupal\aap_utilities\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

class AapTvPageController extends ControllerBase implements ContainerInjectionInterface
{
  /**
   * Returns rendered empty page
   *
   * @return array
   */
  public function view()
  {
    return [
      '#markup' => '',
    ];
  }
}