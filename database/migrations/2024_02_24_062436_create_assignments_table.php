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
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('reference')->unique();
            $table->string('class_reference')->unique();
            $table->enum('status', ['WAITING', 'PENDING', 'COMPLETED', 'IGNORED', ''])->index('assignments_status');
            $table->string('file_link');
            $table->string('download_link');
            $table->enum('source', ['API', 'WEB'])->default('API');
            $table->timestamp('posted_at');
            $table->timestamp('issued_at');
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
