<?php

namespace Supertext\Silverstripe\Service;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\FieldType\DBHTMLVarchar;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Versioned\Versioned;
use Supertext\Silverstripe\Api\HtmlDocument;
use Supertext\Silverstripe\Api\SupertextClient;
use Supertext\Silverstripe\Api\SupertextException;
use Supertext\Silverstripe\Model\TranslationLog;
use Supertext\Silverstripe\Supertext;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

/**
 * Translates a Fluent-localised record (usually a page) from one locale into others.
 *
 * The record's localised text fields, and those of the localised objects it owns (e.g. an
 * Elemental area's blocks), are read in the source locale (draft stage), sent to Supertext
 * as one HTML document per target locale (one data-st-id element per value), and written
 * to the draft stage of the target locale. Nothing is published.
 */
class Translator
{
    use Configurable;
    use Injectable;

    /** Localised fields that are never translated. */
    private static array $exclude_fields = ['URLSegment', 'ExtraMeta', 'ReportClass', 'ExtraClass', 'Style'];

    /** How deep owned objects (area → blocks → nested blocks) are followed. */
    private static int $max_depth = 5;

    private ?SupertextClient $client = null;

    public function setClient(SupertextClient $client): static
    {
        $this->client = $client;

        return $this;
    }

    /** True if the record has content of its own in the locale (not just Fluent's fallback). */
    public static function existsIn(DataObject $record, string $locale): bool
    {
        if ($record->hasExtension(Versioned::class) && $record->hasMethod('isDraftedInLocale')) {
            return (bool) $record->isDraftedInLocale($locale);
        }

        return (bool) $record->existsInLocale($locale);
    }

    /**
     * Locales with the record's state: [['locale' => Locale, 'exists' => bool, 'last' => ?string]].
     */
    public function describe(DataObject $record): array
    {
        $last = TranslationLog::lastTranslations($record);
        $out = [];
        foreach (Locale::getCached() as $locale) {
            $out[] = [
                'locale' => $locale,
                'exists' => self::existsIn($record, $locale->Locale),
                'last'   => $last[$locale->Locale] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @param list<string> $targets locale codes (e.g. de_CH)
     *
     * @return list<array{locale: string, status: string, message: string, created: bool}>
     *
     * @throws SupertextException when nothing can be translated at all (no source, no key)
     */
    public function translate(DataObject $record, string $source, array $targets, bool $overwrite = false, ?Member $member = null): array
    {
        $sourceLocale = Locale::getByLocale($source);
        if (!$sourceLocale) {
            throw new SupertextException(_t(self::class . '.NO_LOCALE', 'Unknown locale {locale}.', ['locale' => $source]));
        }
        if (!self::existsIn($record, $source)) {
            throw new SupertextException(_t(
                self::class . '.NO_SOURCE',
                'This page has no content of its own in {locale} to translate from.',
                ['locale' => $sourceLocale->getTitle()]
            ));
        }

        $client = $this->client ?? Supertext::singleton()->client();
        if (!$client->hasApiKey()) {
            throw new SupertextException(_t(
                self::class . '.NO_KEY',
                'No Supertext API key is configured. Set the SUPERTEXT_API_KEY environment variable.'
            ));
        }

        $units = $this->inLocale($source, fn () => $this->collect($this->reload($record)));

        $results = [];
        foreach (array_unique($targets) as $target) {
            if ($target === $source) {
                continue;
            }
            $results[] = $this->translateInto($client, $record, $units, $sourceLocale, $target, $overwrite, $member);
        }

        foreach ($results as $result) {
            TranslationLog::create([
                'RecordClass'  => $record->baseClass(),
                'RecordID'     => $record->ID,
                'RecordTitle'  => mb_substr((string) $record->getTitle(), 0, 255),
                'SourceLocale' => $source,
                'TargetLocale' => $result['locale'],
                'Status'       => $result['status'],
                'Message'      => $result['message'],
                'MemberID'     => $member?->ID ?: 0,
            ])->write();
        }

        return $results;
    }

    private function translateInto(SupertextClient $client, DataObject $record, array $units, Locale $source, string $target, bool $overwrite, ?Member $member): array
    {
        $result = ['locale' => $target, 'status' => TranslationLog::TRANSLATED, 'message' => '', 'created' => false];
        $targetLocale = Locale::getByLocale($target);
        if (!$targetLocale) {
            return ['status' => TranslationLog::ERROR, 'message' => _t(self::class . '.NO_LOCALE', 'Unknown locale {locale}.', ['locale' => $target])] + $result;
        }

        $exists = self::existsIn($record, $target);
        if ($exists && !$overwrite) {
            return ['status' => TranslationLog::SKIPPED, 'message' => _t(self::class . '.SKIPPED', 'Already translated.')] + $result;
        }

        $allowed = $this->inLocale($target, function () use ($record, $member) {
            Permission::reset();
            $member = $member ?: \SilverStripe\Security\Security::getCurrentUser();

            return Permission::checkMember($member, Supertext::PERMISSION) && $this->reload($record)->canEdit($member);
        });
        Permission::reset();
        if (!$allowed) {
            return ['status' => TranslationLog::ERROR, 'message' => _t(
                self::class . '.FORBIDDEN',
                'You are not allowed to edit this page in {locale}.',
                ['locale' => $targetLocale->getTitle()]
            )] + $result;
        }

        try {
            $translations = $this->send($client, $units, $source, $targetLocale);
            $this->inLocale($target, fn () => $this->apply($record, $units, $translations, $exists));
        } catch (SupertextException $e) {
            return ['status' => TranslationLog::ERROR, 'message' => $e->getMessage()] + $result;
        } catch (\Throwable $e) {
            \SilverStripe\Core\Injector\Injector::inst()->get(\Psr\Log\LoggerInterface::class)
                ->error('Supertext translation failed: ' . $e->getMessage(), ['exception' => $e]);

            return ['status' => TranslationLog::ERROR, 'message' => $e->getMessage() ?: get_class($e)] + $result;
        }

        return ['created' => !$exists] + $result;
    }

    /**
     * The values to translate: the record's own localised text fields and those of the
     * localised objects it owns that have content of their own in the source locale.
     *
     * @return list<array{class: string, id: int, field: string, html: bool, value: string, root: bool}>
     */
    public function collect(DataObject $record): array
    {
        $units = [];
        $seen = [];
        $locale = FluentState::singleton()->getLocale();
        $walk = function (DataObject $object, int $depth, bool $root) use (&$walk, &$units, &$seen, $locale): void {
            $key = $object->baseClass() . '#' . $object->ID;
            if (isset($seen[$key]) || $depth > (int) static::config()->get('max_depth')) {
                return;
            }
            $seen[$key] = true;

            if ($object->hasExtension(FluentExtension::class) && ($root || self::existsIn($object, $locale))) {
                foreach ($this->translatableFields($object) as $field => $html) {
                    $value = trim((string) $object->getField($field));
                    if ($value === '' || ($html && trim(strip_tags($value)) === '' && !str_contains($value, '<img'))) {
                        continue;
                    }
                    $units[] = [
                        'class' => get_class($object), 'id' => (int) $object->ID, 'field' => $field,
                        'html'  => $html, 'value' => $value, 'root' => $root,
                    ];
                }
            }

            if ($object->hasMethod('findOwned')) {
                foreach ($object->findOwned(false) as $owned) {
                    if ($owned instanceof DataObject && $owned->isInDB()) {
                        $walk($owned, $depth + 1, false);
                    }
                }
            }
        };
        $walk($record, 0, true);

        return $units;
    }

    /** @return array<string, bool> field => is HTML */
    public function translatableFields(DataObject $object): array
    {
        $exclude = array_merge(
            (array) static::config()->get('exclude_fields'),
            (array) $object->config()->get('supertext_exclude_fields')
        );
        $schema = DataObject::getSchema();
        $out = [];
        foreach ($object->getLocalisedTables() as $fields) {
            foreach ($fields as $field) {
                if (in_array($field, $exclude, true)) {
                    continue;
                }
                $spec = (string) $schema->fieldSpec($object, $field, \SilverStripe\ORM\DataObjectSchema::DB_ONLY);
                $type = strtok($spec, '(');
                $class = \SilverStripe\Core\Injector\Injector::inst()->getServiceSpec($type)['class'] ?? $type;
                $out[$field] = is_a($class, DBHTMLText::class, true) || is_a($class, DBHTMLVarchar::class, true)
                    || in_array($type, ['HTMLText', 'HTMLVarchar', 'HTMLFragment'], true);
            }
        }

        return $out;
    }

    /** @return array<int, string> unit index => translation */
    private function send(SupertextClient $client, array $units, Locale $source, Locale $target): array
    {
        if ($units === []) {
            return [];
        }
        $segments = [];
        foreach ($units as $i => $unit) {
            $segments[$i] = ['text' => $unit['value'], 'html' => $unit['html']];
        }

        $sourceCode = Supertext::languageCode($source);
        $translations = [];
        foreach (HtmlDocument::chunks($segments) as $chunk) {
            $html = $client->translateDocument(
                HtmlDocument::build($chunk),
                Supertext::languageCode($target),
                strtolower(explode('-', $sourceCode)[0]),
                Supertext::politeness($target) ?: 'default'
            );
            $isHtml = array_map(static fn (array $s) => $s['html'], $chunk);
            $translations += HtmlDocument::parse($html, $isHtml);
        }

        return $translations;
    }

    /** Writes the translations to the draft stage of the current (target) locale. */
    private function apply(DataObject $record, array $units, array $translations, bool $existed): void
    {
        $byObject = [];
        foreach ($units as $i => $unit) {
            $value = trim((string) ($translations[$i] ?? ''));
            if ($value !== '') {
                $byObject[$unit['class'] . '#' . $unit['id']][$unit['field']] = $value;
            }
        }

        $rootKey = get_class($record) . '#' . $record->ID;
        $byObject += [$rootKey => []]; // the record itself is always written, so the locale exists

        // Owned objects first, the record last.
        uksort($byObject, static fn ($a, $b) => ($a === $rootKey) <=> ($b === $rootKey));

        foreach ($byObject as $key => $fields) {
            [$class, $id] = explode('#', $key);
            $object = $key === $rootKey ? $this->reload($record) : DataObject::get($class)->byID((int) $id);
            if (!$object) {
                continue;
            }
            foreach ($fields as $field => $value) {
                $object->setField($field, $value);
            }
            if ($key === $rootKey && !$existed && $object instanceof SiteTree && isset($fields['Title'])) {
                $object->URLSegment = $object->generateURLSegment($fields['Title']);
            }
            if ($object->hasExtension(Versioned::class)) {
                $object->writeToStage(Versioned::DRAFT);
            } else {
                $object->forceChange();
                $object->write();
            }
        }
    }

    /** The record as stored in the current locale's draft stage. */
    private function reload(DataObject $record): DataObject
    {
        $class = get_class($record);
        $read = static fn () => DataObject::get($class)->setUseCache(false)->byID($record->ID);
        $fresh = $record->hasExtension(Versioned::class)
            ? Versioned::withVersionedMode(static function () use ($read) {
                Versioned::set_stage(Versioned::DRAFT);

                return $read();
            })
            : $read();

        return $fresh ?: $record;
    }

    private function inLocale(string $locale, callable $callback): mixed
    {
        return FluentState::singleton()->withState(static function (FluentState $state) use ($locale, $callback) {
            $state->setLocale($locale);

            return Versioned::withVersionedMode(static function () use ($callback) {
                Versioned::set_stage(Versioned::DRAFT);

                return $callback();
            });
        });
    }
}
