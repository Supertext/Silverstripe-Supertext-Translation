<?php

namespace Supertext\Silverstripe\Task;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\Versioned;
use Supertext\Silverstripe\Api\SupertextException;
use Supertext\Silverstripe\Model\TranslationLog;
use Supertext\Silverstripe\Service\Translator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use TractorCow\Fluent\Model\Locale;

/**
 * vendor/bin/sake tasks:supertext-translate --page=<id> --from=en_US [--to=de_CH,fr_CH] [--overwrite] [--member=<email>]
 */
class TranslateTask extends BuildTask
{
    protected static string $commandName = 'supertext-translate';

    protected string $title = 'Supertext: translate a page';

    protected static string $description = 'Translates a page from one Fluent locale into others with Supertext (drafts).';

    private static array $permissions_for_browser_execution = ['ADMIN'];

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $page = Versioned::get_by_stage(SiteTree::class, Versioned::DRAFT)->byID((int) $input->getOption('page'));
        if (!$page) {
            $output->writeln('<error>Page not found. Use --page=<id>.</error>');

            return Command::FAILURE;
        }
        $from = (string) $input->getOption('from') ?: (string) Locale::getDefault()?->Locale;
        $to = array_filter(explode(',', (string) $input->getOption('to')));
        if ($to === []) {
            $to = array_values(array_diff(Locale::getCached()->column('Locale'), [$from]));
        }
        $member = null;
        if ($email = $input->getOption('member')) {
            $member = Member::get()->filter('Email', $email)->first();
            if (!$member) {
                $output->writeln("<error>No member with the e-mail address {$email}.</error>");

                return Command::FAILURE;
            }
        } else {
            $member = Member::get()->filter('Groups.Permissions.Code', 'ADMIN')->first();
        }

        try {
            $results = Translator::singleton()->translate($page, $from, $to, (bool) $input->getOption('overwrite'), $member);
        } catch (SupertextException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $failed = false;
        foreach ($results as $r) {
            $line = $r['locale'] . ': ' . $r['status'] . ($r['message'] ? " ({$r['message']})" : '');
            $failed = $failed || $r['status'] === TranslationLog::ERROR;
            $output->writeln($r['status'] === TranslationLog::ERROR ? "<error>{$line}</error>" : $line);
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    public function getOptions(): array
    {
        return [
            new InputOption('page', null, InputOption::VALUE_REQUIRED, 'Page ID'),
            new InputOption('from', null, InputOption::VALUE_REQUIRED, 'Source locale (default: the default locale)'),
            new InputOption('to', null, InputOption::VALUE_REQUIRED, 'Target locales, comma separated (default: all others)'),
            new InputOption('overwrite', null, InputOption::VALUE_NONE, 'Replace locales that already have their own content'),
            new InputOption('member', null, InputOption::VALUE_REQUIRED, 'Translate as this member (e-mail address; default: the first administrator)'),
        ];
    }
}
