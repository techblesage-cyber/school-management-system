<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Passport photos for staff (one per person, shared across schools) and parents.
        // Students already have a photo column.
        Schema::table('users', function (Blueprint $table) {
            $table->string('photo')->nullable()->after('email');
        });

        Schema::table('guardians', function (Blueprint $table) {
            $table->string('photo')->nullable()->after('address');
        });

        // Subjects taught at each school, such as Mathematics or Biology
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 20)->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        // Who teaches which subject to which class (or one stream of a class).
        // A teacher has one row per subject and class they teach.
        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stream_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'user_id']);
            $table->index(['school_id', 'school_class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teaching_assignments');
        Schema::dropIfExists('subjects');

        Schema::table('guardians', function (Blueprint $table) {
            $table->dropColumn('photo');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('photo');
        });
    }
};
