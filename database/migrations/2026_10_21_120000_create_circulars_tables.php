<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Letters and notices from the school to parents and/or staff
        Schema::create('circulars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('body');
            $table->string('audience', 10)->default('parents');
            $table->string('attachment')->nullable();
            $table->string('attachment_name')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'published_at']);
        });

        // Which classes' parents a circular is for. No rows = every class.
        Schema::create('circular_school_class', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circular_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained()->cascadeOnDelete();

            $table->unique(['circular_id', 'school_class_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circular_school_class');
        Schema::dropIfExists('circulars');
    }
};
