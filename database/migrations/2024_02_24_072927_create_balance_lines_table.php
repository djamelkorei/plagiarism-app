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
        Schema::create('balance_lines', function (Blueprint $table) {
            $table->id();
            $table->integer('credit')->default(0);
            $table->integer('value')->default(0);
            $table->enum('status', ['PENDING', 'APPROVED', 'REFUSED']);
            $table->foreignId('balance_id')->constrained();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balance_lines');
    }
};
