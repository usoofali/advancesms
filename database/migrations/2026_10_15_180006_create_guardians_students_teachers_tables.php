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
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('name', 200);
            $table->string('relationship', 50)->default('Parent');
            $table->string('phone', 30);
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->string('occupation', 150)->nullable();
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained('guardians')->nullOnDelete();
            $table->string('admission_number', 50)->unique()->comment('e.g. GSSS25-00001');
            $table->date('admission_date');
            $table->date('date_of_birth')->nullable();
            $table->string('state_of_origin', 100)->nullable();
            $table->string('lga', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('staff_id', 50)->unique()->comment('Staff ID / Payroll Code');
            $table->string('designation', 150)->nullable()->comment('e.g., Form Master, H.O.D, Senior Master');
            $table->string('qualification', 150)->nullable()->comment('B.Sc. Ed, B.Tech, NCE, etc.');
            $table->date('joining_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teachers');
        Schema::dropIfExists('students');
        Schema::dropIfExists('guardians');
    }
};
