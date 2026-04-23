<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateViewProductsTable extends Migration
{
    public function up()
    {
        Schema::create('view_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();

            // Đảm bảo mỗi user chỉ có một bản ghi cho mỗi sản phẩm
            $table->unique(['user_id', 'product_id']);

            // Khóa ngoại
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('view_products');
    }
}
