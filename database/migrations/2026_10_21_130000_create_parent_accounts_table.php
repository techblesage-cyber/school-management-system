<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Parents' portal logins. One account per phone number, linked to the
        // parent's records at every school their children attend.
        Schema::create('parent_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 20)->unique()->comment('+256772123456');
            $table->string('email')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->foreignId('parent_account_id')->nullable()->after('school_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('guardians', fn (Blueprint $table) => $table->dropConstrainedForeignId('parent_account_id'));
        Schema::dropIfExists('parent_accounts');
    }
};
