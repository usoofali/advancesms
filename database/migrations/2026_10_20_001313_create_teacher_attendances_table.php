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
        Schema::create('teacher_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('academic_terms')->nullOnDelete();
            $table->date('attendance_date');
            $table->enum('status', ['present', 'absent', 'late', 'excused'])->default('present');
            $table->foreignId('recorded_by')->nullable()->comment('User ID of marker')->constrained('users')->nullOnDelete();
            $table->string('remark', 255)->nullable();
            $table->timestamps();

            $table->unique(['teacher_id', 'attendance_date'], 'unique_teacher_daily_attendance');
            $table->index(['attendance_date', 'status'], 'idx_teacher_attendance_date');
            $table->index(['academic_session_id', 'term_id'], 'idx_teacher_att_session_term');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_attendances');
    }
};
