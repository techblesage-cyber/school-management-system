<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How marks become grades, e.g. Primary D1–F9, O-level A–E, A-level A–F
        Schema::create('grading_scales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            // average: average mark and overall grade
            // aggregate: points of the best N subjects added up (lower is better), with divisions
            // points: points of all subjects added up (higher is better), as for A-level
            $table->string('summary', 20)->default('average');
            $table->unsignedTinyInteger('aggregate_subjects')->nullable();
            $table->json('divisions')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });

        Schema::create('grade_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_scale_id')->constrained()->cascadeOnDelete();
            $table->string('grade', 10);
            $table->decimal('min_score', 5, 2)->comment('Lowest percentage for this grade');
            $table->decimal('points', 4, 1)->nullable();
            $table->string('remark', 100)->nullable();
            $table->timestamps();
        });

        Schema::table('school_classes', function (Blueprint $table) {
            $table->foreignId('grading_scale_id')->nullable()->after('sort_order')->constrained()->nullOnDelete();
            $table->foreignId('class_teacher_id')->nullable()->after('grading_scale_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('streams', function (Blueprint $table) {
            $table->foreignId('class_teacher_id')->nullable()->after('name')->constrained('users')->nullOnDelete();
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->boolean('in_aggregate')->default(true)->after('code')->comment('Counts towards aggregate and division');
        });

        Schema::table('schools', function (Blueprint $table) {
            $table->boolean('show_positions')->default(true)->after('is_active');
            $table->boolean('report_shows_fees')->default(true)->after('show_positions');
        });

        // Exams and tests in a term: Beginning of term, Mid-term, End of term...
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('max_score')->default(100);
            $table->unsignedSmallInteger('weight')->default(100)->comment('Share of the term result, relative to the other assessments');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Which classes sit an assessment. No rows = every class.
        Schema::create('assessment_school_class', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();

            $table->unique(['assessment_id', 'school_class_id']);
        });

        Schema::create('marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            // The class and stream the student was in when marked, so old
            // reports stay right after students are promoted
            $table->foreignId('school_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stream_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('score', 6, 2);
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['assessment_id', 'student_id', 'subject_id']);
            $table->index(['student_id', 'assessment_id']);
            $table->index(['school_class_id', 'assessment_id']);
        });

        // Class teacher's and head teacher's comments on a student's term report
        Schema::create('report_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->text('class_teacher_comment')->nullable();
            $table->text('head_teacher_comment')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'academic_term_id']);
        });

        // A class's report cards for a term become visible to parents once published
        Schema::create('result_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['academic_term_id', 'school_class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_publications');
        Schema::dropIfExists('report_comments');
        Schema::dropIfExists('marks');
        Schema::dropIfExists('assessment_school_class');
        Schema::dropIfExists('assessments');

        Schema::table('schools', fn (Blueprint $table) => $table->dropColumn(['show_positions', 'report_shows_fees']));
        Schema::table('subjects', fn (Blueprint $table) => $table->dropColumn('in_aggregate'));
        Schema::table('streams', fn (Blueprint $table) => $table->dropConstrainedForeignId('class_teacher_id'));
        Schema::table('school_classes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('class_teacher_id');
            $table->dropConstrainedForeignId('grading_scale_id');
        });

        Schema::dropIfExists('grade_bands');
        Schema::dropIfExists('grading_scales');
    }
};
