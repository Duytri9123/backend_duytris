<?php

use App\Models\Order;
use App\Models\PaymentMethod;
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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Order::class)->constrained()->onDelete('cascade');
            $table->foreignIdFor(PaymentMethod::class)->nullable()->constrained()->onDelete('set null');

            $table->decimal('amount', 12, 2);
            $table->timestamp('payment_date');

            $table->string('provider_transaction_id')->nullable();

            $table->string('status');

            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
