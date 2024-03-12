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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('profile')->nullable();
            $table->string('email')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('type')->nullable();
            $table->string('tazkira_number')->unique()->nullable();
            $table->text('address')->nullable();
            $table->text('description')->nullable();
            $table->double('total_amount')->default(0);
            $table->double('total_paid')->default(0);
            $table->boolean('status')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
