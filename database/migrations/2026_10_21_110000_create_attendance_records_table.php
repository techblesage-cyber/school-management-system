<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The daily class register: one row per student per day
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stream_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->string('status', 10);
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'date']);
            $table->index(['school_id', 'date']);
            $table->index(['school_class_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
