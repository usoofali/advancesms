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
        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique()->comment('e.g., 2024/2025, 2025/2026');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        Schema::create('academic_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->string('name', 50)->comment('First Term, Second Term, Third Term');
            $table->unsignedTinyInteger('term_number')->comment('1, 2, or 3');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['academic_session_id', 'term_number'], 'unique_term_per_session');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_terms');
        Schema::dropIfExists('academic_sessions');
    }
};
