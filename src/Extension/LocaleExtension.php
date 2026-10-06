<?php

namespace Supertext\Silverstripe\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use TractorCow\Fluent\Model\Locale;

/**
 * Supertext language and form of address per Fluent locale (Locales admin → a locale).
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
        $code = str_replace('_', '-', (string) $this->owner->Locale);
        $fields->addFieldsToTab('Root.Main', [
            TextField::create('SupertextCode', _t(self::class . '.CODE', 'Supertext language'))
                ->setAttribute('placeholder', $code)
                ->setDescription(_t(
                    self::class . '.CODE_HELP',
                    'Language code sent to Supertext. Leave empty to use {code}.',
                    ['code' => $code ?: 'the locale (de_CH → de-CH)']
                )),
            DropdownField::create('SupertextPoliteness', _t(self::class . '.POLITENESS', 'Form of address'), [
                ''     => _t(self::class . '.DEFAULT', 'Default'),
                'more' => _t(self::class . '.FORMAL', 'Formal (Sie, vous)'),
                'less' => _t(self::class . '.INFORMAL', 'Informal (du, tu)'),
            ])->setDescription(_t(self::class . '.POLITENESS_HELP', 'How Supertext addresses the reader in this language.')),
        ]);
    }
}
