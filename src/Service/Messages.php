<?php

/**
 * @package     Supertext Translation for Silverstripe
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace Supertext\Silverstripe\Service;

use Supertext\Silverstripe\Api\SupertextException;

/** Shows errors from the API client (which only speaks English) in the CMS user's language. */
final class Messages
{
    public const SIGNUP_URL = 'https://www.supertext.com/person/en/account/signin';
    public const API_KEY_URL = 'https://www.supertext.com/en/integrations/api';

    public static function of(\Throwable $e): string
    {
        if (!$e instanceof SupertextException || $e->reason === '') {
            return $e->getMessage();
        }

        $links = ['signup' => self::SIGNUP_URL, 'apikey' => self::API_KEY_URL];
        $text = match ($e->reason) {
            'no_file_id' => _t(self::class . '.NO_FILE_ID', 'Supertext did not return a file id.'),
            'translation_failed' => _t(self::class . '.TRANSLATION_FAILED', 'Supertext could not translate the document.'),
            'limit_exceeded' => _t(self::class . '.LIMIT_EXCEEDED', 'Your Supertext translation limit is exceeded. Please upgrade your subscription.'),
            'deleted' => _t(self::class . '.DELETED', 'The document was deleted at Supertext before it could be downloaded.'),
            'timeout' => _t(self::class . '.TIMEOUT', 'Timed out waiting for the Supertext translation.'),
            'empty' => _t(self::class . '.EMPTY', 'The translated document was empty.'),
            'no_api_key' => self::noApiKey(),
            'unreachable' => _t(self::class . '.UNREACHABLE', 'Could not reach Supertext: {error}', ['error' => (string) ($e->args[0] ?? '')]),
            'auth_failed' => _t(self::class . '.AUTH_FAILED', 'Authentication failed. Please check the Supertext API key. No Supertext account yet? Create one at {signup}. Generate your API key at {apikey} (requires the Admin role).', $links),
            'not_found' => _t(self::class . '.NOT_FOUND', 'The requested Supertext resource was not found.'),
            'too_large' => _t(self::class . '.TOO_LARGE', 'The content is too large for Supertext to translate in one go.'),
            'rate_limited' => _t(self::class . '.RATE_LIMITED', 'Too many requests to Supertext. Please try again shortly.'),
            'unavailable' => _t(self::class . '.UNAVAILABLE', 'The Supertext service is currently unavailable.'),
            'http_error' => _t(self::class . '.HTTP_ERROR', 'Supertext answered with HTTP {status}.', ['status' => (string) ($e->args[0] ?? '')]),
            default => null,
        };

        if ($text === null) {
            return $e->getMessage();
        }

        return $e->detail !== '' ? $text . ' (' . $e->detail . ')' : $text;
    }

    public static function noApiKey(): string
    {
        return _t(
            self::class . '.NO_API_KEY',
            'No Supertext API key is configured. Set the SUPERTEXT_API_KEY environment variable. No Supertext account yet? Create one at {signup}. Generate your API key at {apikey} (requires the Admin role).',
            ['signup' => self::SIGNUP_URL, 'apikey' => self::API_KEY_URL]
        );
    }
}
