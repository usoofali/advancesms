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
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete();
            $table->string('name', 150)->comment('e.g., First Term Examination 2024/2025');
            $table->decimal('max_ca1', 5, 2)->default(10.00);
            $table->decimal('max_ca2', 5, 2)->default(10.00);
            $table->decimal('max_ca3', 5, 2)->default(10.00);
            $table->decimal('max_exam', 5, 2)->default(70.00);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->string('name', 10)->unique()->comment('A, B, C, D, E, F');
            $table->decimal('grade_point', 4, 2)->comment('5.0, 4.0, 3.0, 2.0, 1.0, 0.0');
            $table->decimal('mark_from', 5, 2);
            $table->decimal('mark_to', 5, 2);
            $table->string('remark', 50)->comment('Excellent, Very Good, Good, Pass, Fair, Fail');
            $table->timestamps();
        });

        Schema::create('student_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->decimal('ca1', 5, 2)->nullable()->default(0.00);
            $table->decimal('ca2', 5, 2)->nullable()->default(0.00);
            $table->decimal('ca3', 5, 2)->nullable()->default(0.00);
            $table->decimal('exam_score', 5, 2)->nullable()->default(0.00);
            $table->decimal('total_score', 5, 2)->storedAs('COALESCE(ca1, 0) + COALESCE(ca2, 0) + COALESCE(ca3, 0) + COALESCE(exam_score, 0)');
            $table->foreignId('grade_id')->nullable()->constrained('grades')->nullOnDelete();
            $table->string('teacher_comment', 255)->nullable();
            $table->timestamps();

            $table->unique(['enrollment_id', 'subject_id', 'exam_id'], 'unique_student_subject_exam');
            $table->index(['exam_id', 'subject_id'], 'idx_marks_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_marks');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('exams');
    }
};
