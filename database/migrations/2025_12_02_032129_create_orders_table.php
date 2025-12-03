<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hold_id')->constrained()->onDelete('cascade')->unique(); 
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('qty');
            $table->unsignedDecimal('total_price', 8, 2);
            
            $table->enum('status', ['pending_payment', 'paid', 'cancelled'])->default('pending_payment'); 
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};