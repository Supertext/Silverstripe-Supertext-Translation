<?php

namespace Supertext\Silverstripe\Task;

use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Supertext\Silverstripe\PluginVersion;
use Supertext\Silverstripe\Sdk\V1\ApiKeyCheck;
use Supertext\Silverstripe\Service\Messages;
use Supertext\Silverstripe\Service\Reports;
use Supertext\Silverstripe\Supertext;
use TractorCow\Fluent\Model\Locale;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * vendor/bin/sake tasks:supertext-check — checks the API key (cost-free), prints the version and the
 * language mapping, and sends a `check` version report (if version reports are on).
 */
class CheckTask extends BuildTask
{
    protected static string $commandName = 'supertext-check';

    protected string $title = 'Supertext: check the API key';

    protected static string $description = 'Checks the Supertext API key against the API. Nothing is translated or charged.';

    private static array $permissions_for_browser_execution = ['ADMIN'];

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $settings = Supertext::singleton();
        $output->writeln('Supertext Translation for Silverstripe ' . PluginVersion::current() . ', API ' . $settings->baseUrl());

        $failed = false;
        foreach (Locale::getCached() as $locale) {
            $code = Supertext::languageCodeOrNull($locale);
            $failed = $failed || $code === null;
            $output->writeln(sprintf('  %s → %s', $locale->Locale, $code ?? 'not mapped: choose a Supertext language for this locale (Locales admin)'));
        }

        $check = Reports::reportCheck();
        $output->writeln('Version information to Supertext: ' . ($settings->versionReportsEnabled() ? 'on, ping address ' . Reports::callbackUrl() : 'off'));
        if ($check === null) {
            $output->writeln('<error>The API key could not be checked.</error>');

            return Command::FAILURE;
        }
        $message = Messages::keyCheck($check, false);
        if ($check->status !== ApiKeyCheck::OK) {
            $output->writeln('<error>' . $message . '</error>');

            return Command::FAILURE;
        }
        $output->writeln($message);

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
