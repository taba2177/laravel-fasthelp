<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\GeminiFeedbackLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class ProcessApprovedFeedback extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chatbot:process-feedback';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Processes approved Gemini feedback and updates the RiveScript brain.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to process approved feedback...');

        $approvedLogs = GeminiFeedbackLog::where('feedback_status', 'rivescript_new_rule')->get();

        if ($approvedLogs->isEmpty()) {
            $this->info('No new approved feedback to process.');
            return;
        }

        $riveBrainPath = base_path('brain/modules/learned.rive');
        $riveScriptContent = File::get($riveBrainPath);

        foreach ($approvedLogs as $log) {
            $this->info("Processing log ID: {$log->id}");

            $geminiResponse = $log->gemini_response;
            if (is_string($geminiResponse)) {
                // Extract JSON from markdown code block if present
                if (preg_match('/```json\n([\s\S]*?)\n```/', $geminiResponse, $matches)) {
                    $jsonString = $matches[1];
                } else {
                    $jsonString = $geminiResponse;
                }
                $geminiResponse = json_decode($jsonString, true);
            } elseif (is_array($geminiResponse) && isset($geminiResponse['text'])) {
                // If it's already an array but contains a 'text' key with a JSON string
                if (preg_match('/```json\n([\s\S]*?)\n```/', $geminiResponse['text'], $matches)) {
                    $jsonString = $matches[1];
                } else {
                    $jsonString = $geminiResponse['text'];
                }
                $geminiResponse = json_decode($jsonString, true);
            } elseif (is_array($geminiResponse) && isset($geminiResponse['text'])) {
                // If it's already an array but contains a 'text' key with a JSON string
                if (preg_match('/```json\n([\s\S]*?)\n```/', $geminiResponse['text'], $matches)) {
                    $jsonString = $matches[1];
                } else {
                    $jsonString = $geminiResponse['text'];
                }
                $geminiResponse = json_decode($jsonString, true);
            }

            $action = $geminiResponse['action'] ?? null;
            $rivescriptCode = $geminiResponse['rivescript_code'] ?? null;
            $targetPattern = $geminiResponse['target_pattern'] ?? null;

            if (!$action || !$rivescriptCode) {
                $this->error("Skipping log ID: {$log->id} due to missing action or rivescript_code.");
                $log->feedback_status = 'processed_error';
                $log->save();
                continue;
            }

            $updated = false;
            switch ($action) {
                case 'NEW_RULE':
                    if (strpos($riveScriptContent, $rivescriptCode) === false) {
                        File::append($riveBrainPath, "\n\n" . $rivescriptCode);
                        $riveScriptContent = File::get($riveBrainPath); // Reload content
                        $updated = true;
                        $this->info("  - Applied NEW_RULE for log ID: {$log->id}");
                    } else {
                        $this->warn("  - Skipped NEW_RULE for log ID: {$log->id} (duplicate found).");
                    }
                    break;

                case 'MODIFY_PATTERN':
                    if ($targetPattern) {
                        $newRiveScriptContent = preg_replace(
                            '/^\s*\+\s*' . preg_quote($targetPattern, '/') . '\s*$/m',
                            '+' . $rivescriptCode,
                            $riveScriptContent,
                            1,
                            $count
                        );
                        if ($count > 0) {
                            File::put($riveBrainPath, $newRiveScriptContent);
                            $riveScriptContent = $newRiveScriptContent; // Update content for subsequent operations
                            $updated = true;
                            $this->info("  - Applied MODIFY_PATTERN for log ID: {$log->id}");
                        } else {
                            $this->error("  - MODIFY_PATTERN failed for log ID: {$log->id} (target pattern not found).");
                        }
                    } else {
                        $this->error("  - Skipped MODIFY_PATTERN for log ID: {$log->id} (missing target_pattern).");
                    }
                    break;

                case 'ADD_RESPONSE':
                    if ($targetPattern && str_starts_with(trim($rivescriptCode), '-')) {
                        $lines = explode("\n", $riveScriptContent);
                        $newContent = [];
                        $responseAdded = false;
                        foreach ($lines as $line) {
                            $newContent[] = $line;
                            if (preg_match('/^\s*\+\s*' . preg_quote($targetPattern, '/') . '\s*$/m', $line)) {
                                // Append new response directly after the pattern
                                $newContent[] = trim($rivescriptCode);
                                $responseAdded = true;
                            }
                        }

                        if ($responseAdded) {
                            File::put($riveBrainPath, implode("\n", $newContent));
                            $riveScriptContent = implode("\n", $newContent); // Reload
                            $updated = true;
                            $this->info("  - Applied ADD_RESPONSE for log ID: {$log->id}");
                        } else {
                            $this->error("  - ADD_RESPONSE failed for log ID: {$log->id} (target pattern not found).");
                        }
                    } else {
                        $this->error("  - Skipped ADD_RESPONSE for log ID: {$log->id} (missing target_pattern or invalid rivescript_code).");
                    }
                    break;
            }

            if ($updated) {
                $log->feedback_status = 'processed_success';
            } else {
                $log->feedback_status = 'processed_skipped';
            }
            $log->save();
        }

        // Reload RiveScript brain once after all logs are processed
        $rivescript = new \App\Rive\RiveScript();
        $rivescript->load(base_path('brain'));

        $this->info('Finished processing feedback.');
    }
}