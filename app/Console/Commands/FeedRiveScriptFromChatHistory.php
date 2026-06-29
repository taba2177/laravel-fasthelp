<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ChatHistory;
use Illuminate\Support\Facades\File;

class FeedRiveScriptFromChatHistory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rivescript:feed-from-chat-history';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Feeds RiveScript brain with chat history, avoiding duplicates.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $riveFilePath = base_path('brain/main.rive');

        if (!File::exists($riveFilePath)) {
            $this->error('main.rive not found at ' . $riveFilePath);
            return Command::FAILURE;
        }

        $existingRiveContent = File::get($riveFilePath);
        $existingTriggers = $this->parseRiveTriggers($existingRiveContent);

        $chatHistories = ChatHistory::all();
        $newEntriesCount = 0;
        $appendedContent = "\n"; // Start with a newline for separation

        foreach ($chatHistories as $history) {
            $userQuery = trim(strtolower($history->user_query));
            $botResponse = json_decode($history->bot_response, true)['text'] ?? 'No response';

            // Basic check for existing trigger
            if (!in_array($userQuery, $existingTriggers)) {
                $appendedContent .= "+ {$userQuery}\n";
                $appendedContent .= "- {$botResponse}\n\n";
                $newEntriesCount++;
                $existingTriggers[] = $userQuery; // Add to our in-memory list to avoid duplicates within this run
            }
        }

        if ($newEntriesCount > 0) {
            File::append($riveFilePath, $appendedContent);
            $this->info("Successfully added {$newEntriesCount} new entries to main.rive.");
        } else {
            $this->info('No new chat history entries to add to main.rive.');
        }

        return Command::SUCCESS;
    }

    /**
     * Parses RiveScript content to extract existing triggers.
     *
     * @param string $riveContent
     * @return array
     */
    private function parseRiveTriggers(string $riveContent): array
    {
        $triggers = [];
        // Regex to find lines starting with '+' (triggers)
        preg_match_all('/^\+\s*(.*?)$/m', $riveContent, $matches);

        foreach ($matches[1] as $match) {
            $triggers[] = trim(strtolower($match));
        }

        return $triggers;
    }
}
