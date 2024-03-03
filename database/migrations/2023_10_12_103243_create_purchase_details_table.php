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
        Schema::create('purchase_details', function (Blueprint $table) {
            $table->id();

            $table->text('description')->nullable();
            $table->double('yen_cost');
            $table->double('quantity');
            $table->double('total');
            $table->double('received')->nullable();
            $table->double('expense');
            $table->double('per_carton_cost');
            $table->double('sell_price');
            $table->double('carton_amount');
            $table->double('carton');
            $table->double('receive_carton')->nullable();
            $table->string('rate');
            $table->foreignId('purchase_id')->constrained()->onDelete('cascade')->onUpdate('cascade');
            $table->foreignId('vendor_id')->constrained()->onDelete('cascade')->onUpdate('cascade');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('cascade')->onUpdate('cascade');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_details');
    }
};
