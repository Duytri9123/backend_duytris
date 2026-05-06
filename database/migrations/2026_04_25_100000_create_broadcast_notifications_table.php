<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('broadcast_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->string('type')->default('info'); // info, system, promo, order, product
            $table->string('link')->nullable();
            $table->string('color')->nullable(); // custom accent color
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable(); // tự hết hạn
            $table->timestamps();
            $table->index(['is_active', 'created_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('broadcast_notifications');
    }
};
