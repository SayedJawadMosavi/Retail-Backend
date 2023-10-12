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
        Schema::create('incoming_outgoings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->string('table')->nullable();
            $table->string('table_id')->nullable();
            $table->double('amount')->unsigned();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->onUpdate('cascade');
            $table->softDeletes();

            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('incoming_outgoings');
    }
};
