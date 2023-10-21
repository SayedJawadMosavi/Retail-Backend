<?php

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
        Schema::create('stock_to_stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sender_stock_id');
            $table->unsignedBigInteger('receiver_stock_id');
            $table->unsignedBigInteger('sender_stock_product_id');
            $table->unsignedBigInteger('receiver_stock_product_id');
            $table->double('quantity');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->onUpdate('cascade');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_to_stock_transfers');
    }
};
