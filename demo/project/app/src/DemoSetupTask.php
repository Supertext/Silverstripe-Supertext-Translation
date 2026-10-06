<?php

namespace App;

use DNADesign\Elemental\Models\ElementContent;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\Versioned;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

/**
 * Demo setup, run on every start (vendor/bin/sake tasks:supertext-demo-setup). Only adds what
 * is missing; existing accounts and content are never changed. Passwords are never printed.
 */
class DemoSetupTask extends BuildTask
{
    protected static string $commandName = 'supertext-demo-setup';

    protected string $title = 'Supertext demo: locales, Editors group, demo accounts, sample pages';

    protected static string $description = 'Creates what the Supertext demo needs if it is missing.';

    private static array $permissions_for_browser_execution = ['ADMIN'];

    private const LOCALES = [
        ['en_US', 'English', 'en', true, ''],
        ['de_CH', 'Deutsch', 'de', false, 'more'],
        ['fr_CH', 'Français', 'fr', false, 'more'],
        ['it_CH', 'Italiano', 'it', false, 'more'],
    ];

    private PolyOutput $out;

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $this->out = $output;
        $this->ensureLocales();
        $editors = $this->ensureEditorsGroup();
        $this->ensureAccounts($editors);
        FluentState::singleton()->withState(function (FluentState $state) {
            $state->setLocale('en_US');
            $this->ensurePages();
        });

        return Command::SUCCESS;
    }

    private function log(string $message): void
    {
        $this->out->writeln("[demo] {$message}");
    }

    private function ensureLocales(): void
    {
        foreach (self::LOCALES as $sort => [$code, $title, $segment, $default, $politeness]) {
            if (Locale::get()->filter('Locale', $code)->exists()) {
                continue;
            }
            Locale::create([
                'Locale'              => $code,
                'Title'               => $title,
                'URLSegment'          => $segment,
                'IsGlobalDefault'     => $default,
                'SupertextPoliteness' => $politeness,
                'Sort'                => $sort + 1,
            ])->write();
            $this->log("Locale {$title} ({$code}) added.");
        }
        Locale::clearCached();
    }

    private function ensureEditorsGroup(): Group
    {
        $group = Group::get()->filter('Code', 'supertext-editors')->first();
        if ($group) {
            return $group;
        }
        $group = Group::create(['Title' => 'Editors', 'Code' => 'supertext-editors', 'Description' => 'Edit pages in every locale and translate them with Supertext.']);
        $group->write();
        foreach (['CMS_ACCESS_CMSMain', 'SUPERTEXT_TRANSLATE', 'VIEW_DRAFT_CONTENT'] as $code) {
            Permission::grant($group->ID, $code);
        }
        $this->log('Group Editors added.');

        return $group;
    }

    private function ensureAccounts(Group $editors): void
    {
        $admins = Group::get()->filter('Code', 'administrators')->first();
        if (!$admins) {
            Group::singleton()->requireDefaultRecords();
            $admins = Group::get()->filter('Code', 'administrators')->first();
        }

        foreach (['DEMO_ADMIN' => $admins, 'DEMO_EDITOR' => $editors] as $prefix => $group) {
            $email = trim((string) getenv("{$prefix}_EMAIL"));
            $password = (string) getenv("{$prefix}_PASSWORD");
            if ($email === '' || $password === '') {
                $this->log("{$prefix}_EMAIL / {$prefix}_PASSWORD not set; skipping that account.");
                continue;
            }
            if (Member::get()->filter('Email', $email)->exists()) {
                $this->log("{$prefix}: account exists, left unchanged.");
                continue;
            }
            $member = Member::create([
                'FirstName' => 'Demo',
                'Surname'   => $prefix === 'DEMO_ADMIN' ? 'Admin' : 'Editor',
                'Email'     => $email,
                'Locale'    => 'en_US',
            ]);
            $validator = Member::password_validator();
            if ($validator) {
                $result = $validator->validate($password, $member);
                if (!$result->isValid()) {
                    $reasons = implode(' ', array_column($result->getMessages(), 'message'));
                    $this->log("WARNING: {$prefix}_PASSWORD does not meet Silverstripe's password rules ({$reasons}); account not created.");
                    continue;
                }
            }
            $member->Password = $password;
            $member->write();
            $group->DirectMembers()->add($member); // Member::Groups() refuses admin groups without a signed-in admin (CLI)
            $this->log("{$prefix}: account created.");
        }
    }

    private function ensurePages(): void
    {
        if (SiteTree::get()->filter('URLSegment', 'swiss-chocolate-shipped-worldwide')->exists()) {
            return;
        }

        // The installer's sample pages make way for the demo's.
        foreach (SiteTree::get()->filter('Title', ['About Us', 'Contact Us']) as $page) {
            $page->doArchive();
        }

        $config = SiteConfig::current_site_config();
        $config->Title = 'Supertext Silverstripe Demo';
        $config->Tagline = '';
        $config->write();

        $home = SiteTree::get()->filter('URLSegment', 'home')->first() ?? \Page::create(['URLSegment' => 'home']);
        $home->Title = 'Welcome';
        $home->MenuTitle = 'Home';
        $home->Content = '';
        $home->MetaDescription = 'A Silverstripe demo site translated with Supertext.';
        $home->writeToStage(Versioned::DRAFT);
        $this->addBlock($home, '', '<p>This site was written in <strong>English</strong>. Open a page in the CMS and use the <em>Supertext</em> tab to create the German, French and Italian versions.</p>');
        $home->publishRecursive();

        $article = \Page::create([
            'Title'           => 'Swiss chocolate, shipped worldwide',
            'MenuTitle'       => 'Swiss chocolate',
            'URLSegment'      => 'swiss-chocolate-shipped-worldwide',
            'MetaDescription' => 'How a small family business in Bern brings handmade pralines to 40 countries.',
            'Sort'            => 2,
        ]);
        $article->writeToStage(Versioned::DRAFT);
        $this->addBlock(
            $article,
            'From Bern to the world',
            '<p>Every praline is made by hand in our <strong>Bern</strong> workshop. Read more on <a href="https://www.supertext.com">our website</a>.</p>'
            . '<ul><li>Fresh ingredients from local farmers</li><li>Climate-neutral delivery within 48 hours</li></ul>'
        );
        $this->addBlock($article, 'Handmade in Bern', '<p>Every praline is filled and decorated by hand.</p>');
        $this->addBlock($article, 'Shipped in 48 hours', '<p>Cool packaging keeps the chocolate fresh on its way.</p>');
        $article->publishRecursive();
        $this->log('Sample pages added (English).');
    }

    private function addBlock(SiteTree $page, string $title, string $html): void
    {
        $area = $page->ElementalArea();
        if (!$area->exists()) {
            $area->write();
            $page->ElementalAreaID = $area->ID;
            $page->writeToStage(Versioned::DRAFT);
        }
        $block = ElementContent::create([
            'Title'     => $title,
            'ShowTitle' => $title !== '',
            'HTML'      => $html,
            'ParentID'  => $area->ID,
            'Sort'      => $area->Elements()->count() + 1,
        ]);
        $block->writeToStage(Versioned::DRAFT);
    }
}
