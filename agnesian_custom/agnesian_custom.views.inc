<?php

/**
 * @file
 * Provides views data for the Agnesia custom module.
 */

use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_field_views_data_alter().
 *
 * Views integration for entity reference fields which reference nodes.
 * Adds a node relationship to the default field data.
 *
 * @see views_field_default_views_data()
 */
function agnesian_custom_field_views_data_alter(array &$data, FieldStorageConfigInterface $field_storage) {
  if ($field_storage->getType() == 'entity_reference' && $field_storage->getSetting('target_type') == 'node') {
    foreach ($data as $table_name => $table_data) {
      foreach ($table_data as $field_name => $field_data) {
        if (isset($field_data['filter']) && $field_name != 'delta') {
          if ($field_name == 'field_location_target_id') {
            $data[$table_name][$field_name]['filter']['id'] = 'entity_reference_target_id';
          }
          if ($field_name == 'field_birth_location_target_id') {
            $data[$table_name][$field_name]['filter']['id'] = 'entity_reference_target_id';
          }
        }
      }
    }
  }
}
