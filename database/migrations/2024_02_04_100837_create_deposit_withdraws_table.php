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
        Schema::create('deposit_withdraws', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->double('amount')->unsigned();
            $table->string('table')->nullable()->default('direct');
            $table->string('table_id')->nullable();
            $table->string('status')->default('direct')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->onUpdate('cascade');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete()->onUpdate('cascade');
            $table->softDeletes();
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposit_withdraws');
    }
};
