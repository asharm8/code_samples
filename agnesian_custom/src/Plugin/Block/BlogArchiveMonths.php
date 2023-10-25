<?php

namespace Drupal\agnesian_custom\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

use \DateTime;
use \DateTimeZone;


/**
 * Provides an Archive block with a list of months for which there are blog entries.
 *
 * @Block(
 *   id = "blog_archive_months",
 *   admin_label = @Translation("Blog Archive")
 * )
 */
class BlogArchiveMonths extends BlockBase {

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'access content');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $month_list = [];

    // Get the list of all active blog entries.
    // Using that list, build the list of unique months.
    $query = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->condition('type', 'blog')
      ->sort('field_date', 'DESC')
    ;
    $nids = $query->execute();
    if (!empty($nids)) {
      foreach ($nids as $nid) {
        $node = Node::load($nid);
        $date = $node->get('field_date')->getValue();
        $date = (!empty($date[0]['value'])) ? $date[0]['value'] : '';
        $this->addUniqueMonths($month_list, $date);
      }
    }

    // Limit the archive list of months to the last 2 years.
    $month_list = array_slice($month_list, 0, 25);

    // Generate the block contents from the list.
    $markup = '<ul class="blog-archive">';
    foreach ($month_list as $month) {
      $markup .= '<li>' . $this->buildArchiveLink($month) . '</li>';
    }
    $markup .= '</ul>';

    // Build the block.
    if (!empty($month_list)) {
      $build['agnesian_archive'] = array(
        '#markup' => $markup,
        '#prefix' => '<div class="blog-archive-container">',
        '#suffix' => '</div>',
      );
      $build['#cache']['max-age'] = 0;
    }

    return $build;
  }

  /**
   * Build a link to the blog landing page for this specific month.
   */
  private function buildArchiveLink($month) {
    $datetime = DateTime::createFromFormat('Y-m', $month);

    $day_first = DateTime::createFromFormat('Y-m-d', $month . '-01');

    $day_last = clone $day_first;
    $day_last->modify('+1 month');
    $day_last->modify('-1 day');

    $min = $day_first->format('m/d/Y');
    $max = $day_last->format('m/d/Y');

    $url = urlencode('date[min]') . '=' . urlencode($min) . '&' . urlencode('date[max]') . '=' . urlencode($max);
    $link = '<a href="' . '/blog?' . $url . '">' . $datetime->format('F Y') . '</a>';
    return $link;
  }

  /**
   * Keep track of unique months for the blog archive.
   */
  private function addUniqueMonths(&$month_list, $date) {
    $datetime = DateTime::createFromFormat('Y-m-d', $date);
    $month = $datetime->format('Y-m');

    // Lok for this month in the array.
    $match = FALSE;
    if (!empty($month_list)) {
      foreach ($month_list as $saved_month) {
        if ($saved_month == $month) {
          $match = TRUE;
          break;
        }
      }
    }

    // Save this month to the array if not already found.
    if (!$match) {
      $month_list[] = $month;
    }
  }

}
