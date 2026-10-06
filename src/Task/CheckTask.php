<?php

namespace Supertext\Silverstripe\Task;

use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Supertext\Silverstripe\Api\SupertextException;
use Supertext\Silverstripe\Supertext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/** vendor/bin/sake tasks:supertext-check — checks the API key (cost-free). */
class CheckTask extends BuildTask
{
    protected static string $commandName = 'supertext-check';

    protected string $title = 'Supertext: check the API key';

    protected static string $description = 'Checks the Supertext API key against the API. Nothing is translated or charged.';

    private static array $permissions_for_browser_execution = ['ADMIN'];

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $settings = Supertext::singleton();
        if ($settings->apiKey() === '') {
            $output->writeln('<error>No Supertext API key: set SUPERTEXT_API_KEY.</error>');
            $output->writeln('No Supertext account yet? Create one at ' . Supertext::SIGNUP_URL);
            $output->writeln('Generate your API key at ' . Supertext::API_KEY_URL . ' (Integrations → API, requires the Admin role).');

            return Command::FAILURE;
        }
        try {
            $settings->client()->validateApiKey();
        } catch (SupertextException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }
        $output->writeln('Connected to ' . $settings->baseUrl() . '. The API key works.');

        return Command::SUCCESS;
    }
}
