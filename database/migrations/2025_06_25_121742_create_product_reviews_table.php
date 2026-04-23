<?php

use App\Models\Product;
use App\Models\User;
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
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
             $table->string('title')->nullable();
            $table->text('comment')->nullable();
            $table->json('images')->nullable()->comment('Array of review images');
            $table->tinyInteger('rating')->unsigned()->default(5)->comment('rating from 1 to 5');
            $table->boolean('is_verified_purchase')->default(false);
            $table->boolean('is_approved')->default(true);
            $table->integer('helpful_count')->default(0);

            $table->foreignIdFor(User::class, 'user_id');
            $table->foreignIdFor(Product::class, 'product_id');
            $table->index('is_approved');
            $table->unique(['user_id', 'product_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};
