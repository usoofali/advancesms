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
            $table->string('name', 50)->unique()->comment('e.g., Nursery 1, Primary 1, JSS 1, SS 1, etc.');
            $table->unsignedTinyInteger('level_order')->comment('Numeric order for class sequence and progression');
            $table->string('stage', 50)->comment('e.g., kindergarten, nursery, primary, junior_secondary, senior_secondary, or custom');
            $table->timestamps();
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->string('name', 50)->comment('e.g., A, B, Red, Green, Diamond, 1, etc.');
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
