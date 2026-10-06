<?php

namespace Supertext\Silverstripe\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;

/**
 * One translation of a record into a locale: shown on the Supertext tab ("Translated with
 * Supertext on …") and in the Supertext admin.
 *
 * @property string $RecordClass
 * @property int    $RecordID
 * @property string $RecordTitle
 * @property string $SourceLocale
 * @property string $TargetLocale
 * @property string $Status
 * @property string $Message
 * @property int    $MemberID
 */
class TranslationLog extends DataObject
{
    public const TRANSLATED = 'translated';
    public const SKIPPED    = 'skipped';
    public const ERROR      = 'error';

    private static string $table_name = 'SupertextTranslation';

    private static array $db = [
        'RecordClass'  => 'Varchar(255)',
        'RecordID'     => 'Int',
        'RecordTitle'  => 'Varchar(255)',
        'SourceLocale' => 'Varchar(20)',
        'TargetLocale' => 'Varchar(20)',
        'Status'       => "Enum('translated,skipped,error','translated')",
        'Message'      => 'Text',
    ];

    private static array $has_one = [
        'Member' => Member::class,
    ];

    private static array $indexes = [
        'Record' => ['type' => 'index', 'columns' => ['RecordClass', 'RecordID']],
    ];

    private static string $default_sort = '"Created" DESC';

    private static array $summary_fields = [
        'Created.Nice' => 'Date',
        'RecordTitle'  => 'Page',
        'SourceLocale' => 'From',
        'TargetLocale' => 'Into',
        'Status'       => 'Status',
        'Member.Name'  => 'By',
        'Message'      => 'Message',
    ];

    private static string $singular_name = 'Supertext translation';

    private static string $plural_name = 'Supertext translations';

    public function canView($member = null): bool
    {
        return Permission::check('CMS_ACCESS_CMSMain', 'any', $member);
    }

    public function canEdit($member = null): bool
    {
        return false;
    }

    public function canCreate($member = null, $context = []): bool
    {
        return false;
    }

    public function canDelete($member = null): bool
    {
        return Permission::check('ADMIN', 'any', $member);
    }

    /** Last successful translation per target locale for a record: locale => DBDatetime string. */
    public static function lastTranslations(DataObject $record): array
    {
        $out = [];
        $logs = self::get()->filter([
            'RecordClass' => $record->baseClass(),
            'RecordID'    => $record->ID,
            'Status'      => self::TRANSLATED,
        ])->sort('Created', 'ASC');
        foreach ($logs as $log) {
            $out[$log->TargetLocale] = $log->Created;
        }

        return $out;
    }
}
