<?php

namespace Drupal\aap_utilities\LinkGenerator;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\GeneratedLink;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGenerator as CoreLinkGenerator;
use Drupal\Core\Utility\LinkGeneratorInterface as CoreLinkGeneratorInterface;

/**
 * Provides a class which generates a link with route names and parameters.
 */
class LinkGenerator extends CoreLinkGenerator implements CoreLinkGeneratorInterface {

  /**
   * {@inheritdoc}
   *
   * For anonymous users, the "active" class will be calculated on the server,
   * because most sites serve each anonymous user the same cached page anyway.
   * For authenticated users, the "active" class will be calculated on the
   * client (through JavaScript), only data- attributes are added to links to
   * prevent breaking the render cache. The JavaScript is added in
   * system_page_attachments().
   *
   * @see system_page_attachments()
   */
  public function generate($text, Url $url) {
    // Performance: avoid Url::toString() needing to retrieve the URL generator
    // service from the container.
    $url->setUrlGenerator($this->urlGenerator);

    if (is_array($text)) {
      $text = $this->renderer->render($text);
    }

    // Start building a structured representation of our link to be altered later.
    $variables = array(
      'text' => $text,
      'url' => $url,
      'options' => $url->getOptions(),
    );

    // Merge in default options.
    $variables['options'] += array(
      'attributes' => array(),
      'query' => array(),
      'language' => NULL,
      'set_active_class' => FALSE,
      'absolute' => FALSE,
    );

    // Add a hreflang attribute if we know the language of this link's url and
    // hreflang has not already been set.
    if (!empty($variables['options']['language']) && !isset($variables['options']['attributes']['hreflang'])) {
      $variables['options']['attributes']['hreflang'] = $variables['options']['language']->getId();
    }

    // Ensure that query values are strings.
    array_walk($variables['options']['query'], function(&$value) {
      if ($value instanceof MarkupInterface) {
        $value = (string) $value;
      }
    });

    // Set the "active" class if the 'set_active_class' option is not empty.
    if (!empty($variables['options']['set_active_class']) && !$url->isExternal()) {
      // Add a "data-drupal-link-query" attribute to let the
      // drupal.active-link library know the query in a standardized manner.
      if (!empty($variables['options']['query'])) {
        $query = $variables['options']['query'];
        ksort($query);
        $variables['options']['attributes']['data-drupal-link-query'] = Json::encode($query);
      }

      // Add a "data-drupal-link-system-path" attribute to let the
      // drupal.active-link library know the path in a standardized manner.
      if ($url->isRouted() && !isset($variables['options']['attributes']['data-drupal-link-system-path'])) {
        // @todo System path is deprecated - use the route name and parameters.
        $system_path = $url->getInternalPath();
        // Special case for the front page.
        $variables['options']['attributes']['data-drupal-link-system-path'] = $system_path == '' ? '<front>' : $system_path;
      }
    }

    // Remove all HTML and PHP tags from a tooltip, calling expensive strip_tags()
    // only when a quick strpos() gives suspicion tags are present.
    if (isset($variables['options']['attributes']['title']) && strpos($variables['options']['attributes']['title'], '<') !== FALSE) {
      $variables['options']['attributes']['title'] = strip_tags($variables['options']['attributes']['title']);
    }

    // Allow other modules to modify the structure of the link.
    $this->moduleHandler->alter('link', $variables);
    $url = $variables['url'];

    // Move attributes out of options since generateFromRoute() doesn't need
    // them. Include a placeholder for the href.
    $attributes = array('href' => '') + $variables['options']['attributes'];
    unset($variables['options']['attributes']);
    $url->setOptions($variables['options']);

    // External URLs can not have cacheable metadata.
    if ($url->isExternal()) {
      $generated_link = new GeneratedLink();
      $attributes['href'] = $url->toString(FALSE);
    }
    else {
      $generated_url = $url->toString(TRUE);

      $generated_link = GeneratedLink::createFromObject($generated_url);
      // The result of the URL generator is a plain-text URL to use as the href
      // attribute, and it is escaped by \Drupal\Core\Template\Attribute.

      $gen_url = $generated_url->getGeneratedUrl();
      $gen_array = explode('/',$gen_url);
      $ct = 0;
      foreach($gen_array as $key => $val) {
        if($ct > 0 && $val == 'r') {
          unset($gen_array[$key]);
        }
        if ($val == 'r') {
          $ct++;
        }
      }
      $gen_url = implode('/', $gen_array);
      $attributes['href'] = $gen_url;


      // Determine if the configuration is enabled to add a trailing slash.
      $aapHeaderConfig = \Drupal::config('aap.trailingSlash');
      $addTrailingSlash = $aapHeaderConfig->get('addTrailingSlash');
      if ($addTrailingSlash > 0) {
        // If the generated url has query params (identified by a ?), add the slash before the ?
        if (strpos($attributes['href'], '/admin') !== 0 && strpos($attributes['href'], '?') > 0) {
          $attributes['href'] = str_replace('?', '/?', $attributes['href']);
        }

        // Prevent a trailing slash if the generated url starts with '/admin' (to prevent ajax errors)
        if (strpos($attributes['href'], '/admin') !== 0 && strpos($attributes['href'], '?') == FALSE) {
          $attributes['href'] .= "/";
        }

      }


    }

    if (!($variables['text'] instanceof MarkupInterface)) {
      $variables['text'] = Html::escape($variables['text']);
    }
    $attributes = new Attribute($attributes);
    // This is safe because Attribute does escaping and $variables['text'] is
    // either rendered or escaped.
    return $generated_link->setGeneratedLink('<a' . $attributes . '>' . $variables['text'] . '</a>');
  }

}
