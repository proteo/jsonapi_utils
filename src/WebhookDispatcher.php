<?php

namespace Drupal\jsonapi_utils;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\jsonapi_utils\Entity\WebhookInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

/**
 * Sends signed cache-flush requests to remote frontends.
 */
class WebhookDispatcher {

  /**
   * Logger channel.
   */
  protected LoggerInterface $logger;

  public function __construct(
    protected ClientInterface $httpClient,
    protected TimeInterface $time,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('jsonapi_utils');
  }

  /**
   * Sends one flush request to a webhook.
   *
   * @param \Drupal\jsonapi_utils\Entity\WebhookInterface $webhook
   *   The webhook.
   * @param array $changes
   *   The changes to include in the body. The frontend ignores the body, but it
   *   keeps each signed request unique and traceable.
   * @param string $trigger
   *   What caused the request: "entity" or "manual".
   *
   * @return bool
   *   TRUE when the frontend accepted the request.
   */
  public function send(WebhookInterface $webhook, array $changes, string $trigger = 'entity'): bool {
    $secret = $webhook->getSecret();
    if ($webhook->getUrl() === '' || $secret === '') {
      $this->logger->warning('Webhook @id skipped: no URL or secret is configured.', ['@id' => $webhook->id()]);
      return FALSE;
    }

    $endpoint = $webhook->getEndpoint();
    $body = Json::encode(['changes' => $changes]);
    $timestamp = (string) $this->time->getCurrentTime();
    $signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    try {
      $response = $this->httpClient->request('POST', $endpoint, [
        // Must be the exact bytes that were signed.
        'body' => $body,
        'headers' => [
          'Content-Type' => 'application/json',
          'X-Drupal-Timestamp' => $timestamp,
          'X-Drupal-Signature' => $signature,
        ],
        'timeout' => 5,
      ]);
      $this->logger->info('Webhook @id (@trigger) flushed @url: HTTP @status.', [
        '@id' => $webhook->id(),
        '@trigger' => $trigger,
        '@url' => $endpoint,
        '@status' => $response->getStatusCode(),
      ]);
      return TRUE;
    }
    catch (\Throwable $e) {
      // Never break content editing because the frontend is unreachable.
      $status = $e instanceof RequestException && $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'n/a';
      $this->logger->error('Webhook @id (@trigger) failed for @url: HTTP @status, @message', [
        '@id' => $webhook->id(),
        '@trigger' => $trigger,
        '@url' => $endpoint,
        '@status' => $status,
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

}
