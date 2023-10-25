<?php

namespace Drupal\aap_search\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\Xss;

class SearchForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_form';
  }

  /**
   * {@inheritdoc}
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $options[0] = 'All';
    $facet_query = !empty(Xss::filter(\Drupal::request()->get('f'))) ? implode(Xss::filter(\Drupal::request()->get('f'))) : 0;
    $facet_tid =  !empty ($facet_query) ? (int) str_replace("subcategory:", "", $facet_query) : 0;
    $manager = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    foreach($manager->loadTree('category', 0, 2, TRUE) as $term) {
      if (!empty($manager->loadParents($term->id()))) {
        $options[$term->id()] = t($term->getName());
        $default_value = ($facet_tid == $term->id()) ? (string) $term->id() : "0";
      }
    }

    $form['categories_select'] = [
      '#type' => 'select',
      '#title' => $this
        ->t('All'),
      '#options' => $options,
      '#prefix' => '<div class="search-cat-dropdown cat-dropdown">',
      '#suffix' => '</div>',
      '#default_value' => $default_value,
    ];
    $form['search-keyword'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search Articles & Events'),
      '#label' => $this->t('Search Articles & Events'),
      '#size' => 20,
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#button_type' => 'primary',
      '#attributes' => array('class' => array('visually-hidden')),
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Redirect user to the search form.
    $keyword = $form_state->getValue('search-keyword');
    $categories_select = $form_state->getValue('categories_select');
    $category_filter = ($categories_select != '0') ? "?f%5B0%5D=subcategory%3A" . $categories_select : "";
    $url = Url::fromUri('internal:/search/' . $keyword . $category_filter);
    $form_state->setRedirectUrl($url);
  }

}