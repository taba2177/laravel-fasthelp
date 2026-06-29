<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\KnowledgeBaseChunk;
use App\Services\ChatbotGeminiService;
use Illuminate\Support\Facades\DB;

class ImportWhatsappChat extends Command
{
    protected $signature = 'import:whatsapp-chat {filePath}';
    protected $description = 'Imports WhatsApp chat history from a text file into the database.';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('filePath');

        if (!file_exists($filePath)) {
            $this->error('File not found: ' . $filePath);
            return Command::FAILURE;
        }

        $this->info('Importing WhatsApp chat from: ' . $filePath);

        $pythonScriptPath = base_path('scripts/whatsapp_parser.py');

        if (!file_exists($pythonScriptPath)) {
            $this->error('Python parser script not found: ' . $pythonScriptPath);
            return Command::FAILURE;
        }

        // Execute the Python script
        $command = ['python', $pythonScriptPath, $filePath];
        $process = new \Symfony\Component\Process\Process($command);
        $process->setTimeout(3600); // Set a timeout for long files

        try {
            $process->run(function ($type, $buffer) {
                if (\Symfony\Component\Process\Process::OUT === $type) {
                    $this->info($buffer);
                } else {
                    $this->error($buffer);
                }
            });

            if (!$process->isSuccessful()) {
                $this->error('Python script execution failed.');
                $this->error($process->getErrorOutput());
                return Command::FAILURE;
            }

            $this->info('WhatsApp chat imported successfully into the database.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error executing Python script: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}