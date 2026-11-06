<?php

namespace Drupal\jsonapi_utils\Plugin\Field\FieldType;

use Drupal\jsonapi_image_styles\Plugin\Field\FieldType\ImageStyleNormalizedFieldItemList;
use Drupal\image\Entity\ImageStyle;

/**
 * Exposes width and height next to the URL of each jsonapi_image_styles style.
 */
class ImageStyleDimensionsItemList extends ImageStyleNormalizedFieldItemList {

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    parent::computeValue();
    if (!isset($this->list[0])) {
      return;
    }

    $uri = $this->getEntity()->getFileUri();
    $image = \Drupal::service('image.factory')->get($uri);
    if (!$image->isValid()) {
      return;
    }

    $extended = [];
    foreach ($this->list[0]->getValue() as $style_name => $url) {
      // Run the original dimensions through the style's effects chain.
      $dimensions = ['width' => $image->getWidth(), 'height' => $image->getHeight()];
      ImageStyle::load($style_name)?->transformDimensions($dimensions, $uri);
      $extended[$style_name] = $dimensions + ['url' => $url];
    }
    $this->list[0]->setValue($extended);
  }

}
