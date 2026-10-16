<?php

/**
 * @package     Supertext Translation for Silverstripe
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace Supertext\Silverstripe\Api;

use Supertext\Silverstripe\PluginHealth\Http\HttpRequest;
use Supertext\Silverstripe\PluginHealth\Http\HttpResponse;
use Supertext\Silverstripe\PluginHealth\Http\HttpTransport;
use Supertext\Silverstripe\PluginHealth\Http\TransportException;
use Supertext\Silverstripe\Sdk\Http\HttpRequest as SdkHttpRequest;
use Supertext\Silverstripe\Sdk\Http\HttpTransport as SdkHttpTransport;

/**
 * Sends Supertext Plugin Health's version reports through the SDK's HttpTransport
 * (CallableTransport over Guzzle in the module, a fake in tests), so reports and Supertext
 * requests share one HTTP client.
 */
final class PluginHealthTransport implements HttpTransport
{
    public function __construct(private readonly SdkHttpTransport $transport)
    {
    }

    public function send(HttpRequest $request): HttpResponse
    {
        try {
            $response = $this->transport->send(new SdkHttpRequest($request->method, $request->url, $request->headers, $request->body, $request->timeout));
        } catch (\Throwable $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }

        return new HttpResponse($response->status, $response->body, $response->headers);
    }
}
