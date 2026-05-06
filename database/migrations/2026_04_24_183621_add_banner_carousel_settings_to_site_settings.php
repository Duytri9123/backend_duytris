<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->integer('banner_autoplay_interval')->default(5000)->after('site_description'); // milliseconds
            $table->boolean('banner_show_arrows')->default(true)->after('banner_autoplay_interval');
            $table->boolean('banner_show_dots')->default(true)->after('banner_show_arrows');
            $table->string('banner_transition')->default('fade')->after('banner_show_dots'); // fade, slide, zoom
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['banner_autoplay_interval', 'banner_show_arrows', 'banner_show_dots', 'banner_transition']);
        });
    }
};
