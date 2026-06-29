<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Chatbot;
use App\Models\ChatbotKnowledgeSource;
use App\Models\KnowledgeBaseChunk;

class ChatbotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a sample Chatbot
        $chatbot = Chatbot::create([
            'name' => 'General Chatbot',
            'description' => 'A general purpose chatbot.',
            'prompt_hint' => 'You are a helpful AI assistant.',
            'model_id' => 'gemini-pro',
            'temperature' => 0.7,
            'top_p' => 0.9,
            'top_k' => 40,
            'max_output_tokens' => 1000,
        ]);

        // Create sample KnowledgeBaseChunks
        $chunks = [
            [
                'content' => 'The capital of France is Paris.',
                'source_type' => 'fact',
                'source_identifier' => 'geography',
                'embedding' => json_encode(array_fill(0, 768, 0.1)), // Placeholder embedding
            ],
            [
                'content' => 'The highest mountain in the world is Mount Everest.',
                'source_type' => 'fact',
                'source_identifier' => 'geography',
                'embedding' => json_encode(array_fill(0, 768, 0.2)), // Placeholder embedding
            ],
            [
                'content' => 'The Earth revolves around the Sun.',
                'source_type' => 'fact',
                'source_identifier' => 'astronomy',
                'embedding' => json_encode(array_fill(0, 768, 0.3)), // Placeholder embedding
            ],
            [
                'content' => 'The primary colors are red, yellow, and blue.',
                'source_type' => 'fact',
                'source_identifier' => 'art',
                'embedding' => json_encode(array_fill(0, 768, 0.4)), // Placeholder embedding
            ],
            [
                'content' => 'Water boils at 100 degrees Celsius at sea level.',
                'source_type' => 'fact',
                'source_identifier' => 'science',
                'embedding' => json_encode(array_fill(0, 768, 0.5)), // Placeholder embedding
            ],
        ];

        foreach ($chunks as $chunkData) {
            KnowledgeBaseChunk::create($chunkData);
        }

        // Attach knowledge sources to the chatbot (if applicable)
        // For example, if you have a many-to-many relationship between chatbots and knowledge sources
        // $chatbot->knowledgeSources()->attach([1, 2, 3]); // Assuming IDs 1, 2, 3 exist
    }
}
