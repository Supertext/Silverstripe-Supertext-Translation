<?php

/**
 * @package     Supertext Translation for Silverstripe
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace Supertext\Silverstripe\Api;

use Supertext\Silverstripe\Sdk\V1\Languages;
use Supertext\Silverstripe\Sdk\V1\SupertextException as SdkException;

/**
 * Fluent locales → Supertext language codes, with the Supertext SDK's rules (the same in every
 * Supertext plugin), without guessing.
 *
 * Supertext accepts exactly the codes of its `GET features` list (Languages::TARGETS/SOURCES):
 * bare sources ("en"); targets regional only for German, English, French, Italian and Portuguese
 * ("de-CH", "en-US"), with a script for Chinese and Serbian ("zh-Hans", "sr-Latn"), bare for the
 * others ("es", "nl"). A locale is resolved by the mapping (each locale's "Supertext language"),
 * else a Supertext code equal to the locale apart from case and "_"/"-" ("de_CH" → "de-CH"),
 * else the error unsupported_language and nothing is sent. There are no default regions.
 *
 * Has no Silverstripe dependencies.
 */
final class LanguageCodes
{
    /** @return list<array{code: string, name: string, politeness: bool}> the targets Supertext offers */
    public static function targets(): array
    {
        return array_values(Languages::TARGETS);
    }

    /**
     * Target code to send.
     *
     * @param array<string, string> $mapping locale → Supertext code (the locales' "Supertext language")
     *
     * @throws SupertextException unsupported_language when the locale is neither mapped nor a Supertext code
     */
    public static function target(string $locale, array $mapping = []): string
    {
        try {
            return Languages::target($locale, self::clean($mapping));
        } catch (SdkException $e) {
            throw SupertextClient::fromSdk($e);
        }
    }

    /**
     * Source code to send: the mapped code or the locale in its source form ("de-CH" → "de").
     *
     * @param array<string, string> $mapping locale → Supertext code
     *
     * @throws SupertextException unsupported_language
     */
    public static function source(string $locale, array $mapping = []): string
    {
        try {
            return Languages::source($locale, self::clean($mapping));
        } catch (SdkException $e) {
            throw SupertextClient::fromSdk($e);
        }
    }

    /**
     * The target code, or null when the locale has none (for the status page and the editor).
     *
     * @param array<string, string> $mapping
     */
    public static function targetOrNull(string $locale, array $mapping = []): ?string
    {
        try {
            return Languages::target($locale, self::clean($mapping));
        } catch (SdkException) {
            return null;
        }
    }

    /** The Supertext code equal to the locale (apart from case and "_"/"-"), else null: "de_CH" → "de-CH", "de" → null. */
    public static function match(string $locale): ?string
    {
        return Languages::matchTarget($locale);
    }

    /** Whether Supertext offers formal/informal for a target code; null for a code that isn't in its list. */
    public static function supportsPoliteness(string $target): ?bool
    {
        return Languages::supportsPoliteness($target);
    }

    /**
     * @param array<string, string> $mapping
     *
     * @return array<string, string> without blank entries
     */
    private static function clean(array $mapping): array
    {
        return array_filter(array_map(static fn ($code): string => trim((string) $code), $mapping), static fn (string $code): bool => $code !== '');
    }
}
