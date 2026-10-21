<?php

namespace App\Support;

use App\Models\School;
use Illuminate\Http\Request;

/**
 * Each school's own web addresses, and which school a web address is for.
 *
 * With school subdomains switched on (SCHOOL_TENANT_DOMAIN), St Mary's is at
 * https://stmarys.sms.sharebility.net; otherwise everyone shares the main
 * address and staff pick their school after signing in.
 */
class SchoolAddress
{
    /**
     * The school whose subdomain this request is on, if any.
     */
    public static function fromRequest(?Request $request = null): ?School
    {
        $domain = config('school.tenant_domain');
        $host = ($request ?? request())->getHost();

        if (blank($domain) || ! str_ends_with($host, '.'.$domain)) {
            return null;
        }

        $slug = substr($host, 0, -strlen('.'.$domain));

        return School::query()->active()->where('slug', $slug)->first();
    }

    /**
     * Where this school's staff sign in.
     */
    public static function staffLogin(School $school): string
    {
        return self::base($school).'/school/login';
    }

    /**
     * Where this school's parents sign in.
     */
    public static function parentPortal(School $school): string
    {
        return self::base($school).'/parent';
    }

    protected static function base(School $school): string
    {
        $root = rtrim((string) config('app.url'), '/');
        $domain = config('school.tenant_domain');

        if (blank($domain)) {
            return $root;
        }

        $scheme = parse_url($root, PHP_URL_SCHEME) ?: 'https';
        $port = parse_url($root, PHP_URL_PORT);

        return "{$scheme}://{$school->slug}.{$domain}".($port ? ":{$port}" : '');
    }
}
