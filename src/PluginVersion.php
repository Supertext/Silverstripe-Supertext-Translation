<?php

namespace Supertext\Silverstripe;

use Composer\InstalledVersions;

/**
 * The module's version as Composer installed it (the Git tag of the release, e.g. "0.1.0", or a
 * branch such as "dev-main"). No Silverstripe classes, so it is unit-tested on its own.
 */
final class PluginVersion
{
    public const PACKAGE = 'supertext/silverstripe-supertext-translation';

    public const RELEASES_URL = 'https://github.com/Supertext/Silverstripe-Supertext-Translation/releases/tag/';

    public static function current(): string
    {
        try {
            if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled(self::PACKAGE)) {
                $version = InstalledVersions::getPrettyVersion(self::PACKAGE);
                if (is_string($version) && $version !== '') {
                    return $version;
                }
            }
        } catch (\Throwable) {
            // Fall through: Composer's runtime data is missing or unreadable.
        }

        return 'unknown';
    }

    /** Release page for a version like "0.1.0" or "v0.1.0"; null for branches and "unknown". */
    public static function releaseUrl(string $version): ?string
    {
        if (preg_match('/^v?(\d+\.\d+\.\d+)$/', $version, $m) !== 1) {
            return null;
        }

        return self::RELEASES_URL . 'v' . $m[1];
    }
}
