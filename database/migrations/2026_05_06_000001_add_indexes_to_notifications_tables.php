<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Index cho admin_notifications — query thường order by created_at, filter by is_read
        Schema::table('admin_notifications', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('is_read');
        });

        // Index composite (user_id, created_at) cho user_notifications
        // Bảng đã có index (user_id, is_read) từ migration gốc
        // Thêm index cho created_at để ORDER BY nhanh hơn
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('admin_notifications', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['is_read']);
        });

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
        });
    }
};
