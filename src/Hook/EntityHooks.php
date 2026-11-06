<?php

namespace Drupal\jsonapi_utils\Hook;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\DestructableInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\jsonapi_utils\WebhookDispatcher;

/**
 * Collects content changes and flushes the matching frontend caches.
 *
 * Requests are deferred to destruct() so that the database transaction is
 * committed before the frontend refetches, and so that several saves in one
 * request result in a single request per webhook.
 */
class EntityHooks implements DestructableInterface {

  /**
   * Changes collected during this request.
   */
  protected array $changes = [];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected WebhookDispatcher $dispatcher,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function destruct(): void {
    if (!$this->changes) {
      return;
    }
    // Reset first so a failure cannot cause the same changes to be re-sent.
    $changes = $this->changes;
    $this->changes = [];

    $webhooks = $this->entityTypeManager->getStorage('jsonapi_utils_webhook')->loadByProperties(['status' => TRUE]);
    /** @var \Drupal\jsonapi_utils\Entity\WebhookInterface $webhook */
    foreach ($webhooks as $webhook) {
      $matching = array_values(array_filter(
        $changes,
        static fn (array $change) => $webhook->isTriggeredBy($change['entity_type'], $change['bundle']),
      ));
      if ($matching) {
        $this->dispatcher->send($webhook, $matching, 'entity');
      }
    }
  }

  /**
   * Implements hook_entity_insert().
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    $this->record($entity, 'insert');
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    $this->record($entity, 'update');
  }

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    $this->record($entity, 'delete');
  }

  /**
   * Records a change for the end of the request.
   */
  protected function record(EntityInterface $entity, string $operation): void {
    // The generic entity hooks fire for config entities too.
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }
    $this->changes[] = [
      'event' => $entity->getEntityTypeId() . '.' . $operation,
      'entity_type' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
      'uuid' => $entity->uuid(),
      'nonce' => Crypt::randomBytesBase64(16),
    ];
  }

}
