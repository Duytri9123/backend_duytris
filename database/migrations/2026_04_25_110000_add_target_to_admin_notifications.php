<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('admin_notifications', function (Blueprint $table) {
            $table->string('target')->default('admins')->after('link'); // all, users, admins
            $table->integer('recipients_count')->default(0)->after('target'); // số người nhận
        });
    }

    public function down(): void {
        Schema::table('admin_notifications', function (Blueprint $table) {
            $table->dropColumn(['target', 'recipients_count']);
        });
    }
};
