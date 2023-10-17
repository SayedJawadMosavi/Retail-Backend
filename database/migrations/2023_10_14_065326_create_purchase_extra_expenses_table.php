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
        Schema::create('purchase_extra_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->double('price')->unsigned();
            $table->foreignId('purchase_id')->constrained()->OnDelete('cascade')->onUpdate('cascade');
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete()->onUpdate('cascade');
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
        Schema::dropIfExists('purchase_extra_expenses');
    }
};
