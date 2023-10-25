<?php

namespace Drupal\aap_utilities\Plugin\Field\FieldFormatter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;


/**
 * Class MetatagFormatter
 *
 * @FieldFormatter(
 *   id = "metatag_custom",
 *   label = @Translation("Meta Keywords"),
 *   field_types = {
 *     "metatag"
 *   }
 * )
 */
class MetatagFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    // Get the keywords of the node and output as plain text.
    $allMeta = $items->getValue();
    if (isset($allMeta[0]['value'])) {
      $unserializedMeta = unserialize($allMeta[0]['value']);
      if (isset($unserializedMeta['keywords'])) {
        $data = $unserializedMeta['keywords'];
        $element[0] = [
          '#plain_text' => $data,
        ];
        return $element;
    }
      else {
        return [];
      }
    }
  }
}