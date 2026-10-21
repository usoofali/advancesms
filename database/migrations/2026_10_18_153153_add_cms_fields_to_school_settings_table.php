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
            $table->text('about_text')->nullable()->after('principal_name');
            $table->text('mission')->nullable()->after('about_text');
            $table->text('vision')->nullable()->after('mission');
            $table->json('core_values')->nullable()->after('vision');
            $table->text('principal_message')->nullable()->after('core_values');
            $table->string('principal_photo_path')->nullable()->after('principal_message');
            $table->string('office_hours', 100)->default('Mon - Fri: 8:00 AM - 3:30 PM')->after('principal_photo_path');
            $table->text('map_embed_url')->nullable()->after('office_hours');
            $table->json('social_links')->nullable()->after('map_embed_url');
            $table->string('prospectus_path')->nullable()->after('social_links');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_settings', function (Blueprint $table) {
            $table->dropColumn([
                'about_text',
                'mission',
                'vision',
                'core_values',
                'principal_message',
                'principal_photo_path',
                'office_hours',
                'map_embed_url',
                'social_links',
                'prospectus_path',
            ]);
        });
    }
};
