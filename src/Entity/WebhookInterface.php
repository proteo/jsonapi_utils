<?php

namespace Drupal\jsonapi_utils\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for frontend cache webhook configuration entities.
 */
interface WebhookInterface extends ConfigEntityInterface {

  /**
   * Gets the base webhook URL, with any settings.php override applied.
   */
  public function getUrl(): string;

  /**
   * Gets the shared secret, with any settings.php override applied.
   */
  public function getSecret(): string;

  /**
   * Gets the source to flush, or an empty string for the default source.
   */
  public function getSource(): string;

  /**
   * Gets the full endpoint: the base URL plus the source, when set.
   */
  public function getEndpoint(): string;

  /**
   * Gets the enabled entities, keyed by entity type ID.
   *
   * @return string[][]
   *   Bundle IDs per entity type ID.
   */
  public function getEnabledEntities(): array;

  /**
   * Checks whether changes to the given entity type and bundle trigger this.
   */
  public function isTriggeredBy(string $entity_type_id, string $bundle): bool;

}
