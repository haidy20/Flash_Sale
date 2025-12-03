<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('qty');
            $table->enum('status', ['active', 'used', 'expired', 'cancelled'])->default('active'); 
            $table->timestamp('expires_at'); 
            $table->timestamps();
            $table->index('expires_at'); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holds');
    }
};