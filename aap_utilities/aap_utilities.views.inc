<?php

/**
 * Implements hook_views_data_alter().
 */
function aap_utilities_views_data_alter(array &$data) {
  $data['node']['event_date_formatter'] = array(
    'title' => t('Event Date Formatter'),
    'field' => array(
      'title' => t('Event date formatter for Views'),
      'help' => t('Formats an event date for AAP'),
      'id' => 'event_date_formatter',
    ),
  );

}