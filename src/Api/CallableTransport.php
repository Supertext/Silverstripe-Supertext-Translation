<?php

/**
 * @package     Supertext Translation for Silverstripe
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace Supertext\Silverstripe\Api;

use Supertext\Silverstripe\Sdk\Http\HttpRequest;
use Supertext\Silverstripe\Sdk\Http\HttpResponse;
use Supertext\Silverstripe\Sdk\Http\HttpTransport;
use Supertext\Silverstripe\Sdk\Http\TransportException;

/**
 * Sends the Supertext SDK's requests through a callable: Guzzle in the module,
 * a fake in tests. The callable gets the request's timeout in seconds as fifth argument
 * (the version report has 3 s, the key check 10 s). Every HTTP answer, 4xx and
 * 5xx included, goes back to the SDK; only a missing answer is an exception.
 */
final class CallableTransport implements HttpTransport
{
    /** @var callable(string, string, array<string, string>, ?string, float): array{status: int, body: string, headers?: array<string, string>} */
    private $send;

    /** @param callable(string $method, string $url, array<string, string> $headers, ?string $body, float $timeout): array{status: int, body: string, headers?: array<string, string>} $send */
    public function __construct(callable $send)
    {
        $this->send = $send;
    }

    public function send(HttpRequest $request): HttpResponse
    {
        try {
            $response = ($this->send)($request->method, $request->url, $request->headers, $request->body, $request->timeout);
        } catch (\Throwable $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        return new HttpResponse($response['status'], $response['body'], $response['headers'] ?? []);
    }
}
