<?php

namespace Drupal\jsonapi_utils\EventSubscriber;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Drops caches that depend on the general settings.
 *
 * Runs on every config save, so it also applies to drush config:set and
 * config imports, not only to the settings form.
 */
class SettingsSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected CachedDiscoveryInterface $fieldTypeManager,
    protected CachedDiscoveryInterface $typedDataManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    protected CacheBackendInterface $normalizationsCache,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [ConfigEvents::SAVE => 'onSave'];
  }

  /**
   * Reacts to a saved config object.
   */
  public function onSave(ConfigCrudEvent $event): void {
    if ($event->getConfig()->getName() !== 'jsonapi_utils.settings'
      || !$event->isChanged('include_image_style_dimensions')) {
      return;
    }
    // hook_field_info_alter() runs in cached plugin discovery, and the result
    // is copied into the typed data and entity field definitions.
    $this->fieldTypeManager->clearCachedDefinitions();
    $this->typedDataManager->clearCachedDefinitions();
    $this->entityFieldManager->clearCachedFieldDefinitions();
    // Already-built JSON:API responses and normalizations still contain the
    // old values, and neither is tagged with this config.
    $this->normalizationsCache->deleteAll();
    $this->cacheTagsInvalidator->invalidateTags(['http_response', 'rendered']);
  }

}
