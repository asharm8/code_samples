<?php

namespace Drupal\agnesian_custom\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;

/**
 * Provide a form that allows users to find doctors by name, keyword and specialty.
 */
class DoctorSearch extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'doctor_search';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $show_search_for = FALSE) {
    $form['#prefix'] = '<div class="view-filters">';
    $form['#suffix'] = '</div>';

    // Optionally show the "Search for..." area
    if ($show_search_for) {
      $form['search_for'] = [
        '#prefix' => '<div class="search-for"><h2>' . t('Search for ...') . '</h2></div>',
        '#type' => 'radios',
        '#options' => [
          'doctors' => $this->t('Doctors'),
          'locations' => $this->t('Locations'),
          'treatments' => $this->t('Treatments'),
          'conditions' => $this->t('Conditions'),
        ],
        '#default_value' => 'doctors',
      ];
    }

    // Search by name.
    $form['name'] = [
      '#type' => 'textfield',
      '#placeholder' => $this->t('Search by Name...'),
      '#required' => FALSE,
    ];
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Search'),
    );

    // Search by keyword.
    $options = array();
    $query = db_select('node__field_tags', 'tags');
    $query->join('taxonomy_term_field_data', 'term', 'tags.field_tags_target_id = term.tid');
    $results = $query
      ->fields('tags')
      ->fields('term')
      ->condition('tags.bundle', 'physician')
      ->condition('tags.deleted', 0)
      ->distinct()
      ->orderBy('term.weight')
      ->orderBy('term.name')
      ->execute();
    foreach($results as $result) {
      $options[$result->field_tags_target_id] = $result->name;
    }

    $form['keyword_wrapper'] = [
      '#title' => $this->t('Search by Keyword...'),
      '#type' => 'fieldset',
    ];
    $form['keyword_wrapper']['keyword'] = [
      '#type' => 'checkboxes',
      '#options' => $options,
    ];

    // Search by specialty.
    $options = array();
    $query = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', 'specialty')
      ->sort('weight')
      ->sort('name')
      ->addTag('term_access');
    $terms = Term::loadMultiple($query->execute());
    foreach ($terms as $term) {
      $options[$term->id()] = \Drupal::entityManager()->getTranslationFromContext($term)->label();
    }

    $form['specialty_wrapper'] = [
      '#title' => $this->t('Search by Specialty...'),
      '#type' => 'fieldset',
    ];
    $form['specialty_wrapper']['specialty'] = [
      '#type' => 'checkboxes',
      '#options' => $options,
    ];

    if ($show_search_for) {
      $form['new_patients'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Accepting new patients'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Determine type of search.
    $search_for = $form_state->getValue('search_for');

    // Gather filter selections into query parameters.
    $options = [];
    $fields = ['name', 'keyword', 'specialty', 'new_patients'];
    foreach ($fields as $field) {
      $value = $form_state->getValue($field);
      if (is_array($value)) {
        $value = array_filter($value);
      }
      if (!empty($value)) {
        $options['query'][$field] = $value;
      }

      // If searching on something other than doctors, then only the name field is used.
      if (!empty($search_for) && ($search_for != 'doctors')) {
        break;
      }
    }

    // Determine the target redirect URL based on the Search for... setting.
    switch($search_for) {
      case 'locations':
        $target = 'internal:/locations';
        break;

      case 'treatments':
        $target = 'internal:/treatments';
        break;

      case 'conditions':
        $target = 'internal:/conditions';
        break;

      case 'doctors':
      default:
        $target = 'internal:/doctors';
        $options['query']['gender'] = "All";
        $options['query']['new_patients'] = "All";
        break;
    }

    // Send user to the FAD Search Results page with filters set.
    $url =  Url::fromUri($target, $options);
    $form_state->setRedirectUrl($url);
  }

}
