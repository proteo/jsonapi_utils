<?php

namespace Drupal\jsonapi_utils\Controller;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\jsonapi_utils\Entity\WebhookInterface;
use Drupal\jsonapi_utils\WebhookDispatcher;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Enable, disable and manually flush webhooks.
 */
class WebhookController extends ControllerBase {

  public function __construct(protected WebhookDispatcher $dispatcher) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get(WebhookDispatcher::class));
  }

  /**
   * Enables a webhook.
   */
  public function enable(WebhookInterface $jsonapi_utils_webhook): RedirectResponse {
    $jsonapi_utils_webhook->enable()->save();
    $this->messenger()->addStatus($this->t('Enabled webhook %label.', ['%label' => $jsonapi_utils_webhook->label()]));
    return $this->backToList();
  }

  /**
   * Disables a webhook.
   */
  public function disable(WebhookInterface $jsonapi_utils_webhook): RedirectResponse {
    $jsonapi_utils_webhook->disable()->save();
    $this->messenger()->addStatus($this->t('Disabled webhook %label.', ['%label' => $jsonapi_utils_webhook->label()]));
    return $this->backToList();
  }

  /**
   * Flushes the frontend cache of a webhook right now.
   */
  public function flush(WebhookInterface $jsonapi_utils_webhook): RedirectResponse {
    $changes = [['event' => 'manual.flush', 'nonce' => Crypt::randomBytesBase64(16)]];
    if ($jsonapi_utils_webhook->status() && $this->dispatcher->send($jsonapi_utils_webhook, $changes, 'manual')) {
      $this->messenger()->addStatus($this->t('Frontend cache flush requested for %label.', ['%label' => $jsonapi_utils_webhook->label()]));
    }
    else {
      $this->messenger()->addError($this->t('The frontend cache flush for %label failed. Check the log for details.', ['%label' => $jsonapi_utils_webhook->label()]));
    }
    return $this->backToList();
  }

  /**
   * Redirects to the webhook list.
   */
  protected function backToList(): RedirectResponse {
    return new RedirectResponse(Url::fromRoute('entity.jsonapi_utils_webhook.collection')->toString());
  }

}
