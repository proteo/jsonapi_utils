<?php

namespace Drupal\jsonapi_utils\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\jsonapi_utils\Form\WebhookForm;
use Drupal\jsonapi_utils\WebhookListBuilder;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;

/**
 * Defines a remote frontend cache webhook.
 */
#[ConfigEntityType(
  id: 'jsonapi_utils_webhook',
  label: new TranslatableMarkup('Frontend cache webhook'),
  label_collection: new TranslatableMarkup('Frontend cache webhooks'),
  label_singular: new TranslatableMarkup('webhook'),
  label_plural: new TranslatableMarkup('webhooks'),
  config_prefix: 'webhook',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'status' => 'status',
  ],
  handlers: [
    'list_builder' => WebhookListBuilder::class,
    'form' => [
      'add' => WebhookForm::class,
      'edit' => WebhookForm::class,
      'delete' => EntityDeleteForm::class,
    ],
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
  ],
  links: [
    'collection' => '/admin/config/services/jsonapi/utils/webhooks',
    'add-form' => '/admin/config/services/jsonapi/utils/webhooks/add',
    'edit-form' => '/admin/config/services/jsonapi/utils/webhooks/{jsonapi_utils_webhook}',
    'delete-form' => '/admin/config/services/jsonapi/utils/webhooks/{jsonapi_utils_webhook}/delete',
  ],
  admin_permission: 'manage jsonapi utilities webhooks',
  label_count: [
    'singular' => '@count webhook',
    'plural' => '@count webhooks',
  ],
  config_export: [
    'id',
    'label',
    'url',
    'secret',
    'source',
    'entities',
  ],
)]
class Webhook extends ConfigEntityBase implements WebhookInterface {

  /**
   * The machine name.
   */
  protected string $id;

  /**
   * The label.
   */
  protected string $label;

  /**
   * The base webhook URL.
   */
  protected string $url = '';

  /**
   * The shared secret used to sign requests.
   */
  protected string $secret = '';

  /**
   * The source to flush; empty for the frontend's default source.
   */
  protected string $source = '';

  /**
   * Bundle IDs per entity type ID that trigger this webhook.
   *
   * @var string[][]
   */
  protected array $entities = [];

  /**
   * {@inheritdoc}
   */
  public function getUrl(): string {
    return $this->url;
  }

  /**
   * {@inheritdoc}
   */
  public function getSecret(): string {
    return $this->secret;
  }

  /**
   * {@inheritdoc}
   */
  public function getSource(): string {
    return $this->source ?: 'default';
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpoint(): string {
    $endpoint = rtrim($this->url, '/');
    return $this->source === '' ? $endpoint : $endpoint . '/' . rawurlencode($this->source);
  }

  /**
   * {@inheritdoc}
   */
  public function getEnabledEntities(): array {
    return $this->entities;
  }

  /**
   * {@inheritdoc}
   */
  public function isTriggeredBy(string $entity_type_id, string $bundle): bool {
    return in_array($bundle, $this->entities[$entity_type_id] ?? [], TRUE);
  }

}
