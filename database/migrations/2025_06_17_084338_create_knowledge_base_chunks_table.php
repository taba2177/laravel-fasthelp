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
        Schema::create('knowledge_base_chunks', function (Blueprint $table) {
        $table->id();
        $table->text('content')->comment('A chunk of text from WhatsApp or website data');
        $table->string('source_type')->comment('e.g., whatsapp, website_product, website_page');
        $table->string('source_identifier')->nullable()->comment('e.g., WhatsApp chat ID, product SKU');
        // If you are storing embeddings directly in MySQL (not recommended for very large scale)
        // $table->json('embedding')->nullable()->comment('Vector embedding of the content chunk');
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_chunks');
    }

};
