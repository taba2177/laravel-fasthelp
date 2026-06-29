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
        Schema::create('chatbot_knowledge_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chatbot_id')->constrained()->onDelete('cascade');
            $table->string('source_type'); // e.g., 'database_table', 'whatsapp_chat', 'file_system'
            $table->string('source_identifier'); // e.g., 'products', 'whatsapp_group_id_123', '/docs/faq.txt'
            $table->timestamps();

            $table->unique(['chatbot_id', 'source_type', 'source_identifier'], 'chatbot_source_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chatbot_knowledge_sources');
    }
};
