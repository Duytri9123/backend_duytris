<?php

use App\Models\Discount;
use App\Models\PaymentMethod;
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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->onDelete('cascade');

            $table->string('order_number')->unique();

            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone');
            $table->text('shipping_address');
            $table->text('notes')->nullable();

            $table->decimal('subtotal', 12, 2);
            $table->decimal('shipping_fee', 12, 2)->default(0);

            $table->foreignIdFor(Discount::class)->nullable()->constrained()->onDelete('set null');

            $table->string('discount_code')->nullable();

            $table->decimal('discount_amount', 12, 2)->default(0);

            $table->decimal('grand_total', 12, 2);

            // --- Trạng thái đơn hàng & Thanh toán ---
            $table->foreignIdFor(PaymentMethod::class)->nullable()->constrained()->onDelete('set null');
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('unpaid');

            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
