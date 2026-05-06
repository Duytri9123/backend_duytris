<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('message');
            $table->string('type')->default('info'); // order, product, review, system, info
            $table->boolean('is_read')->default(false);
            $table->string('link')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'is_read']);
        });
    }
    public function down(): void { Schema::dropIfExists('user_notifications'); }
};
