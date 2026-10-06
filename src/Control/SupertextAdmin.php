<?php

namespace Supertext\Silverstripe\Control;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Security\Permission;
use SilverStripe\View\Requirements;
use Supertext\Silverstripe\Api\SupertextException;
use Supertext\Silverstripe\Model\TranslationLog;
use Supertext\Silverstripe\Supertext;
use TractorCow\Fluent\Control\LocaleAdmin;
use TractorCow\Fluent\Model\Locale;

/**
 * Admin section "Supertext": connection status, Test connection (administrators), the
 * locales with their Supertext language and form of address, and the translation log.
 */
class SupertextAdmin extends ModelAdmin
{
    private static string $url_segment = 'supertext';

    private static string $menu_title = 'Supertext';

    private static string $menu_icon_class = 'font-icon-translatable';

    private static array $managed_models = [
        'translations' => ['dataClass' => TranslationLog::class, 'title' => 'Translations'],
    ];

    private static array $allowed_actions = ['doTestConnection'];

    private static array $required_permission_codes = [Supertext::PERMISSION];

    public $showImportForm = false;

    protected function init()
    {
        parent::init();
        Requirements::javascript('supertext/silverstripe-supertext-translation:client/supertext.js');
        Requirements::css('supertext/silverstripe-supertext-translation:client/supertext.css');
    }

    public function getEditForm($id = null, $fields = null): Form
    {
        $form = parent::getEditForm($id, $fields);

        $fields = $form->Fields();
        $fields->unshift(LiteralField::create('SupertextLocales', $this->localesHtml()));
        if (Permission::check('ADMIN')) {
            $fields->unshift(
                FormAction::create('doTestConnection', _t(self::class . '.TEST', 'Test connection'))
                    ->addExtraClass('btn-outline-primary font-icon-sync supertext-test')
                    ->setUseButtonTag(true)
                    ->setAttribute('data-busy', _t(self::class . '.TESTING', 'Testing…'))
            );
        }
        $fields->unshift(LiteralField::create('SupertextConnection', $this->connectionHtml()));

        return $form;
    }

    protected function getGridFieldConfig(): GridFieldConfig
    {
        return GridFieldConfig_RecordViewer::create(50);
    }

    public function doTestConnection(array $data, Form $form): HTTPResponse
    {
        if (!Permission::check('ADMIN')) {
            throw new HTTPResponse_Exception('Action not allowed', 403);
        }
        $settings = Supertext::singleton();
        try {
            if ($settings->apiKey() === '') {
                throw new SupertextException(_t(self::class . '.NO_KEY', 'No API key: set the SUPERTEXT_API_KEY environment variable.'));
            }
            $settings->client()->validateApiKey();
            $message = _t(self::class . '.CONNECTED', 'Connected. The API key works.');
            $status = 200;
        } catch (SupertextException $e) {
            $message = $e->getMessage();
            $status = 400;
        }

        $response = $status === 200
            ? $this->getResponseNegotiator()->respond($this->getRequest())
            : HTTPResponse::create($message, $status);
        $response->addHeader('X-Status', rawurlencode($message));

        return $response;
    }

    private function connectionHtml(): string
    {
        $settings = Supertext::singleton();
        $key = $settings->apiKey() !== ''
            ? _t(self::class . '.KEY_SET', 'Set (environment variable SUPERTEXT_API_KEY)')
            : '<strong>' . _t(self::class . '.KEY_MISSING', 'Missing: set the environment variable SUPERTEXT_API_KEY') . '</strong>';

        return sprintf(
            '<div class="supertext-status"><h2>%s</h2><table class="table supertext-table"><tbody>
                <tr><th>%s</th><td>%s</td></tr>
                <tr><th>%s</th><td><code data-supertext-endpoint>%s</code></td></tr>
                <tr><th>%s</th><td>%d s</td></tr>
            </tbody></table><p class="supertext-help">%s</p></div>',
            _t(self::class . '.CONNECTION', 'Connection'),
            _t(self::class . '.API_KEY', 'API key'),
            $key,
            _t(self::class . '.API', 'API'),
            Convert::raw2xml($settings->baseUrl()),
            _t(self::class . '.TIMEOUT', 'Timeout per locale'),
            $settings->timeout(),
            _t(
                self::class . '.KEY_HELP',
                'No Supertext account yet? <a href="{signup}" target="_blank" rel="noopener">Create one at supertext.com</a>. Generate your API key at <a href="{apikey}" target="_blank" rel="noopener">supertext.com → Integrations → API</a> (requires the Admin role).',
                ['signup' => Supertext::SIGNUP_URL, 'apikey' => Supertext::API_KEY_URL]
            )
        );
    }

    private function localesHtml(): string
    {
        $rows = '';
        foreach (Locale::getCached() as $locale) {
            $politeness = match (Supertext::politeness($locale)) {
                'more'  => _t(self::class . '.FORMAL', 'Formal (Sie, vous)'),
                'less'  => _t(self::class . '.INFORMAL', 'Informal (du, tu)'),
                default => _t(self::class . '.DEFAULT', 'Default'),
            };
            $rows .= sprintf(
                '<tr><td>%s <code>%s</code></td><td><code>%s</code></td><td>%s</td></tr>',
                Convert::raw2xml($locale->getTitle()),
                Convert::raw2xml($locale->Locale),
                Convert::raw2xml(Supertext::languageCode($locale)),
                $politeness
            );
        }

        return sprintf(
            '<div class="supertext-status supertext-locales"><h2>%s</h2><p>%s</p>
                <table class="table supertext-table"><thead><tr><th>%s</th><th>%s</th><th>%s</th></tr></thead><tbody>%s</tbody></table>
                <h2>%s</h2></div>',
            _t(self::class . '.LOCALES', 'Locales'),
            _t(
                self::class . '.LOCALES_HELP',
                'The Fluent locales with the Supertext language and form of address they are translated with. Change them under <a href="{link}">Locales</a>.',
                ['link' => Convert::raw2att(LocaleAdmin::singleton()->Link())]
            ),
            _t(self::class . '.LOCALE', 'Locale'),
            _t(self::class . '.CODE', 'Supertext language'),
            _t(self::class . '.POLITENESS', 'Form of address'),
            $rows,
            _t(self::class . '.LOG', 'Translations')
        );
    }
}
