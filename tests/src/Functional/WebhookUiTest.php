<?php

namespace Drupal\Tests\jsonapi_utils\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the admin UI.
 *
 * @group jsonapi_utils
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
class WebhookUiTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'jsonapi', 'jsonapi_utils', 'block'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests access, creation, sorting, disabling and deletion.
   */
  public function testWebhookCrud(): void {
    $this->drupalCreateContentType(['type' => 'page']);
    $this->drupalLogin($this->drupalCreateUser(['access administration pages']));
    $this->drupalGet('admin/config/services/jsonapi/utils/webhooks');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->drupalCreateUser(['administer jsonapi utilities', 'manage jsonapi utilities webhooks']));
    $this->drupalGet('admin/config/services/jsonapi/utils');
    $this->assertSession()->pageTextContains('Include image style dimensions');
    $this->assertSession()->checkboxNotChecked('include_image_style_dimensions');

    foreach (['Zeta' => 'zeta', 'Alpha' => 'alpha'] as $label => $id) {
      $this->drupalGet('admin/config/services/jsonapi/utils/webhooks/add');
      $this->submitForm([
        'label' => $label,
        'id' => $id,
        'url' => 'https://front.example.com/hooks/drupal',
        'secret' => 'abc',
        'entities[node][page]' => TRUE,
      ], 'Save');
    }
    $this->assertSession()->addressEquals('admin/config/services/jsonapi/utils/webhooks');
    $this->assertSession()->pageTextContains('Remote webhooks');
    $this->assertSession()->pageTextContains('Actions');
    $page = $this->getSession()->getPage()->getText();
    $this->assertLessThan(strpos($page, 'Zeta'), strpos($page, 'Alpha'));

    $this->clickLink('Disable', 0);
    $this->assertSession()->pageTextContains('Alpha (disabled)');

    // Invalid source is rejected.
    $this->drupalGet('admin/config/services/jsonapi/utils/webhooks/zeta');
    $this->submitForm(['source' => 'Bad Source'], 'Save');
    $this->assertSession()->pageTextContains('The source may only contain');

    // The stored secret is shown in the form and survives an edit.
    $this->submitForm(['source' => 'reviews'], 'Save');
    $webhook = \Drupal::entityTypeManager()->getStorage('jsonapi_utils_webhook')->load('zeta');
    $this->assertSame('abc', $webhook->getSecret());
    $this->assertSame(['page'], $webhook->getEnabledEntities()['node']);

    // A secret typed into the form is stored and shown again.
    $this->drupalGet('admin/config/services/jsonapi/utils/webhooks/zeta');
    $this->assertSession()->fieldValueEquals('secret', 'abc');
    $this->submitForm(['secret' => 'changed'], 'Save');
    $this->drupalGet('admin/config/services/jsonapi/utils/webhooks/zeta');
    $this->assertSession()->fieldValueEquals('secret', 'changed');
    $this->assertSame('changed', \Drupal::configFactory()->getEditable('jsonapi_utils.webhook.zeta')->get('secret'));

    $this->drupalGet('admin/config/services/jsonapi/utils/webhooks/zeta/delete');
    $this->submitForm([], 'Delete');
    // The confirmation message names the webhook, so check storage instead.
    $this->assertNull(\Drupal::entityTypeManager()->getStorage('jsonapi_utils_webhook')->load('zeta'));
  }

}
