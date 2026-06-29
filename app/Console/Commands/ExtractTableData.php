<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\KnowledgeBaseChunk;
use App\Services\ChatbotGeminiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExtractTableData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'extract:table-data {table} {--columns= : Comma-separated list of columns to extract}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extracts data from a database table and stores it in the knowledge base.';

    protected ChatbotGeminiService $chatbotService;

    public function __construct(ChatbotGeminiService $chatbotService)
    {
        parent::__construct();
        $this->chatbotService = $chatbotService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tableName = $this->argument('table');
        $columns = $this->option('columns');

        if (!Schema::hasTable($tableName)) {
            $this->error("Table '{$tableName}' does not exist.");
            return Command::FAILURE;
        }

        $this->info("Extracting data from table: {$tableName}");

        $query = DB::table($tableName);

        if ($columns) {
            $columns = explode(',', $columns);
            // Ensure all specified columns exist in the table
            foreach ($columns as $column) {
                if (!Schema::hasColumn($tableName, $column)) {
                    $this->error("Column '{$column}' does not exist in table '{$tableName}'.");
                    return Command::FAILURE;
                }
            }
            $query->select($columns);
        } else {
            // If no columns specified, get all string/text columns
            $columns = Schema::getColumnListing($tableName);
            $stringColumns = [];
            foreach ($columns as $column) {
                $type = Schema::getColumnType($tableName, $column);
                if (in_array($type, ['string', 'text', 'mediumtext', 'longtext'])) {
                    $stringColumns[] = $column;
                }
            }
            $query->select($stringColumns);
            $columns = $stringColumns; // Update columns to only include string columns
        }

        $records = $query->get();
        $chunkCounter = 0;

        foreach ($records as $record) {
            $content = '';
            foreach ($columns as $column) {
                if (isset($record->$column)) {
                    $content .= $column . ': ' . $record->$column . "\n";
                }
            }

            if (!empty($content)) {
                DB::transaction(function () use ($content, $tableName, & $chunkCounter) {
                    $embedding = $this->chatbotService->generateEmbedding($content);

                    KnowledgeBaseChunk::create([
                        'content' => trim($content),
                        'source_type' => 'database',
                        'source_identifier' => $tableName,
                        'embedding' => $embedding ? json_encode($embedding) : null,
                    ]);
                    $chunkCounter++;
                });
            }
        }

        $this->info('Successfully extracted ' . $chunkCounter . ' chunks from table ' . $tableName . '.');

        return Command::SUCCESS;
    }
}