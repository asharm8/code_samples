<?php
namespace Drupal\aap_utilities;

use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\DependencyInjection\ContainerBuilder;


class AapUtilitiesServiceProvider extends ServiceProviderBase implements ServiceModifierInterface {
  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('link_generator');
    $definition->setClass('Drupal\aap_utilities\LinkGenerator\LinkGenerator');
  }
}