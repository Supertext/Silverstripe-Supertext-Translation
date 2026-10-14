<?php

namespace Supertext\Silverstripe\Tests\unit;

use PHPUnit\Framework\TestCase;
use Supertext\Silverstripe\Api\LanguageCodes;
use Supertext\Silverstripe\Api\SupertextException;

/** No guessing: the locale's Supertext language, else an exact Supertext code, else unsupported_language. */
final class LanguageCodesTest extends TestCase
{
    public function testLocaleThatIsASupertextCodeIsUsed(): void
    {
        self::assertSame('de-CH', LanguageCodes::target('de_CH'));
        self::assertSame('en-US', LanguageCodes::target('en_US'));
        self::assertSame('fr-CH', LanguageCodes::target('fr-ch'));
        self::assertSame('es', LanguageCodes::target('es'));
        self::assertSame('zh-Hant', LanguageCodes::target('zh_hant'));
    }

    public function testNoDefaultRegions(): void
    {
        foreach (['de', 'en', 'es_ES', 'sl_SI', 'zh_CN', 'xx'] as $locale) {
            try {
                LanguageCodes::target($locale);
                self::fail("$locale should need a Supertext language");
            } catch (SupertextException $e) {
                self::assertSame('unsupported_language', $e->reason);
                self::assertSame([$locale], $e->args);
            }
            self::assertNull(LanguageCodes::targetOrNull($locale));
        }
    }

    public function testTheMappingWins(): void
    {
        $mapping = ['de' => 'de-CH', 'es_ES' => 'es', 'en_US' => 'en-GB', 'it_CH' => '  '];
        self::assertSame('de-CH', LanguageCodes::target('de', $mapping));
        self::assertSame('es', LanguageCodes::target('es_ES', $mapping));
        self::assertSame('en-GB', LanguageCodes::target('en_US', $mapping));
        self::assertSame('it-CH', LanguageCodes::target('it_CH', $mapping), 'a blank entry means no mapping');
        self::assertSame('xx-YY', LanguageCodes::target('xx', ['xx' => 'xx-YY']), 'a mapped code is sent as it is');
    }

    public function testSourcesAreBareAndUseTheSameMapping(): void
    {
        self::assertSame('en', LanguageCodes::source('en_US'));
        self::assertSame('de', LanguageCodes::source('de_CH'));
        self::assertSame('de', LanguageCodes::source('de', ['de' => 'de-CH']));
        self::assertSame('en', LanguageCodes::source('en_XX', ['en_XX' => 'en-US']));
        self::assertSame('', LanguageCodes::source(''));
        $this->expectException(SupertextException::class);
        LanguageCodes::source('xx_YY');
    }

    public function testPolitenessOnlyWhereSupertextOffersIt(): void
    {
        self::assertTrue(LanguageCodes::supportsPoliteness('de-CH'));
        self::assertFalse(LanguageCodes::supportsPoliteness('en-US'));
        self::assertFalse(LanguageCodes::supportsPoliteness('rm'));
        self::assertNull(LanguageCodes::supportsPoliteness('xx'));
    }

    public function testListsTheSupertextTargets(): void
    {
        $codes = array_column(LanguageCodes::targets(), 'code');
        self::assertContains('de-CH', $codes);
        self::assertContains('es', $codes);
        self::assertNotContains('es-ES', $codes);
        self::assertSame('de-CH', LanguageCodes::match('DE_ch'));
        self::assertNull(LanguageCodes::match('de'));
    }
}
