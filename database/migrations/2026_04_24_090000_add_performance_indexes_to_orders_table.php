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
        Schema::table('orders', function (Blueprint $table) {
            // Index cho status để filter nhanh (delivered, completed, etc)
            $table->index('status');
            // Index cho created_at để tính toán theo tháng/năm nhanh hơn
            $table->index('created_at');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Index cho foreign key để join nhanh khi tính top sản phẩm
            if (Schema::hasColumn('order_items', 'product_id')) {
                $table->index('product_id');
            }
            // Thêm quantity nếu thực sự chưa có (phòng trường hợp migration cũ bị lỗi)
            if (!Schema::hasColumn('order_items', 'quantity')) {
                $table->integer('quantity')->default(1)->after('price');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'product_id')) {
                $table->dropIndex(['product_id']);
            }
        });
    }
};
