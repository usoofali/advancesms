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
        Schema::create('school_settings', function (Blueprint $table) {
            $table->id();
            $table->string('school_name')->default('School Management System');
            $table->string('school_code', 50)->default('SMS-APP');
            $table->string('motto')->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('currency_symbol', 10)->default('₦');
            $table->string('currency_code', 10)->default('NGN');
            $table->string('principal_name')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('stamp_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_settings');
    }
};
