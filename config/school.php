<?php

return [

    /*
    |--------------------------------------------------------------------------
    | School web addresses
    |--------------------------------------------------------------------------
    |
    | Leave SCHOOL_TENANT_DOMAIN empty during development: each school's
    | portal opens at /school/{slug}, which works in Codespaces.
    |
    | On the live server, set it to the main domain (for example
    | sms.sharebility.net). Each school then opens on its own subdomain,
    | such as stmarys.sms.sharebility.net/school. This needs a wildcard
    | subdomain (*.sms.sharebility.net) and a wildcard SSL certificate.
    |
    */

    'tenant_domain' => env('SCHOOL_TENANT_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Sharebility admin panel address
    |--------------------------------------------------------------------------
    |
    | The admin panel is never linked from school or parent screens. On the
    | live server give it a private address only Sharebility knows, e.g.
    | ADMIN_PATH=sharebility-hq-7k2. With school subdomains on, it only opens
    | on the main domain, never on a school's own address.
    |
    */

    'admin_path' => trim((string) preg_replace('/[^a-z0-9\-]/', '', strtolower((string) env('ADMIN_PATH', 'admin'))), '-') ?: 'admin',

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Shown before every amount. Amounts are stored as whole units, since
    | Uganda shillings have no cents.
    |
    */

    'currency' => env('SCHOOL_CURRENCY', 'UGX'),

    /*
    |--------------------------------------------------------------------------
    | Phone numbers
    |--------------------------------------------------------------------------
    |
    | Local numbers such as 0772 123 456 are stored as +256772123456, so the
    | same parent is recognised however the number was typed.
    |
    */

    'phone_country_code' => env('SCHOOL_PHONE_COUNTRY_CODE', '256'),

];
