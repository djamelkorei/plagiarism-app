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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('password');
            $table->enum('status', ['SUSPENDED', 'PENDING', 'ACTIVE'])->default('PENDING')->index('accounts_status');
            $table->enum('type', ['INSTRUCTOR', 'STUDENT']);
            $table->boolean('stateless')->default(false);
            $table->integer('class_id');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
