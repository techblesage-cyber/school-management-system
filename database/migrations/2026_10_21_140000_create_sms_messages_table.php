<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every SMS sent (or tried), for the school's records and for billing
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to', 20);
            $table->text('message');
            $table->string('purpose', 30);
            $table->string('status', 10)->default('queued');
            $table->string('provider_id')->nullable();
            $table->string('cost', 30)->nullable();
            $table->text('error')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'created_at']);
        });

        Schema::table('schools', function (Blueprint $table) {
            // Switched on by Sharebility for schools that use (and pay for) SMS
            $table->boolean('sms_enabled')->default(false)->after('report_shows_fees');
            $table->boolean('sms_payment_receipts')->default(true)->after('sms_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('schools', fn (Blueprint $table) => $table->dropColumn(['sms_enabled', 'sms_payment_receipts']));
        Schema::dropIfExists('sms_messages');
    }
};
