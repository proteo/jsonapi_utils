<?php

namespace Drupal\jsonapi_utils\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\jsonapi_utils\Plugin\Field\FieldType\ImageStyleDimensionsItemList;

/**
 * Field type hooks.
 */
class FieldHooks {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * Implements hook_field_info_alter().
   */
  #[Hook('field_info_alter')]
  public function fieldInfoAlter(array &$info): void {
    // The list class extends a jsonapi_image_styles class, so the module must
    // be present even when the setting is on.
    if (!$this->moduleHandler->moduleExists('jsonapi_image_styles')
      || !$this->configFactory->get('jsonapi_utils.settings')->get('include_image_style_dimensions')) {
      return;
    }
    if (isset($info['image_style_uri'])) {
      $info['image_style_uri']['list_class'] = ImageStyleDimensionsItemList::class;
    }
  }

}
