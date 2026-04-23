<?php

use App\Models\AttributeValue;
use App\Models\ProductVariant;
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
        Schema::create('attribute_product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ProductVariant::class)
                ->constrained()
                ->onDelete('cascade');

            // Liên kết đến giá trị thuộc tính cấu thành nên biến thể đó
            $table->foreignIdFor(AttributeValue::class)
                ->constrained()
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attribute_product_variants');
    }
};
