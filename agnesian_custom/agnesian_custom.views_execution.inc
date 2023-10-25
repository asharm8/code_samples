<?php

/**
 * @file
 * Provides views processing for the Agnesia custom module.
 */

/**
 * Implements hook_views_post_execute().
 */
function agnesian_custom_views_post_execute($view) {
  if (($view->id() == 'related_testimonials') && count($view->result)) {
    // Aggregation on this view generates a SQL error.
    // Derive a unique set of testimonials using PHP instead.
    $ids = [];
    $before_count = count($view->result);
    foreach($view->result as $index => $result) {
      if (isset($ids[$result->_entity->id()])) {
        // Duplicate row, remove it.
        unset($view->result[$index]);
      } else {
        // First time we've seen this testimonial.
        $ids[$result->_entity->id()] = TRUE;
      }
    }
    $after_count = count($view->result);

    if ($after_count < $before_count) {
      // Rows were removed, eliminate any holes in the array.
      $view->result = array_values($view->result);
    }
  }
}

/**
 * Implements hook_views_query_alter().
 */
function agnesian_custom_views_query_alter($view, $query) {
  if ($view->id() == 'find_a_doctor') {
    // Traverse through the 'where' part of the query.
    foreach ($query->where as &$condition_group) {
      foreach ($condition_group['conditions'] as &$condition) {
        if (strpos($condition['field'], 'field_address_locality')) {
          $condition['operator'] = 'IN';
        }
        if (strpos($condition['field'], 'field_new_patients')) {
          $condition['operator'] = '<>';
          if($condition['value'] == 0){
            unset($condition);
          }
        }
      }
    }
  }
}
