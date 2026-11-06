<?php

namespace Drupal\jsonapi_utils\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\jsonapi_utils\Entity\WebhookInterface;
use Psr\Log\LoggerInterface;

/**
 * Logs changes to webhook configuration.
 */
class WebhookHooks {

  /**
   * Logger channel.
   */
  protected LoggerInterface $logger;

  public function __construct(
    LoggerChannelFactoryInterface $loggerFactory,
    protected AccountInterface $currentUser,
  ) {
    $this->logger = $loggerFactory->get('jsonapi_utils');
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for webhooks.
   */
  #[Hook('jsonapi_utils_webhook_insert')]
  public function insert(EntityInterface $webhook): void {
    $this->log('Webhook @id created by @user.', $webhook);
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for webhooks.
   */
  #[Hook('jsonapi_utils_webhook_update')]
  public function update(WebhookInterface $webhook): void {
    $previous = $webhook->original ?? NULL;
    if ($previous && $previous->status() !== $webhook->status()) {
      $this->log($webhook->status() ? 'Webhook @id enabled by @user.' : 'Webhook @id disabled by @user.', $webhook);
      return;
    }
    $this->log('Webhook @id updated by @user.', $webhook);
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for webhooks.
   */
  #[Hook('jsonapi_utils_webhook_delete')]
  public function delete(EntityInterface $webhook): void {
    $this->log('Webhook @id deleted by @user.', $webhook);
  }

  /**
   * Writes a log entry; never includes the secret.
   */
  protected function log(string $message, EntityInterface $webhook): void {
    $this->logger->notice($message, [
      '@id' => $webhook->id(),
      '@user' => $this->currentUser->getAccountName(),
    ]);
  }

}
