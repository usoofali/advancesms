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
            $table->longText('letterhead_base64')->nullable()->after('prospectus_path');
            $table->unsignedInteger('letterhead_top_margin')->default(120)->after('letterhead_base64');
            $table->unsignedInteger('letterhead_bottom_margin')->default(50)->after('letterhead_top_margin');
            $table->unsignedInteger('letterhead_left_margin')->default(40)->after('letterhead_bottom_margin');
            $table->unsignedInteger('letterhead_right_margin')->default(40)->after('letterhead_left_margin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->dropColumn([
                'letterhead_base64',
                'letterhead_top_margin',
                'letterhead_bottom_margin',
                'letterhead_left_margin',
                'letterhead_right_margin',
            ]);
        });
    }
};
