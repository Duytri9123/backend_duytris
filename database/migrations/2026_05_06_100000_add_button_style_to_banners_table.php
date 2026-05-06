<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            // JSON string: { bgColor, textColor, borderColor, borderWidth, borderRadius, paddingX, paddingY, fontSize, fontWeight, position: {x, y} }
            $table->text('button_style')->nullable()->after('button_text');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn('button_style');
        });
    }
};
