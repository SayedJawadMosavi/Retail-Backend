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
        Schema::create('employee_loans', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->double('amount')->unsigned();
            $table->string('table')->nullable()->default('direct');
            $table->string('table_id')->nullable();
            $table->string('status')->default('direct')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->onUpdate('cascade');
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete()->onUpdate('cascade');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_loans');
    }
};
