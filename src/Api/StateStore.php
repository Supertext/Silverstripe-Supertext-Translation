<?php

/**
 * @package     Supertext Translation for Silverstripe
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace Supertext\Silverstripe\Api;

/**
 * Where the version reporter keeps its state (a JSON string): the table SupertextState in the
 * module (Service\DatabaseStateStore), an array in tests.
 */
interface StateStore
{
    /** The stored JSON, or null if there is none yet. */
    public function load(): ?string;

    public function save(string $json): void;
}
