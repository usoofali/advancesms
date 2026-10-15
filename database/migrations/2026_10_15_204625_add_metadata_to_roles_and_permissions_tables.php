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
        $tableNames = config('permission.table_names');

        Schema::table($tableNames['permissions'], function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
            $table->string('group', 100)->default('General')->after('display_name');
            $table->text('description')->nullable()->after('group');
            $table->index('group');
        });

        Schema::table($tableNames['roles'], function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
            $table->text('description')->nullable()->after('display_name');
            $table->boolean('is_system')->default(false)->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        Schema::table($tableNames['permissions'], function (Blueprint $table) {
            $table->dropIndex(['group']);
            $table->dropColumn(['display_name', 'group', 'description']);
        });

        Schema::table($tableNames['roles'], function (Blueprint $table) {
            $table->dropColumn(['display_name', 'description', 'is_system']);
        });
    }
};
