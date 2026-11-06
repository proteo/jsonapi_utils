<?php

namespace Drupal\Tests\jsonapi_utils\Kernel;

use Drupal\jsonapi_utils\Entity\Webhook;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that content changes flush the matching webhooks.
 *
 * @group jsonapi_utils
 */
#[RunTestsInSeparateProcesses]
class WebhookDispatchTest extends KernelTestBase {

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
   * Guzzle request history.
   */
  protected array $history = [];

  /**
   * Queue of mocked responses.
   */
  protected MockHandler $mock;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['jsonapi_utils']);
    // Swap the client before any service captures it.
    $this->mock = new MockHandler();
    $stack = HandlerStack::create($this->mock);
    $stack->push(Middleware::history($this->history));
    $this->container->set('http_client', new Client(['handler' => $stack]));
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
  }

  /**
   * Queues mocked responses.
   */
  protected function mockClient(array $responses): void {
    $this->mock->append(...$responses);
  }

  /**
   * Creates a webhook.
   */
  protected function createWebhook(array $values = []): Webhook {
    $webhook = Webhook::create($values + [
      'id' => 'front',
      'label' => 'Front',
      'status' => TRUE,
      'url' => 'https://front.example.com/hooks/drupal/',
      'secret' => 's3cret',
      'source' => '',
      'entities' => ['node' => ['page']],
    ]);
    $webhook->save();
    return $webhook;
  }

  /**
   * Signed request, correct URL, one call per request.
   */
  public function testSignedRequestSentOncePerWebhook(): void {
    $this->createWebhook(['source' => 'reviews']);
    $this->mockClient([new Response(202)]);

    Node::create(['type' => 'page', 'title' => 'One'])->save();
    Node::create(['type' => 'page', 'title' => 'Two'])->save();
    // Changes are sent when the request ends.
    $this->container->get('Drupal\jsonapi_utils\Hook\EntityHooks')->destruct();

    $this->assertCount(1, $this->history);
    $request = $this->history[0]['request'];
    $this->assertSame('https://front.example.com/hooks/drupal/reviews', (string) $request->getUri());
    $body = (string) $request->getBody();
    $this->assertCount(2, json_decode($body, TRUE)['changes']);
    $timestamp = $request->getHeaderLine('X-Drupal-Timestamp');
    $this->assertSame(hash_hmac('sha256', $timestamp . '.' . $body, 's3cret'), $request->getHeaderLine('X-Drupal-Signature'));
  }

  /**
   * Non-matching bundles and disabled webhooks send nothing.
   */
  public function testFilteringAndDisabled(): void {
    $this->createWebhook();
    $this->createWebhook(['id' => 'off', 'status' => FALSE]);
    $this->createWebhook(['id' => 'nosecret', 'secret' => '']);
    $this->mockClient([new Response(202), new Response(202)]);

    Node::create(['type' => 'article', 'title' => 'Ignored'])->save();
    $this->container->get('Drupal\jsonapi_utils\Hook\EntityHooks')->destruct();
    $this->assertCount(0, $this->history);

    Node::create(['type' => 'page', 'title' => 'Matches'])->save();
    $this->container->get('Drupal\jsonapi_utils\Hook\EntityHooks')->destruct();
    // "front" fires; "off" is disabled; "nosecret" is skipped.
    $this->assertCount(1, $this->history);
  }

  /**
   * A failing frontend never throws.
   */
  public function testFailureDoesNotThrow(): void {
    $webhook = $this->createWebhook();
    $this->mockClient([new Response(401)]);
    $this->assertFalse($this->container->get('Drupal\jsonapi_utils\WebhookDispatcher')->send($webhook, []));
  }

  /**
   * The dimensions option is off by default.
   */
  public function testImageDimensionsOffByDefault(): void {
    $this->assertFalse($this->config('jsonapi_utils.settings')->get('include_image_style_dimensions'));
  }

}
