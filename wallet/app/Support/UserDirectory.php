<?php

namespace App\Support;

/**
 * User-facing copy for directory / lookup flows (keep in sync to limit enumeration).
 */
final class UserDirectory
{
    public const EXTRA_CASH_LOOKUP_NOT_FOUND = 'We could not find a user with that ExtraCash number.';

    public const EXTRA_CASH_LOOKUP_SELF = 'Use a different ExtraCash number.';
}
