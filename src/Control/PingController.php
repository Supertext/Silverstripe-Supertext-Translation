<?php

namespace Supertext\Silverstripe\Control;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Middleware\HTTPCacheControlMiddleware;
use Supertext\Silverstripe\Api\Reporting;
use Supertext\Silverstripe\Supertext;

/**
 * GET /supertext/ping?challenge=… — public (no login, no session, no CSRF token), so the Supertext
 * Commando Center can check that the site is reachable. Answers only {"ok":true,"plugin":…,
 * "challenge":…} (the SDK's pingResponse()), 400 for a bad challenge, 404 while version reports are
 * off. Never cached.
 */
class PingController extends Controller
{
    private static array $url_handlers = [
        '' => 'index',
    ];

    private static array $allowed_actions = ['index'];

    public function index(): HTTPResponse
    {
        HTTPCacheControlMiddleware::singleton()->disableCache(true);
        $request = $this->getRequest();
        if (!$request->isGET() && !$request->isHEAD()) {
            return HTTPResponse::create('', 405)->addHeader('Allow', 'GET');
        }

        $answer = Reporting::ping($request->getVar('challenge'), Supertext::singleton()->versionReportsEnabled());
        $response = HTTPResponse::create($answer->body, $answer->status);
        foreach ($answer->headers as $name => $value) {
            $response->addHeader($name, $value);
        }

        return $response;
    }
}
