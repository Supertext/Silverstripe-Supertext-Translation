<?php

namespace Supertext\Silverstripe\Extension;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\Form;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use Supertext\Silverstripe\Api\SupertextException;
use Supertext\Silverstripe\Model\TranslationLog;
use Supertext\Silverstripe\Service\Messages;
use Supertext\Silverstripe\Service\Translator;
use Supertext\Silverstripe\Supertext;
use TractorCow\Fluent\Model\Locale;

/**
 * Handles the Translate button of the Supertext tab in the page editor.
 *
 * @extends Extension<LeftAndMain>
 */
class SupertextCMSMainExtension extends Extension
{
    private static array $allowed_actions = ['doSupertextTranslate'];

    public function doSupertextTranslate(array $data, Form $form): HTTPResponse
    {
        if (!Permission::check(Supertext::PERMISSION)) {
            throw new HTTPResponse_Exception('Action not allowed', 403);
        }
        $record = $form->getRecord();
        $source = Locale::getCurrentLocale();
        if (!$record || !$record->isInDB() || !$source || !$record->canEdit()) {
            throw new HTTPResponse_Exception('Action not allowed', 403);
        }

        $targets = array_values(array_filter((array) ($data['SupertextTargets'] ?? []), 'is_string'));
        if ($targets === []) {
            return $this->complete(_t(self::class . '.NONE', 'Choose at least one locale to translate into.'), 400);
        }

        try {
            $results = Translator::singleton()->translate(
                $record,
                $source->Locale,
                $targets,
                !empty($data['SupertextOverwrite']),
                Security::getCurrentUser()
            );
        } catch (SupertextException $e) {
            return $this->complete(Messages::of($e), 400);
        }

        $names = [];
        foreach (Locale::getCached() as $locale) {
            $names[$locale->Locale] = $locale->getTitle();
        }
        $done = $skipped = $errors = [];
        foreach ($results as $result) {
            $name = $names[$result['locale']] ?? $result['locale'];
            match ($result['status']) {
                TranslationLog::TRANSLATED => $done[] = $name,
                TranslationLog::SKIPPED    => $skipped[] = $name,
                default                    => $errors[] = $name . ': ' . $result['message'],
            };
        }

        $parts = [];
        if ($done) {
            $parts[] = _t(self::class . '.DONE', 'Translated into {locales} (saved as drafts).', ['locales' => implode(', ', $done)]);
        }
        if ($skipped) {
            $parts[] = _t(self::class . '.SKIPPED', 'Already translated, skipped: {locales}.', ['locales' => implode(', ', $skipped)]);
        }
        if ($errors) {
            $parts[] = _t(self::class . '.FAILED', 'Failed: {errors}', ['errors' => implode('; ', $errors)]);
        }

        return $this->complete(implode(' ', $parts), $done || !$errors ? 200 : 400);
    }

    private function complete(string $message, int $status = 200): HTTPResponse
    {
        $owner = $this->owner;
        $response = $status === 200
            ? $owner->getResponseNegotiator()->respond($owner->getRequest())
            : HTTPResponse::create($message, $status);
        $response->addHeader('X-Status', rawurlencode($message));

        return $response;
    }
}
