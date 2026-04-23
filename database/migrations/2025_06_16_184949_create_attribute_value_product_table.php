<?php

use App\Models\AttributeValue;
use App\Models\Product;
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
        Schema::create('attribute_value_product', function (Blueprint $table) {
            $table->id();
           $table->foreignIdFor(Product::class)
                  ->constrained()
                  ->onDelete('cascade');

            // Liên kết đến giá trị thuộc tính được phép
            $table->foreignIdFor(AttributeValue::class)
                  ->constrained()
                  ->onDelete('cascade');

            // Đảm bảo một sản phẩm không có 2 lần cùng một tùy chọn
            $table->unique(['product_id', 'attribute_value_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attribute_value_product');
    }
};
