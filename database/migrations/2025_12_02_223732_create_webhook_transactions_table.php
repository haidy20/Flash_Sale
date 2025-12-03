<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('webhook_transactions', function (Blueprint $table) {
            $table->id();
            
            $table->string('idempotency_key')->unique();
            
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            
            $table->timestamp('processed_at');
            $table->boolean('is_successful')->comment('True if payment was successful');

            $table->index('order_id');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('webhook_transactions');
    }
};