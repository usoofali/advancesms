<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('section_id')->constrained('sections')->restrictOnDelete();
            $table->string('roll_number', 30)->nullable();
            $table->enum('status', ['active', 'promoted', 'repeated', 'withdrawn', 'graduated'])->default('active');
            $table->timestamps();

            $table->unique(['student_id', 'academic_session_id'], 'unique_student_session');
            $table->index(['academic_session_id', 'class_id', 'section_id', 'status'], 'idx_enrollment_lookup');
        });

        Schema::create('teacher_subject_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->boolean('can_input_marks')->default(true);
            $table->boolean('can_take_attendance')->default(false);
            $table->timestamps();

            $table->unique(['academic_term_id', 'section_id', 'subject_id'], 'unique_assignment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_subject_assignments');
        Schema::dropIfExists('enrollments');
    }
};
