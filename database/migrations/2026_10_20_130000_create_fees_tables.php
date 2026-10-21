<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Term 1, 2 and 3 of each year. Fees are set and billed per term.
        Schema::create('academic_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('term');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['school_id', 'year', 'term']);
        });

        // The fee structure: what each class pays in a term. Amounts are in
        // whole shillings (UGX has no cents).
        Schema::create('fee_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedBigInteger('amount');
            $table->string('residence', 10)->nullable()->comment('null = day scholars and boarders');
            $table->timestamps();
        });

        // Which classes a fee item applies to. No rows = every class.
        Schema::create('fee_item_school_class', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();

            $table->unique(['fee_item_id', 'school_class_id']);
        });

        // One fees invoice (bill) per student per term
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_term_id')->constrained()->restrictOnDelete();
            $table->string('number', 30);
            $table->date('issued_on');
            $table->bigInteger('total')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'academic_term_id']);
            $table->unique(['school_id', 'number']);
        });

        // Lines on an invoice. A negative amount is a discount or bursary.
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->bigInteger('amount');
            $table->timestamps();
        });

        // Money received. Payments are never edited or deleted, only voided
        // with a reason, so the record of what was received stays complete.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_term_id')->nullable()->constrained()->nullOnDelete();
            $table->string('receipt_number', 30);
            $table->unsignedBigInteger('amount');
            $table->date('paid_on');
            $table->string('method', 20);
            $table->string('reference', 100)->nullable()->comment('Bank slip, mobile money or SchoolPay transaction ID');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'receipt_number']);
            $table->index(['school_id', 'paid_on']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('fee_item_school_class');
        Schema::dropIfExists('fee_items');
        Schema::dropIfExists('academic_terms');
    }
};
