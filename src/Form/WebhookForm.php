<?php

namespace Drupal\jsonapi_utils\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\jsonapi_utils\Entity\Webhook;
use Drupal\jsonapi_utils\Entity\WebhookInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Add/edit form for frontend cache webhooks.
 */
class WebhookForm extends EntityForm {

  public function __construct(
    protected EntityTypeBundleInfoInterface $bundleInfo,
    protected EntityTypeManagerInterface $entityTypeManagerService,
    protected ConfigFactoryInterface $configFactoryService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\jsonapi_utils\Entity\WebhookInterface $webhook */
    $webhook = $this->entity;
    $overridden = $this->getOverriddenKeys();

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $webhook->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $webhook->id(),
      '#machine_name' => ['exists' => [Webhook::class, 'load']],
      '#disabled' => !$webhook->isNew(),
    ];
    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#description' => $this->t('Disabled webhooks never send requests.'),
      '#default_value' => $webhook->isNew() ? TRUE : $webhook->status(),
    ];

    $form['connection'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Connection details'),
    ];
    $form['connection']['url'] = [
      '#type' => 'url',
      '#title' => $this->t('Remote webhook URL'),
      '#description' => $this->t('Base endpoint of the frontend webhook, for example https://frontend.example.com/hooks/drupal. When a source is set it is appended to this URL. Can be overridden per environment in settings.php.'),
      '#attributes' => ['placeholder' => 'https://'],
      '#maxlength' => 2048,
      '#default_value' => $webhook->getUrl(),
      '#required' => !in_array('url', $overridden, TRUE),
      '#disabled' => in_array('url', $overridden, TRUE),
    ];
    $form['connection']['secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Remote webhook secret'),
      '#description' => $this->t('Used to sign each request (HMAC-SHA256). Optional, but no requests are sent while it is empty. Can be overridden per environment in settings.php.'),
      '#maxlength' => 255,
      '#default_value' => $webhook->getSecret(),
      '#disabled' => in_array('secret', $overridden, TRUE),
      '#attributes' => ['autocomplete' => 'off'],
    ];
    if (in_array('secret', $overridden, TRUE)) {
      $form['connection']['secret']['#description'] = $this->t('Overridden in settings.php.');
    }
    if (in_array('url', $overridden, TRUE)) {
      $form['connection']['url']['#description'] = $this->t('Overridden in settings.php.');
    }
    $form['connection']['source'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Source'),
      '#description' => $this->t('The named source to flush, for example "reviews". Leave empty to flush all the sources.'),
      '#maxlength' => 64,
      '#default_value' => $webhook->getSource(),
    ];

    $form['enabled_entities'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Enabled entities'),
      '#description' => $this->t('Creating, updating or deleting an item of a selected bundle sends a request to this webhook.'),
      '#attached' => ['library' => ['jsonapi_utils/webhook_form']],
    ];
    $form['enabled_entities']['entities_table'] = $this->buildEntitiesTable($webhook->getEnabledEntities());

    return $form;
  }

  /**
   * Builds the entity type/bundle table.
   */
  protected function buildEntitiesTable(array $enabled): array {
    $table = [
      '#type' => 'table',
      '#header' => [$this->t('Entity type / bundle'), $this->t('Enabled')],
    ];
    $definitions = array_filter(
      $this->entityTypeManagerService->getDefinitions(),
      static fn ($definition) => $definition instanceof ContentEntityTypeInterface,
    );
    uasort($definitions, static fn ($a, $b) => strnatcasecmp((string) $a->getLabel(), (string) $b->getLabel()));

    foreach ($definitions as $type_id => $definition) {
      $bundles = $this->bundleInfo->getBundleInfo($type_id);
      uasort($bundles, static fn ($a, $b) => strnatcasecmp((string) $a['label'], (string) $b['label']));

      $table[$type_id . '__group'] = [
        '#attributes' => ['class' => ['jsonapi-utils-group']],
        'name' => ['#markup' => '<strong>' . $definition->getLabel() . '</strong>'],
        'select_all' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Select all @type', ['@type' => $definition->getLabel()]),
          '#title_display' => 'invisible',
          '#parents' => ['select_all', $type_id],
          '#attributes' => ['class' => ['jsonapi-utils-select-all'], 'data-group' => $type_id],
        ],
      ];
      foreach ($bundles as $bundle => $info) {
        $table[$type_id . '__' . $bundle] = [
          'name' => ['#markup' => $info['label']],
          'enabled' => [
            '#type' => 'checkbox',
            '#title' => $this->t('@bundle (@type)', ['@bundle' => $info['label'], '@type' => $definition->getLabel()]),
            '#title_display' => 'invisible',
            '#parents' => ['entities', $type_id, $bundle],
            '#default_value' => in_array((string) $bundle, $enabled[$type_id] ?? [], TRUE),
            '#attributes' => ['class' => ['jsonapi-utils-bundle'], 'data-group' => $type_id],
          ],
        ];
      }
    }
    return $table;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $url = (string) $form_state->getValue('url');
    if ($url !== '' && !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], TRUE)) {
      $form_state->setErrorByName('url', $this->t('The webhook URL must start with http:// or https://.'));
    }
    $source = (string) $form_state->getValue('source');
    if ($source !== '' && !preg_match('/^[a-z0-9_-]+$/', $source)) {
      $form_state->setErrorByName('source', $this->t('The source may only contain lowercase letters, numbers, underscores and hyphens.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state): void {
    parent::copyFormValuesToEntity($entity, $form, $form_state);
    if (!$entity instanceof WebhookInterface) {
      return;
    }

    // This runs on validate and on submit, so form state must stay untouched.
    $entities = [];
    foreach ($form_state->getValue('entities') ?? [] as $type_id => $bundles) {
      if ($selected = array_keys(array_filter($bundles))) {
        $entities[$type_id] = array_map('strval', $selected);
      }
    }
    $entity->set('entities', $entities);

    // Never persist a settings.php override into the exported config.
    if (!$this->entity->isNew()) {
      $original = $this->getStorage()->loadOverrideFree($this->entity->id());
      assert($original instanceof WebhookInterface);
      foreach ($this->getOverriddenKeys() as $key) {
        $entity->set($key, $original->get($key));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $this->messenger()->addStatus($result === SAVED_NEW
      ? $this->t('Created webhook %label.', ['%label' => $this->entity->label()])
      : $this->t('Saved webhook %label.', ['%label' => $this->entity->label()]));
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

  /**
   * Gets the storage for this entity type.
   */
  protected function getStorage(): ConfigEntityStorageInterface {
    $storage = $this->entityTypeManagerService->getStorage('jsonapi_utils_webhook');
    if (!$storage instanceof ConfigEntityStorageInterface) {
      throw new \LogicException('The webhook entity type must use config entity storage.');
    }
    return $storage;
  }

  /**
   * Lists the properties whose runtime value differs from the stored one.
   *
   * @return string[]
   *   Property names overridden through $config in settings.php.
   */
  protected function getOverriddenKeys(): array {
    if ($this->entity->isNew()) {
      return [];
    }
    $original = $this->getStorage()->loadOverrideFree($this->entity->id());
    if (!$original instanceof WebhookInterface) {
      return [];
    }
    // Compare against the runtime config, not $this->entity: EntityForm copies
    // submitted values onto the entity during validation, which would make
    // every edited property look overridden.
    $runtime = $this->configFactoryService->get($this->entity->getConfigDependencyName());
    return array_values(array_filter(
      ['url', 'secret'],
      fn (string $key) => $original->get($key) !== $runtime->get($key),
    ));
  }

}
