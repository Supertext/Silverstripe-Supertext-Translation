<?php

namespace Supertext\Silverstripe\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;

/**
 * Small key/value store of the module (table SupertextState). Holds the version reporter's state
 * under "reporter": what was reported to Supertext last, the latest API key check and the key's
 * fingerprint (a SHA-256, never the key). Not editable in the CMS.
 *
 * @property string $Key
 * @property string $Value
 */
class SupertextState extends DataObject
{
    private static string $table_name = 'SupertextState';

    private static array $db = [
        'Key'   => 'Varchar(64)',
        'Value' => 'Text',
    ];

    private static array $indexes = [
        'Key' => ['type' => 'unique', 'columns' => ['Key']],
    ];

    private static string $singular_name = 'Supertext state';

    private static string $plural_name = 'Supertext states';

    public function canView($member = null)
    {
        return Permission::check('ADMIN', 'any', $member);
    }

    public function canEdit($member = null)
    {
        return false;
    }

    public function canDelete($member = null)
    {
        return false;
    }

    public function canCreate($member = null, $context = [])
    {
        return false;
    }
}
