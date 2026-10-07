<?php

namespace Supertext\Silverstripe\Tests\unit;

use PHPUnit\Framework\TestCase;
use Supertext\Silverstripe\PluginVersion;

class PluginVersionTest extends TestCase
{
    public function testReleaseVersionsLinkToTheirGitHubRelease(): void
    {
        $url = 'https://github.com/Supertext/Silverstripe-Supertext-Translation/releases/tag/v0.1.0';
        self::assertSame($url, PluginVersion::releaseUrl('0.1.0'));
        self::assertSame($url, PluginVersion::releaseUrl('v0.1.0'));
    }

    public function testOtherVersionsHaveNoLink(): void
    {
        foreach (['dev-main', 'unknown', '0.1.0-beta1', '1.2', 'v1.2.3.4', ''] as $version) {
            self::assertNull(PluginVersion::releaseUrl($version), $version);
        }
    }

    public function testCurrentNeverReturnsAnEmptyString(): void
    {
        self::assertNotSame('', PluginVersion::current());
    }
}
