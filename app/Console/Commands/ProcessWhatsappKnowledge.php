<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\WhatsappMessage;
use App\Models\KnowledgeBaseChunk;
use App\Services\ChatbotGeminiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessWhatsappKnowledge extends Command
{
    protected $signature = 'app:process-whatsapp-knowledge';
    protected $description = 'Processes unprocessed WhatsApp messages and adds them to the knowledge base.';

    protected ChatbotGeminiService $chatbotService;

    public function __construct(ChatbotGeminiService $chatbotService)
    {
        parent::__construct();
        $this->chatbotService = $chatbotService;
    }

    public function handle()
    {
        $this->info('Starting WhatsApp knowledge processing...');

        $unprocessedMessages = WhatsappMessage::where('processed', false)->get();

        if ($unprocessedMessages->isEmpty()) {
            $this->info('No unprocessed WhatsApp messages found.');
            return Command::SUCCESS;
        }

        $progressBar = $this->output->createProgressBar(count($unprocessedMessages));
        $progressBar->start();

        foreach ($unprocessedMessages as $message) {
            DB::transaction(function () use ($message, $progressBar) {
                $content = $message->message;
                $sourceIdentifier = 'whatsapp_message_' . $message->id;

                // Generate embedding for the message content
                $embedding = $this->chatbotService->generateEmbedding($content);

                if ($embedding) {
                    KnowledgeBaseChunk::create([
                        'content' => $content,
                        'source_type' => 'whatsapp',
                        'source_identifier' => $sourceIdentifier,
                        'embedding' => json_encode($embedding),
                    ]);

                    // Mark message as processed
                    $message->processed = true;
                    $message->save();
                } else {
                    $this->warn("Could not generate embedding for message ID: {$message->id}. Skipping.");
                }
                $progressBar->advance();
            });
        }

        $progressBar->finish();
        $this->info('\nWhatsApp knowledge processing completed.');

        return Command::SUCCESS;
    }
}
