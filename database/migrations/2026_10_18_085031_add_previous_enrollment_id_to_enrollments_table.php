<?php

use App\Models\Student;
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
        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('previous_enrollment_id')
                ->nullable()
                ->after('student_id')
                ->constrained('enrollments')
                ->nullOnDelete();
        });

        // Backfill existing enrollments: if a student has multiple enrollments ordered by ID, link them
        $students = Student::with(['enrollments' => fn ($q) => $q->orderBy('id')])->get();
        foreach ($students as $student) {
            $previous = null;
            foreach ($student->enrollments as $enrollment) {
                if ($previous !== null && $enrollment->previous_enrollment_id === null) {
                    $enrollment->updateQuietly(['previous_enrollment_id' => $previous->id]);
                }
                $previous = $enrollment;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropForeign(['previous_enrollment_id']);
            $table->dropColumn('previous_enrollment_id');
        });
    }
};
