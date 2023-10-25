<?php
/**
 * @file
 * Provides Eventbrite API Functionality
 */
namespace Drupal\agnesian_custom\Controller;


use Drupal\Core\Logger\LoggerChannelFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\entity_print\PrintBuilderInterface;
use Drupal\entity_print\PrintEngineException;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface;
use iio\libmergepdf\Merger;
use Drupal\node\Entity\Node;
use Drupal\image\Entity\ImageStyle;

/**
 * Class AgnesianCustomController
 * @package Drupal\agnesian_custom\Controller
 */
class AgnesianCustomController extends ControllerBase {

  /**
   * The plugin manager for our Print engines.
   *
   * @var \Drupal\entity_print\Plugin\EntityPrintPluginManagerInterface
   */
  protected $pluginManager;

  /**
   * The Print builder.
   *
   * @var \Drupal\entity_print\PrintBuilderInterface
   */
  protected $printBuilder;

  /**
   * The Entity Type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityPrintPluginManagerInterface $plugin_manager, PrintBuilderInterface $print_builder, EntityTypeManagerInterface $entity_type_manager, Request $current_request, AccountInterface $current_user, LoggerChannelFactory $logger) {
    $this->pluginManager = $plugin_manager;
    $this->printBuilder = $print_builder;
    $this->entityTypeManager = $entity_type_manager;
    $this->currentRequest = $current_request;
    $this->currentUser = $current_user;
    $this->logger = $logger->get('agnesian_custom');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.entity_print.print_engine'),
      $container->get('entity_print.print_builder'),
      $container->get('entity_type.manager'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('current_user'),
      $container->get('logger.factory')
    );
  }

  /**
   * @return array|bool
   */
  public function generateFullPDFS() {

    $generated = false;

    $this->logger->info('Updating Agnesian Full Directory PDF');

    //We will bump the memory from original limit (192M) to 256M during PDF generation before setting it back to default.
    $mem_limit = ini_get('memory_limit');
    ini_set('memory_limit', '384M');


    // Create the Print engine plugin.
    $config = $this->config('entity_print.settings');;

    $view1 = \Drupal\views\Views::getView('find_a_doctor');;
    $view1->get_total_rows = TRUE;
    $view1->execute();
    $rows = $view1->total_rows;


    $rowsPerPage = 100;
    $pagecount = ceil($rows/$rowsPerPage) - 1;


    for($i=0; $i<= $pagecount ; $i++) {
      /** @var \Drupal\views\Entity\View $view */
      $view = \Drupal::entityTypeManager()->getStorage('view')->load('find_a_doctor');
      $executable = $view->getExecutable();
      $executable->setDisplay('entity_print_views_print_group');
      $executable->getPager();
      $executable->setItemsPerPage($rowsPerPage);
      $executable->setCurrentPage($i);

      try {
        $print_engine = $this->pluginManager->createSelectedInstance('pdf');
      }
      catch (PrintEngineException $e) {
        // Build a safe markup string using Xss::filter() so that the instructions
        // for installing dependencies can contain quotes.
        drupal_set_message(new FormattableMarkup('Error generating Print: ' . Xss::filter($e->getMessage()), []), 'error');

        $url = $executable->hasUrl(NULL, 'entity_print_views_print_group') ? $executable->getUrl(NULL, 'entity_print_views_print_group')->toString() : Url::fromRoute('<front>');
        return new RedirectResponse($url);
      }

      //Save this document.
      $uri =$this->printBuilder->savePrintable([$view], $print_engine, 'public', 'pdfdir'.$i.'.pdf', $config->get('default_css'));
    }

    $this->logger->info('@count PDFs has been generated. Merging documents to one file.', ['@count' => $pagecount]);


    //PDF Merging Works. We will have to loop this.
    $m = new Merger();
    for($i=0; $i<= $pagecount ; $i++) {
      $m->addFromFile('sites/default/files/pdfdir'.$i.'.pdf');
    }
    file_put_contents('sites/default/files/AgnesianFullDirectory.pdf', $m->merge());

    $this->logger->info(' PDF Files Merged. Removing Temporary Files');

    //Remove Temporary PDF Files
    $publicDir = drupal_realpath('public://');
    $currentDir = getcwd(); // Save the current directory

    chdir($publicDir);
      foreach (glob("pdfdir*.pdf") as $filename) {
        unlink($filename);
      }
    chdir($currentDir);

    //Let's change it back to original memory limit.
    ini_set('memory_limit', $mem_limit);


    if (file_exists('sites/default/files/AgnesianFullDirectory.pdf')) {
      $generated = TRUE;
      $this->logger->info('Agnesian PDF Directory has been generated. File can be found at sites/default/files/AgnesianFullDirectory.pdf');
    }
    return $generated;
  }

  /**
   * @return array or RedirectResponse
   */
  public function generateFullPDFThroughRoute($force=FALSE) {
    $cache = \Drupal::cache();

    // If the cache flag is set, it means the PDF has already been generated.
    // Simply return the generated file.
    $cid = 'agnesian_full_provider_directory';
    $generated = $cache->get($cid);
    if (!$generated || $force == TRUE) {
      //Before we begin PDF Generation, Lets ensure all medium image styles are generated.
      //This function generates all defined image styles for Doctor Photos.
      $this->generatePhysicianImageStyles();

      // Th cache is not set or has expired.
      // Regenerate the PDF.
      try {
        $generated = $this->generateFullPDFS();
      } catch (Exception $e) {
        $this->logger->error('Error generating PDF of Full Provider Directory.');
      }

      if ($generated) {
        // Cache the newly generated directory for 6 hours.
        $cache->set($cid, TRUE, time() + (6 * 60 * 60));
      }
    }

    if ($generated) {
      return new RedirectResponse('/sites/default/files/AgnesianFullDirectory.pdf');
    } else {
      $build = [
        '#title' => $this->t('Full Provider Directory'),
        '#markup' => '<div class="outer-wrapper">' . $this->t('Sorry, an error occurred, please try again later.') . '</div>',
      ];
      return $build;
    }
  }

  /**
   * Generates Image Styles for all uploaded Physician Photos.
   */
  public function generatePhysicianImageStyles() {
    //Let's ensure all image styles are generated before Full PDF Directory Gen occurs.
    $query = \Drupal::entityQuery('node')
      ->condition('status', 1)
      ->condition('type', 'physician');
    $nids = $query->execute();
    $node_exists = !empty($nids);
    if ($node_exists) {
      foreach ($nids as $nid) {
        $entity = Node::load($nid);
        if (!empty($entity->field_image->entity)) {
          $image = \Drupal::service('image.factory')
            ->get($entity->field_image->entity->getFileUri());

          /** @var \Drupal\Core\Image\Image $image */
          if ($image->isValid()) {
            $styles = ImageStyle::loadMultiple();
            $image_uri = $entity->field_image->entity->getFileUri();
            /** @var \Drupal\image\Entity\ImageStyle $style */
            foreach ($styles as $style) {
              $destination = $style->buildUri($image_uri);
              if (!file_exists($destination)) {
                $style->createDerivative($image_uri, $destination);
              }
            }
          }
        }
      }
    }
  }

}
