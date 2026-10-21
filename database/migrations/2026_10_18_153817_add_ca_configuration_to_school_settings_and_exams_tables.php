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
        Schema::table('school_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('ca_count')->default(3)->after('principal_name');
            $table->decimal('default_max_ca1', 5, 2)->default(10.00)->after('ca_count');
            $table->decimal('default_max_ca2', 5, 2)->default(10.00)->after('default_max_ca1');
            $table->decimal('default_max_ca3', 5, 2)->default(10.00)->after('default_max_ca2');
            $table->decimal('default_max_exam', 5, 2)->default(70.00)->after('default_max_ca3');
            $table->string('ca1_label', 50)->default('1st CA')->after('default_max_exam');
            $table->string('ca2_label', 50)->default('2nd CA')->after('ca1_label');
            $table->string('ca3_label', 50)->default('3rd CA')->after('ca2_label');
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->unsignedTinyInteger('ca_count')->default(3)->after('name');
            $table->string('ca1_label', 50)->nullable()->after('max_exam');
            $table->string('ca2_label', 50)->nullable()->after('ca1_label');
            $table->string('ca3_label', 50)->nullable()->after('ca2_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn(['ca_count', 'ca1_label', 'ca2_label', 'ca3_label']);
        });

        Schema::table('school_settings', function (Blueprint $table) {
            $table->dropColumn([
                'ca_count',
                'default_max_ca1',
                'default_max_ca2',
                'default_max_ca3',
                'default_max_exam',
                'ca1_label',
                'ca2_label',
                'ca3_label',
            ]);
        });
    }
};
