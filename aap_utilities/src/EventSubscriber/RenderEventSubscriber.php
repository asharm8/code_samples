<?php

namespace Drupal\aap_utilities\EventSubscriber;

use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Render\AttachmentsResponseProcessorInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Response subscriber to handle HTML responses.
 */
class RenderEventSubscriber implements EventSubscriberInterface  {
  /**
   * The HTML response attachments processor service.
   *
   * @var \Drupal\Core\Render\AttachmentsResponseProcessorInterface
   */
  protected $htmlResponseAttachmentsProcessor;

  /**
   * Constructs a HtmlResponseSubscriber object.
   *
   * @param \Drupal\Core\Render\AttachmentsResponseProcessorInterface $html_response_attachments_processor
   *   The HTML response attachments processor service.
   */
  public function __construct(AttachmentsResponseProcessorInterface $html_response_attachments_processor) {
    $this->htmlResponseAttachmentsProcessor = $html_response_attachments_processor;
  }


  /**
   * Code that should be triggered on event specified
   */
  public function onRespond(FilterResponseEvent $event) {
    $response = $event->getResponse();
    if (!$response instanceof HtmlResponse) {
      return;
    }

    $attachments = $response->getAttachments();
    if (isset($attachments['html_head'])) {
      foreach ($attachments['html_head'] as $key => $attachment) {
        if (isset($attachment[0]['#attributes']['rel']) && $attachment[0]['#attributes']['rel'] == 'canonical' && $attachment[1] != "canonical_url") {
          unset($attachments['html_head'][$key]);
        }
      }
    }
    $attachments['http_header'] = [];
    $response->setAttachments($attachments);

    $response = $this->htmlResponseAttachmentsProcessor->processAttachments($response);
    $event->setResponse($response);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // For this example I am using KernelEvents constants (see below a full list).
    $events[KernelEvents::RESPONSE][] = ['onRespond'];
    return $events;
  }

}
