<?php

namespace Supertext\Silverstripe\Service;

use Supertext\Silverstripe\Api\StateStore;
use Supertext\Silverstripe\Model\SupertextState;

/** The version reporter's state in the table SupertextState (one row per key). */
final class DatabaseStateStore implements StateStore
{
    public function __construct(private readonly string $key = 'reporter')
    {
    }

    public function load(): ?string
    {
        $row = SupertextState::get()->filter('Key', $this->key)->first();

        return $row ? (string) $row->Value : null;
    }

    public function save(string $json): void
    {
        $row = SupertextState::get()->filter('Key', $this->key)->first() ?? SupertextState::create(['Key' => $this->key]);
        $row->Value = $json;
        $row->write();
    }
}
