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
        Schema::create('daily_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->enum('status', ['present', 'absent', 'late', 'excused'])->default('present');
            $table->foreignId('recorded_by')->nullable()->comment('User ID of teacher who recorded attendance')->constrained('users')->nullOnDelete();
            $table->string('remark', 255)->nullable();
            $table->timestamps();

            $table->unique(['enrollment_id', 'attendance_date'], 'unique_student_daily_attendance');
            $table->index(['attendance_date', 'status'], 'idx_attendance_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_attendances');
    }
};
