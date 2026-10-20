<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Classes such as "P.5" or "S.2", defined by each school
        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('level', 20)->default('other');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        // Streams within a class, such as "East" or "A"
        Schema::create('streams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->timestamps();

            $table->unique(['school_class_id', 'name']);
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('admission_number', 30);
            $table->string('lin', 30)->nullable()->comment('Learner Identification Number');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('other_names', 100)->nullable();
            $table->string('gender', 10);
            $table->date('date_of_birth')->nullable();
            $table->string('nationality', 50)->nullable();
            $table->string('photo')->nullable();
            $table->foreignId('school_class_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('stream_id')->nullable()->constrained()->nullOnDelete();
            $table->string('residence', 10)->default('day');
            $table->date('admission_date');
            $table->string('status', 20)->default('active');
            $table->string('previous_school')->nullable();
            $table->string('entry_index_number', 30)->nullable()->comment('PLE/UCE index number');
            $table->string('home_address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'admission_number']);
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'last_name']);
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 30);
            $table->string('alt_phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('occupation', 100)->nullable();
            $table->string('address')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'phone']);
        });

        // A student can have several guardians, and a guardian several children
        Schema::create('guardian_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('relationship', 20)->default('guardian');
            $table->boolean('is_primary')->default(false)->comment('Main contact for this student');
            $table->timestamps();

            $table->unique(['guardian_id', 'student_id']);
        });

        Schema::create('admission_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 30);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('other_names', 100)->nullable();
            $table->string('gender', 10);
            $table->date('date_of_birth')->nullable();
            $table->foreignId('school_class_id')->nullable()->constrained()->nullOnDelete()->comment('Class applied for');
            $table->string('previous_school')->nullable();
            $table->string('entry_index_number', 30)->nullable();
            $table->string('guardian_name');
            $table->string('guardian_phone', 30);
            $table->string('guardian_email')->nullable();
            $table->string('guardian_relationship', 20)->default('guardian');
            $table->date('applied_on');
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'reference']);
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_applications');
        Schema::dropIfExists('guardian_student');
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('students');
        Schema::dropIfExists('streams');
        Schema::dropIfExists('school_classes');
    }
};
