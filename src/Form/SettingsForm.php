<?php

namespace Drupal\jsonapi_utils\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * General settings for JSON:API Utilities.
 *
 * To add an option: add a key to config/install and the schema, then an
 * element in buildForm() and its value in submitForm().
 */
class SettingsForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $configFactory,
    TypedConfigManagerInterface $typedConfigManager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($configFactory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'jsonapi_utils_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['jsonapi_utils.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('jsonapi_utils.settings');
    $has_image_styles = $this->moduleHandler->moduleExists('jsonapi_image_styles');

    $form['include_image_style_dimensions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include image style dimensions'),
      '#description' => $this->t('Enable to include width and height values for styled images served as part of JSON:API resources'),
      '#default_value' => $has_image_styles && $config->get('include_image_style_dimensions'),
      '#disabled' => !$has_image_styles,
    ];
    if (!$has_image_styles) {
      $form['include_image_style_dimensions']['#description'] .= '<br>' . $this->t('This option requires the JSON:API Image Styles module, which is not installed.');
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('jsonapi_utils.settings');
    // A disabled checkbox must not overwrite the stored value.
    if ($this->moduleHandler->moduleExists('jsonapi_image_styles')) {
      $config->set('include_image_style_dimensions', (bool) $form_state->getValue('include_image_style_dimensions'));
    }
    $config->save();
    $this->logger('jsonapi_utils')->notice('Settings updated by @user.', ['@user' => $this->currentUser()->getAccountName()]);
    parent::submitForm($form, $form_state);
  }

}
