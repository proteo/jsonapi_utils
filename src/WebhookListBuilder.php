<?php

namespace Drupal\jsonapi_utils;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\jsonapi_utils\Entity\WebhookInterface;

/**
 * Lists frontend cache webhooks.
 */
class WebhookListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entities = parent::load();
    uasort($entities, static fn (EntityInterface $a, EntityInterface $b) => strnatcasecmp((string) $a->label(), (string) $b->label()));
    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['table']['#attributes']['class'][] = 'jsonapi-utils-webhooks';
    $build['table']['#attached']['library'][] = 'jsonapi_utils/webhook_list';
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    return [
      'label' => $this->t('Remote webhooks'),
      'operations' => $this->t('Actions'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\jsonapi_utils\Entity\WebhookInterface $entity */
    $label = $entity->label();
    if (!$entity->status()) {
      $label = $this->t('@label (disabled)', ['@label' => $label]);
    }
    return [
      'label' => $label,
      'operations' => ['data' => $this->buildOperations($entity)],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity, ?CacheableMetadata $cacheability = NULL) {
    $operations = parent::getDefaultOperations($entity, $cacheability);
    $parameters = ['jsonapi_utils_webhook' => $entity->id()];
    if ($entity instanceof WebhookInterface && $entity->status()) {
      $operations['disable'] = [
        'title' => $this->t('Disable'),
        'weight' => 20,
        'url' => Url::fromRoute('jsonapi_utils.webhook.disable', $parameters),
      ];
      $operations['flush'] = [
        'title' => $this->t('Flush now'),
        'weight' => 30,
        'url' => Url::fromRoute('jsonapi_utils.webhook.flush', $parameters),
      ];
    }
    else {
      $operations['enable'] = [
        'title' => $this->t('Enable'),
        'weight' => 20,
        'url' => Url::fromRoute('jsonapi_utils.webhook.enable', $parameters),
      ];
    }
    return $operations;
  }

}
