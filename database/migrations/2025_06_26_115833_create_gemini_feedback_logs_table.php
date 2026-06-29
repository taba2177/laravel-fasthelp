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
        Schema::create('gemini_feedback_logs', function (Blueprint $table) {
            $table->id();
            $table->text('user_query');
            $table->json('gemini_response')->nullable();
            $table->json('context_chunks')->nullable(); // Store relevant chunks used
            $table->string('feedback_status')->default('pending'); // e.g., pending, approved, rejected, rivescript_added, kb_added
            $table->text('admin_feedback')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gemini_feedback_logs');
    }
};