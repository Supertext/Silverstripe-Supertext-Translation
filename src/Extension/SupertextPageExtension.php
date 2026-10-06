<?php

namespace Supertext\Silverstripe\Extension;

use SilverStripe\Core\Convert;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Permission;
use SilverStripe\View\Requirements;
use Supertext\Silverstripe\Service\Translator;
use Supertext\Silverstripe\Supertext;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Model\Locale;

/**
 * The "Supertext" tab on pages (and other Fluent-localised records that use it): translate
 * the current locale's saved content into other locales.
 *
 * @extends Extension<DataObject>
 */
class SupertextPageExtension extends Extension
{
    protected function updateCMSFields(FieldList $fields): void
    {
        $record = $this->owner;
        if (!$record->hasExtension(FluentExtension::class) || !$record->isInDB() || !Permission::check(Supertext::PERMISSION)) {
            return;
        }
        $current = Locale::getCurrentLocale();
        if (!$current || Locale::getCached()->count() < 2) {
            return;
        }

        Requirements::javascript('supertext/silverstripe-supertext-translation:client/supertext.js');
        Requirements::css('supertext/silverstripe-supertext-translation:client/supertext.css');

        $tab = 'Root.Supertext';
        $fields->findOrMakeTab($tab, _t(self::class . '.TAB', 'Supertext'));

        $from = Convert::raw2xml($current->getTitle());
        if (!Translator::existsIn($record, $current->Locale)) {
            $fields->addFieldToTab($tab, LiteralField::create('SupertextNoSource', sprintf(
                '<p class="alert alert-info">%s</p>',
                _t(
                    self::class . '.NO_SOURCE',
                    'This page has no content of its own in {locale} yet. Switch to a locale it was written in to translate it from there.',
                    ['locale' => $from]
                )
            )));

            return;
        }

        $options = [];
        $default = [];
        $existing = [];
        foreach (Translator::singleton()->describe($record) as $item) {
            /** @var Locale $locale */
            $locale = $item['locale'];
            if ($locale->Locale === $current->Locale) {
                continue;
            }
            $label = Convert::raw2xml($locale->getTitle()) . ' <code>' . Convert::raw2xml($locale->Locale) . '</code>';
            if ($item['last']) {
                $date = DBDatetime::create()->setValue($item['last'])->Date();
                $label .= ' <span class="supertext-chip">' . _t(self::class . '.LAST', 'Translated with Supertext on {date}', ['date' => $date]) . '</span>';
            } elseif ($item['exists']) {
                $label .= ' <span class="supertext-chip">' . _t(self::class . '.EXISTS', 'Already translated') . '</span>';
            }
            $options[$locale->Locale] = DBField::create_field('HTMLFragment', $label);
            if ($item['exists']) {
                $existing[] = $locale->Locale;
            } else {
                $default[] = $locale->Locale;
            }
        }

        $configured = Supertext::singleton()->apiKey() !== '';

        $fields->addFieldsToTab($tab, [
            LiteralField::create('SupertextIntro', sprintf(
                '<div class="supertext-intro"><h3>%s</h3><p>%s</p>%s</div>',
                _t(self::class . '.TITLE', 'Translate with Supertext'),
                _t(self::class . '.FROM', 'From <strong>{locale}</strong>, the locale you are editing.', ['locale' => $from]),
                $configured ? '' : '<p class="alert alert-warning">' . _t(
                    self::class . '.NOT_CONFIGURED',
                    'Supertext is not set up yet: an administrator needs to set SUPERTEXT_API_KEY.'
                ) . '</p>'
            )),
            CheckboxSetField::create('SupertextTargets', _t(self::class . '.INTO', 'Into'), $options, $default)
                ->addExtraClass('supertext-targets no-change-track')
                ->setAttribute('data-existing', json_encode($existing)),
            CheckboxField::create('SupertextOverwrite', _t(self::class . '.OVERWRITE', 'Overwrite existing translations'))
                ->addExtraClass('supertext-overwrite no-change-track')
                ->setDescription(_t(
                    self::class . '.OVERWRITE_HELP',
                    'The selected locales that already have their own content get a new translation of {locale}: page fields and content blocks. Changes made in those locales are lost. Leave this off to translate only the missing locales.',
                    ['locale' => $from]
                )),
            LiteralField::create('SupertextHint', '<p class="supertext-hint">' . _t(
                self::class . '.HINT',
                'Supertext translates the saved draft of this locale: save your changes first. The translations are saved as drafts; review and publish them in each locale.'
            ) . '</p>'),
            FormAction::create('doSupertextTranslate', _t(self::class . '.BUTTON', 'Translate'))
                ->addExtraClass('btn-primary supertext-translate font-icon-translatable')
                ->setUseButtonTag(true)
                ->setAttribute('data-busy', _t(self::class . '.BUSY', 'Translating…')),
        ]);
    }

    /** The tab's fields aren't saved into the record. */
    public function saveSupertextTargets(mixed $value): void
    {
    }

    public function saveSupertextOverwrite(mixed $value): void
    {
    }
}
