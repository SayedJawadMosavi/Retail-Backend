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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_name')->unique();
            $table->string('company_name');
            $table->string('unit_name');
            $table->string('code')->nullable();
            $table->string('size')->nullable();
            $table->string('color')->nullable();
            $table->double('quantity')->nullable()->unsigned();
            $table->double('carton_amount')->nullable()->unsigned();
            $table->double('carton_quantity')->nullable()->unsigned();
            $table->double('per_carton_cost')->default(0);
            $table->double('sell_price')->default(0);
            $table->unsignedBigInteger('category_id');
            $table->text('description')->nullable();
            $table->boolean('status')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
