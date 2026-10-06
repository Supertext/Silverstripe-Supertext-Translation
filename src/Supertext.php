<?php

namespace Supertext\Silverstripe;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Security\PermissionProvider;
use Supertext\Silverstripe\Api\SupertextClient;
use Supertext\Silverstripe\Api\SupertextException;
use TractorCow\Fluent\Model\Locale;

/**
 * Settings (YAML config plus environment variables) and the permission.
 *
 *   Supertext\Silverstripe\Supertext:
 *     environment: live        # live | staging | testing
 *     api_url: ''              # custom base URL; SUPERTEXT_API_URL wins
 *     timeout: 180             # seconds per locale
 *
 * The API key comes from the SUPERTEXT_API_KEY environment variable.
 */
class Supertext implements PermissionProvider
{
    use Configurable;
    use Injectable;

    public const PERMISSION = 'SUPERTEXT_TRANSLATE';

    private static string $environment = 'live';

    private static string $api_url = '';

    private static int $timeout = 180;

    private static float $poll_interval = 2.0;

    public function apiKey(): string
    {
        return SupertextClient::normalizeKey((string) Environment::getEnv('SUPERTEXT_API_KEY'));
    }

    public function baseUrl(): string
    {
        $url = (string) (Environment::getEnv('SUPERTEXT_API_URL') ?: static::config()->get('api_url'));

        return SupertextClient::baseUrlFor((string) static::config()->get('environment'), $url);
    }

    public function timeout(): int
    {
        return max(10, (int) static::config()->get('timeout'));
    }

    /** Supertext language for a Fluent locale: the locale's override, else BCP-47 (de_CH -> de-CH). */
    public static function languageCode(Locale $locale): string
    {
        $override = trim((string) $locale->getField('SupertextCode'));

        return $override !== '' ? $override : str_replace('_', '-', (string) $locale->Locale);
    }

    /** "more" (formal), "less" (informal) or "" (default). */
    public static function politeness(Locale $locale): string
    {
        $value = (string) $locale->getField('SupertextPoliteness');

        return in_array($value, ['more', 'less'], true) ? $value : '';
    }

    public function client(): SupertextClient
    {
        $http = new Client(['http_errors' => false, 'timeout' => 60, 'connect_timeout' => 15]);
        $transport = static function (string $method, string $url, array $headers, ?string $body) use ($http): array {
            try {
                $response = $http->request($method, $url, ['headers' => $headers, 'body' => $body]);
            } catch (GuzzleException $e) {
                throw new SupertextException(
                    _t(self::class . '.UNREACHABLE', 'The Supertext service could not be reached.') . ' ' . $e->getMessage()
                );
            }
            $out = [];
            foreach ($response->getHeaders() as $name => $values) {
                $out[strtolower($name)] = implode(', ', $values);
            }

            return ['status' => $response->getStatusCode(), 'body' => (string) $response->getBody(), 'headers' => $out];
        };

        return new SupertextClient(
            $this->apiKey(),
            $this->baseUrl(),
            $transport,
            $this->timeout(),
            (float) static::config()->get('poll_interval')
        );
    }

    public function providePermissions(): array
    {
        return [
            self::PERMISSION => [
                'name'     => _t(self::class . '.PERMISSION', 'Translate content with Supertext'),
                'help'     => _t(
                    self::class . '.PERMISSION_HELP',
                    'Send pages to Supertext and save the translations in other locales. Editing the page and the locales is still required.'
                ),
                'category' => _t(self::class . '.PERMISSION_CATEGORY', 'Supertext'),
                'sort'     => 100,
            ],
        ];
    }
}
