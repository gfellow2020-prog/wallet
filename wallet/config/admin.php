<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin Email Allowlist
    |--------------------------------------------------------------------------
    |
    | Until a richer role/permission system exists, admin API access is granted
    | only to users whose email appears in this allowlist. Configure it through
    | ADMIN_EMAILS as a comma-separated list.
    |
    */
    'emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('ADMIN_EMAILS', ''))
    ))),
];
