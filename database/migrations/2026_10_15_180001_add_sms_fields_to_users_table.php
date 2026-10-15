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
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 100)->nullable()->unique()->after('id');
            $table->string('first_name', 100)->nullable()->after('password');
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('last_name', 100)->nullable()->after('middle_name');
            $table->string('role', 30)->default('student')->index()->after('last_name');
            $table->string('gender', 10)->default('male')->after('role');
            $table->string('phone', 30)->nullable()->after('gender');
            $table->string('blood_group', 10)->nullable()->after('phone');
            $table->text('address')->nullable()->after('blood_group');
            $table->string('avatar', 255)->nullable()->after('address');
            $table->boolean('is_active')->default(true)->after('avatar');
            $table->unsignedInteger('legacy_id')->nullable()->after('is_active');
            $table->string('name')->nullable()->change();
            $table->string('email', 150)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username',
                'first_name',
                'middle_name',
                'last_name',
                'role',
                'gender',
                'phone',
                'blood_group',
                'address',
                'avatar',
                'is_active',
                'legacy_id',
            ]);
            $table->string('name')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
        });
    }
};
