<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Each school has its own parents' portal logins: the same parent at
        // two schools has two separate accounts, with different passwords.
        // (Each step checks first, so a half-finished earlier run is fine.)
        Schema::table('parent_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('parent_accounts', 'school_id')) {
                $table->foreignId('school_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            }

            if (! Schema::hasColumn('parent_accounts', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('password');
            }
        });

        // Staff given a password by the system choose their own at first sign-in
        if (! Schema::hasColumn('users', 'must_change_password')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('must_change_password')->default(false)->after('password');
            });
        }

        // The old rule (one account per phone number) goes before splitting
        if ($this->hasIndex('parent_accounts_phone_unique')) {
            Schema::table('parent_accounts', fn (Blueprint $table) => $table->dropUnique(['phone']));
        }

        $this->splitSharedAccounts();

        if (! $this->hasIndex('parent_accounts_school_id_phone_unique')) {
            Schema::table('parent_accounts', fn (Blueprint $table) => $table->unique(['school_id', 'phone']));
        }
    }

    protected function hasIndex(string $name): bool
    {
        return collect(Schema::getIndexes('parent_accounts'))->contains(fn (array $index): bool => $index['name'] === $name);
    }

    /**
     * Until now one account could cover children at several schools. Each
     * account now stays with the school of its first parent record; parent
     * records at other schools get a new account of their own (the school
     * gives the parent its login with "Send new login").
     */
    protected function splitSharedAccounts(): void
    {
        foreach (DB::table('parent_accounts')->orderBy('id')->get() as $account) {
            $guardians = DB::table('guardians')->where('parent_account_id', $account->id)->orderBy('id')->get();

            if ($guardians->isEmpty()) {
                if ($account->school_id === null) {
                    DB::table('parent_accounts')->where('id', $account->id)->delete();
                }

                continue;
            }

            $home = $account->school_id ?? $guardians->first()->school_id;
            DB::table('parent_accounts')->where('id', $account->id)->update(['school_id' => $home]);

            foreach ($guardians->where('school_id', '!=', $home)->pluck('school_id')->unique() as $schoolId) {
                $newId = DB::table('parent_accounts')->where('school_id', $schoolId)->where('phone', $account->phone)->value('id')
                    ?? DB::table('parent_accounts')->insertGetId([
                        'school_id' => $schoolId,
                        'name' => $account->name,
                        'phone' => $account->phone,
                        'email' => $account->email,
                        'password' => Hash::make(Str::random(32)),
                        'must_change_password' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                DB::table('guardians')
                    ->where('parent_account_id', $account->id)
                    ->where('school_id', $schoolId)
                    ->update(['parent_account_id' => $newId]);
            }
        }
    }

    public function down(): void
    {
        // MySQL uses the (school_id, phone) index for the school link, so the
        // link goes first
        Schema::table('parent_accounts', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropUnique(['school_id', 'phone']);
        });

        // Back to one account per phone number: keep the oldest
        $keep = DB::table('parent_accounts')->selectRaw('min(id) as id, phone')->groupBy('phone')->pluck('id', 'phone');

        foreach (DB::table('parent_accounts')->whereNotIn('id', $keep->values())->get() as $extra) {
            DB::table('guardians')->where('parent_account_id', $extra->id)->update(['parent_account_id' => $keep[$extra->phone]]);
            DB::table('parent_accounts')->where('id', $extra->id)->delete();
        }

        Schema::table('parent_accounts', function (Blueprint $table) {
            $table->unique('phone');
            $table->dropColumn(['school_id', 'must_change_password']);
        });

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('must_change_password'));
    }
};
