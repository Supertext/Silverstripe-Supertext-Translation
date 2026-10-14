<?php

/**
 * @package     Supertext Translation for Silverstripe
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace Supertext\Silverstripe\Api;

use Supertext\Silverstripe\Sdk\Http\HttpTransport;
use Supertext\Silverstripe\Sdk\V1\ApiKeyCheck;
use Supertext\Silverstripe\Sdk\V1\InstallationReporter;
use Supertext\Silverstripe\Sdk\V1\PingResponse;
use Supertext\Silverstripe\Sdk\V1\ReporterResult;
use Supertext\Silverstripe\Sdk\V1\ReporterState;

/**
 * Version reports to the Supertext Commando Center, the automatic API key check and the callback
 * ping (Commando-Center-Supertext-Translation → docs/DEVELOPER.md → Report protocol). The protocol
 * is the SDK's InstallationReporter; this class only loads and stores its state and makes sure
 * nothing here ever throws into a request, a save or a translation.
 *
 * Has no Silverstripe dependencies: Service\Reports supplies the site URL, the versions, the
 * state store and the HTTP transport.
 */
final class Reporting
{
    /** The GitHub repository name, as the center expects it. */
    public const PLUGIN_NAME = 'Silverstripe-Supertext-Translation';

    public const SYSTEM_NAME = 'Silverstripe CMS';

    /** The ping route, relative to the site's base URL (_config/routes.yml). */
    public const PING_PATH = 'supertext/ping';

    public const ON_REQUEST = 'onRequest';
    public const TEST_CONNECTION = 'testConnection';
    public const AUTHENTICATION_FAILED = 'authenticationFailed';
    public const REPORT_CHECK = 'reportCheck';

    /**
     * @param bool                         $enabled   InstallationReporter::reportsEnabled() of the setting and the environment
     * @param string                       $siteUrl   the site's base URL
     * @param string|null                  $reportUrl where reports go (SUPERTEXT_REPORT_URL); null: the SDK's default
     * @param (callable(float): void)|null $sleep     tests: the key check's 429 waits
     */
    public function __construct(
        private readonly StateStore $store,
        public readonly bool $enabled,
        private readonly string $siteUrl,
        private readonly string $pluginVersion,
        private readonly ?string $systemVersion,
        private readonly string $apiBaseUrl,
        private readonly ?HttpTransport $transport = null,
        private readonly ?string $reportUrl = null,
        private $sleep = null,
    ) {
    }

    /** The absolute URL of the ping route for a base URL ("https://example.com/" → "https://example.com/supertext/ping"). */
    public static function callbackUrl(string $siteUrl): string
    {
        return rtrim($siteUrl, '/') . '/' . self::PING_PATH;
    }

    /** The ping route's answer: 200 with the challenge, 400 for a bad one, 404 while reports are off. */
    public static function ping(mixed $challenge, bool $enabled): PingResponse
    {
        return InstallationReporter::pingResponse(\is_string($challenge) ? $challenge : null, self::PLUGIN_NAME, $enabled);
    }

    public function reporter(): InstallationReporter
    {
        return new InstallationReporter(
            enabled: $this->enabled,
            siteUrl: $this->siteUrl,
            pluginName: self::PLUGIN_NAME,
            pluginVersion: $this->pluginVersion,
            systemName: self::SYSTEM_NAME,
            systemVersion: $this->systemVersion,
            callbackUrl: self::callbackUrl($this->siteUrl),
            reportUrl: $this->reportUrl,
            transport: $this->transport,
            apiBaseUrl: $this->apiBaseUrl,
            sleep: $this->sleep,
        );
    }

    /**
     * Runs one reporter action with the stored state and stores the new state. Never throws: null
     * if something failed (the state store, an unexpected error).
     *
     * @param string $action one of the constants ON_REQUEST, TEST_CONNECTION, AUTHENTICATION_FAILED, REPORT_CHECK
     */
    public function run(string $action, #[\SensitiveParameter] string $apiKey, int $httpStatus = 401): ?ReporterResult
    {
        try {
            $reporter = $this->reporter();
            $stored = $this->store->load();
            $state = ReporterState::from($stored);
            $result = match ($action) {
                self::TEST_CONNECTION => $reporter->testConnection($state, $apiKey),
                self::AUTHENTICATION_FAILED => $reporter->authenticationFailed($state, $apiKey, $httpStatus),
                self::REPORT_CHECK => $reporter->reportCheck($state),
                default => $reporter->onRequest($state, $apiKey),
            };
            $json = json_encode($result->state, JSON_UNESCAPED_SLASHES);
            if ($json !== false && $json !== $stored) {
                $this->store->save($json);
            }

            return $result;
        } catch (\Throwable) {
            return null;
        }
    }

    /** The latest stored key check (for the Supertext section), null if none or unreadable. */
    public function lastCheck(): ?ApiKeyCheck
    {
        try {
            return ReporterState::from($this->store->load())->keyCheck;
        } catch (\Throwable) {
            return null;
        }
    }
}
