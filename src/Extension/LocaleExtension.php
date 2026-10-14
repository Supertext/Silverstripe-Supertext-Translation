<?php

namespace Supertext\Silverstripe\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use Supertext\Silverstripe\Api\LanguageCodes;
use TractorCow\Fluent\Model\Locale;

/**
 * Supertext language and form of address per Fluent locale (Locales admin → a locale). Together the
 * locales' Supertext languages are the language mapping (locale → Supertext code); a locale without
 * one is sent as the Supertext code equal to it (de_CH → de-CH), or has no Supertext language.
 *
 * @extends Extension<Locale>
 */
class LocaleExtension extends Extension
{
    private static array $db = [
        'SupertextCode'       => 'Varchar(20)',
        'SupertextPoliteness' => "Enum(',more,less','')",
    ];

    protected function updateCMSFields(FieldList $fields): void
    {
        $fields->removeByName(['SupertextCode', 'SupertextPoliteness']);
        $locale = (string) $this->owner->Locale;
        $current = trim((string) $this->owner->getField('SupertextCode'));
        $match = $locale !== '' ? LanguageCodes::match($locale) : null;

        // Every Supertext target, "Name (code)"; empty = the code equal to the locale, if there is one.
        $options = ['' => $match !== null
            ? _t(self::class . '.AUTO', 'Same as the locale: {code}', ['code' => $match])
            : _t(self::class . '.NONE', 'Not mapped: choose a Supertext language')];
        foreach (LanguageCodes::targets() as $target) {
            $options[$target['code']] = $target['name'] . ' (' . $target['code'] . ')';
        }
        if ($current !== '' && !isset($options[$current])) {
            // A code set before (or one Supertext added later): kept and sent as it is.
            $options[$current] = _t(self::class . '.UNKNOWN', '{code} (not in Supertext’s list)', ['code' => $current]);
        }

        $code = $current !== '' ? $current : $match;
        $politenessHelp = _t(self::class . '.POLITENESS_HELP', 'How Supertext addresses the reader in this language.');
        if ($code !== null && LanguageCodes::supportsPoliteness($code) === false) {
            $politenessHelp = _t(
                self::class . '.NO_POLITENESS',
                'Supertext offers no formal or informal tone for {code}: leave this on Default, otherwise translations into this locale fail.',
                ['code' => $code]
            );
        }

        $fields->addFieldsToTab('Root.Main', [
            DropdownField::create('SupertextCode', _t(self::class . '.CODE', 'Supertext language'), $options)
                ->setDescription(_t(
                    self::class . '.CODE_HELP',
                    'The Supertext language this locale is translated into, and from. Supertext only accepts its own codes: choose one if the locale isn\'t one of them (e.g. de → German (Switzerland), es_ES → Spanish).'
                )),
            DropdownField::create('SupertextPoliteness', _t(self::class . '.POLITENESS', 'Form of address'), [
                ''     => _t(self::class . '.DEFAULT', 'Default'),
                'more' => _t(self::class . '.FORMAL', 'Formal (Sie, vous)'),
                'less' => _t(self::class . '.INFORMAL', 'Informal (du, tu)'),
            ])->setDescription($politenessHelp),
        ]);
    }
}
