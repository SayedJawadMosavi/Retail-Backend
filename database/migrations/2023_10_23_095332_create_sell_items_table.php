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
        Schema::create('sell_items', function (Blueprint $table) {
            $table->id();
            $table->double('cost');
            $table->double('quantity');
            $table->double('total');
            $table->double('carton_amount');
            $table->double('carton_quantity');
            $table->double('income_price');
            $table->double('per_carton_price');
            $table->foreignId('sell_id')->constrained()->onDelete('cascade')->onUpdate('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('product_stock_id');
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sell_items');
    }
};
