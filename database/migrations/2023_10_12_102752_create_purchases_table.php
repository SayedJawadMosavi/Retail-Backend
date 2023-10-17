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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('pending');
            $table->date('purchase_date');
            $table->text('description')->nullable();
            
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete()->onUpdate('cascade');
            $table->foreignId('container_id')->nullable()->constrained('containers')->nullOnDelete()->onUpdate('cascade');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
