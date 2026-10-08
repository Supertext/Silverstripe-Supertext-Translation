<?php

namespace Supertext\Silverstripe\Tests\unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** lang/de.yml, fr.yml and it.yml have every string of en.yml (same placeholders), and en.yml has every _t() key of src/. */
final class LangFilesTest extends TestCase
{
    private const LANG = __DIR__ . '/../../lang';

    /** @return iterable<string, array{string}> */
    public static function languages(): iterable
    {
        foreach (['de', 'fr', 'it'] as $language) {
            yield $language => [$language];
        }
    }

    #[DataProvider('languages')]
    public function testSameStringsAsEnglish(string $language): void
    {
        $english = self::load('en');
        $strings = self::load($language);

        self::assertSame(array_keys($english), array_keys($strings));
        foreach ($english as $key => $text) {
            self::assertSame(self::placeholders($text), self::placeholders($strings[$key]), "$language $key");
        }
    }

    public function testEveryKeyUsedInTheCodeIsInEnglish(): void
    {
        $english = self::load('en');
        $used = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../../src', \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $code = (string) file_get_contents($file->getPathname());
            if (!preg_match('/^namespace ([^;]+);/m', $code, $ns) || !preg_match('/^(?:final |abstract )?class (\w+)/m', $code, $class)) {
                continue;
            }
            preg_match_all("/_t\\(\\s*self::class \\. '\\.(\\w+)'/", $code, $keys);
            foreach ($keys[1] as $key) {
                $used++;
                self::assertArrayHasKey($ns[1] . '\\' . $class[1] . '.' . $key, $english);
            }
        }
        self::assertGreaterThan(40, $used);
    }

    /** @return array<string, string> "Class.KEY" (or "Class.KEY.sub") => text, from the simple YAML the text collector writes */
    private static function load(string $language): array
    {
        $lines = file(self::LANG . "/$language.yml", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertSame("$language:", array_shift($lines));
        $out = [];
        $path = [];
        foreach ($lines as $line) {
            self::assertMatchesRegularExpression('/^( +)([^:]+(?:\\\\[^:]+)*):(?: (.*))?$/', $line);
            preg_match('/^( +)(.+?):(?: (.*))?$/', $line, $m);
            $depth = intdiv(strlen($m[1]), 2) - 1;
            $path = array_slice($path, 0, $depth);
            $path[] = $m[2];
            if (($m[3] ?? '') !== '') {
                $value = $m[3];
                if (str_starts_with($value, "'")) {
                    self::assertStringEndsWith("'", $value, $line);
                    $value = str_replace("''", "'", substr($value, 1, -1));
                }
                $out[$path[0] . '.' . implode('.', array_slice($path, 1))] = $value;
            }
        }

        return $out;
    }

    /** @return list<string> */
    private static function placeholders(string $text): array
    {
        preg_match_all('/\{[a-z]+\}|<\/?[a-z]+|https?:\/\/[^\s"<]+|SUPERTEXT_[A-Z_]+/', $text, $m);
        $found = $m[0];
        sort($found);

        return $found;
    }
}
