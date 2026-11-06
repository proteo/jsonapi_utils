<?php

namespace Drupal\Tests\jsonapi_utils\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\jsonapi_utils\Entity\Webhook;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests how the webhook form handles settings.php overrides.
 *
 * @group jsonapi_utils
 */
#[RunTestsInSeparateProcesses]
class WebhookFormOverrideTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
    'image',
    'file',
    'serialization',
    'jsonapi',
    'jsonapi_utils',
  ];

  /**
   * Builds the edit form for the webhook.
   */
  protected function buildForm(): array {
    $webhook = \Drupal::entityTypeManager()->getStorage('jsonapi_utils_webhook')->load('t');
    $form_object = \Drupal::entityTypeManager()->getFormObject('jsonapi_utils_webhook', 'edit');
    $form_object->setEntity($webhook);
    return \Drupal::formBuilder()->buildForm($form_object, new FormState());
  }

  /**
   * Stored secrets are editable; overridden ones are disabled but visible.
   */
  public function testSecretVisibility(): void {
    $this->installConfig(['jsonapi_utils']);
    Webhook::create(['id' => 't', 'label' => 'T', 'url' => 'https://a.example.com', 'secret' => 'stored'])->save();

    $form = $this->buildForm();
    $this->assertSame('stored', $form['connection']['secret']['#default_value']);
    $this->assertEmpty($form['connection']['secret']['#disabled']);

    $GLOBALS['config']['jsonapi_utils.webhook.t']['secret'] = 'from-settings';
    \Drupal::configFactory()->reset();
    $form = $this->buildForm();
    $this->assertSame('from-settings', $form['connection']['secret']['#default_value']);
    $this->assertTrue($form['connection']['secret']['#disabled']);
    unset($GLOBALS['config']['jsonapi_utils.webhook.t']);
  }

}
