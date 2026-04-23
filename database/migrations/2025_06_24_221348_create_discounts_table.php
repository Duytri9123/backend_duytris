<?php

use App\Models\Category;
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
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('type');
            $table->decimal('value', 12, 2);
            $table->decimal('min_order_value', 12, 2)->default(0);
            $table->unsignedInteger('max_quantity')->nullable();
            $table->unsignedInteger('quantity_used')->default(0);

            $table->string('applicable_to')->default('all');
            $table->foreignIdFor(Product::class, 'applicable_product_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignIdFor(Category::class, 'applicable_category_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
