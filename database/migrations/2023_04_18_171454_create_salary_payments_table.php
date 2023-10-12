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
        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->onDelete('cascade')->onUpdate('cascade');
            $table->bigInteger("salary")->unsigned();
            $table->integer("paid")->unsigned();
            $table->integer("loan")->unsigned()->nullable();
            $table->integer("present")->nullable();
            $table->integer("absent")->nullable();
            $table->string("year_month")->nulllable();
            $table->bigInteger("deduction")->nullable();
            $table->text("description")->nullable();
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
        Schema::dropIfExists('salary_payments');
    }
};
