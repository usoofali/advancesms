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
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique()->comment('Sciences, Arts & Humanities, Languages, etc.');
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique()->comment('JSS 1, JSS 2, JSS 3, SS 1, SS 2, SS 3');
            $table->unsignedTinyInteger('level_order')->comment('1 for JSS 1 up to 6 for SS 3 for auto-promotion');
            $table->enum('stage', ['junior_secondary', 'senior_secondary']);
            $table->timestamps();
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->string('name', 50)->comment('A, B, C, D, etc.');
            $table->unsignedSmallInteger('capacity')->default(60);
            $table->timestamps();

            $table->unique(['class_id', 'name'], 'unique_class_section');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sections');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('departments');
    }
};
