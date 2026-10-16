<?php

declare(strict_types=1);

namespace Scell\Sdk\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Scell\Sdk\Http\RetryMiddleware;

/**
 * Verifie la politique de retry : erreurs reseau sans reponse (PSR-18
 * NetworkExceptionInterface, valable pour Guzzle 7 ET 8) et codes HTTP retryables.
 */
class RetryMiddlewareTest extends TestCase
{
    private function clientWith(MockHandler $mock): Client
    {
        $stack = HandlerStack::create($mock);
        $stack->push(RetryMiddleware::create(maxRetries: 3, baseDelay: 1));

        return new Client(['handler' => $stack, 'http_errors' => false]);
    }

    private function networkError(): \Throwable
    {
        $request = new Request('GET', 'https://api.example.test/ping');

        return new class ('timeout', $request) extends \RuntimeException implements NetworkExceptionInterface {
            public function __construct(string $message, private RequestInterface $request)
            {
                parent::__construct($message);
            }

            public function getRequest(): RequestInterface
            {
                return $this->request;
            }
        };
    }

    #[Test]
    public function it_retries_a_get_after_a_network_exception(): void
    {
        $mock = new MockHandler([$this->networkError(), new Response(200, [], 'ok')]);

        $response = $this->clientWith($mock)->request('GET', 'https://api.example.test/ping');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(0, $mock, 'both queued results must have been consumed');
    }

    #[Test]
    public function it_retries_a_post_after_a_network_exception(): void
    {
        $mock = new MockHandler([$this->networkError(), new Response(201, [], 'created')]);

        $response = $this->clientWith($mock)->request('POST', 'https://api.example.test/items');

        $this->assertSame(201, $response->getStatusCode());
    }

    #[Test]
    public function it_gives_up_after_the_maximum_number_of_retries(): void
    {
        $mock = new MockHandler([
            $this->networkError(),
            $this->networkError(),
            $this->networkError(),
            $this->networkError(),
        ]);

        $this->expectException(\Throwable::class);

        try {
            $this->clientWith($mock)->request('GET', 'https://api.example.test/ping');
        } finally {
            $this->assertCount(0, $mock, 'initial attempt + 3 retries');
        }
    }

    #[Test]
    public function it_retries_idempotent_requests_on_503(): void
    {
        $mock = new MockHandler([new Response(503), new Response(200, [], 'ok')]);

        $response = $this->clientWith($mock)->request('GET', 'https://api.example.test/ping');

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function it_does_not_retry_client_errors(): void
    {
        $mock = new MockHandler([new Response(422), new Response(200)]);

        $response = $this->clientWith($mock)->request('GET', 'https://api.example.test/ping');

        $this->assertSame(422, $response->getStatusCode());
        $this->assertCount(1, $mock);
    }
}
