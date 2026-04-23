<?php

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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->onDelete('cascade');

            // Thông tin chi tiết của người nhận tại địa chỉ này
            $table->string('receiver_name');
            $table->string('receiver_phone');

            // Thông tin địa chỉ chi tiết
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('ward_code');
            $table->string('district_code');
            $table->string('city_code');
            $table->text('full_address')->nullable();

            // Cờ để xác định đây có phải là địa chỉ mặc định không
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
