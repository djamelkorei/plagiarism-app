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
            $table->enum('status', ['PENDING', 'COMPLETED', 'IGNORED'])->index('assignments_status');
            $table->string('file_link');
            $table->string('download_link')->nullable();
            $table->enum('source', ['API', 'WEB'])->default('API');
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('attribution_id')->nullable()->constrained();
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
