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
