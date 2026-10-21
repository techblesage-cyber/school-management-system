<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Updating from the version where one parent account covered every school:
 * the account stays with its first school, and each other school gets an
 * account of its own.
 */
class ParentAccountSplitTest extends TestCase
{
    use DatabaseMigrations;

    public function test_a_shared_parent_account_is_split_into_one_per_school(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();

        $stmarys = DB::table('schools')->insertGetId(['name' => 'St Marys', 'slug' => 'stmarys', 'is_active' => true]);
        $kings = DB::table('schools')->insertGetId(['name' => 'Kings', 'slug' => 'kings', 'is_active' => true]);
        $shared = DB::table('parent_accounts')->insertGetId(['name' => 'Grace', 'phone' => '+256772123456', 'password' => Hash::make('old-pass')]);
        $orphan = DB::table('parent_accounts')->insertGetId(['name' => 'Nobody', 'phone' => '+256700000009', 'password' => 'x']);
        $atStMarys = DB::table('guardians')->insertGetId(['school_id' => $stmarys, 'parent_account_id' => $shared, 'name' => 'Grace', 'phone' => '+256772123456']);
        $atKings = DB::table('guardians')->insertGetId(['school_id' => $kings, 'parent_account_id' => $shared, 'name' => 'Grace N.', 'phone' => '+256772123456']);

        $this->artisan('migrate')->assertSuccessful();

        $first = DB::table('parent_accounts')->find($shared);
        $this->assertSame($stmarys, (int) $first->school_id);
        $this->assertTrue(Hash::check('old-pass', $first->password), 'the first school\'s login keeps working');
        $this->assertSame($shared, (int) DB::table('guardians')->find($atStMarys)->parent_account_id);

        $kingsAccount = DB::table('parent_accounts')->find(DB::table('guardians')->find($atKings)->parent_account_id);
        $this->assertNotSame($shared, (int) $kingsAccount->id);
        $this->assertSame($kings, (int) $kingsAccount->school_id);
        $this->assertSame('+256772123456', $kingsAccount->phone);
        $this->assertSame(1, (int) $kingsAccount->must_change_password);
        $this->assertNull(DB::table('parent_accounts')->find($orphan), 'logins with no children anywhere are removed');

        // And back again
        $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();
        $this->assertSame(1, DB::table('parent_accounts')->where('phone', '+256772123456')->count());
        $this->assertSame(2, DB::table('guardians')->where('parent_account_id', $shared)->count());
    }
}
