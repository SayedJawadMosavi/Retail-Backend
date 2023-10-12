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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->text('profile')->nullable();
            $table->string('email')->nullable();
            $table->string('phone_number');
            $table->string('current_address')->nullable();
            $table->string('permenent_address')->nullable();
            $table->date('employment_start_date');
            $table->date('employment_end_date')->nullable();
            $table->string('job_title');
            $table->float('salary')->default(0);
            $table->float('loan')->default(0)->nullable();
            $table->string('employee_id_number')->nullable();
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
