<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ChatbotSetupController extends Controller
{
    public function showSetupForm()
    {
        return view('chatbot.setup');
    }

    public function processSetup(Request $request)
    {
        $request->validate([
            'whatsapp_chat_file' => 'nullable|file|mimes:txt|max:10240', // Max 10MB
            'database_tables' => 'nullable|string',
        ]);

        $successMessages = [];
        $errorMessages = [];

        // Process WhatsApp Chat File
        if ($request->hasFile('whatsapp_chat_file')) {
            try {
                $filePath = $request->file('whatsapp_chat_file')->store('whatsapp_chats');
                Artisan::call('import:whatsapp-chat', ['filePath' => Storage::path($filePath)]);
                $successMessages[] = 'WhatsApp chat history imported successfully.';
            } catch (\Throwable $e) {
                Log::error("WhatsApp Import Error: " . $e->getMessage(), ['error' => $e]);
                $errorMessages[] = 'Failed to import WhatsApp chat history: ' . $e->getMessage();
            }
        }

        // Process Database Tables
        if ($request->filled('database_tables')) {
            $tables = array_map('trim', explode(',', $request->input('database_tables')));
            foreach ($tables as $table) {
                try {
                    Artisan::call('extract:table-data', ['table' => $table]);
                    $successMessages[] = "Data extracted from table '{$table}' successfully.";
                } catch (\Throwable $e) {
                    Log::error("Database Table Extraction Error for table {$table}: " . $e->getMessage(), ['error' => $e]);
                    $errorMessages[] = "Failed to extract data from table '{$table}': " . $e->getMessage();
                }
            }
        } else {
            // If no specific tables are provided, consider extracting from all relevant tables.
            // This part would require more advanced logic to discover and iterate through tables.
            // For now, we'll just log a message if no tables are specified.
            $successMessages[] = 'No specific database tables provided for extraction. Skipping.';
        }

        if (!empty($errorMessages)) {
            return redirect()->back()->with('error', implode("<br>", $errorMessages));
        } else {
            return redirect()->back()->with('success', implode("<br>", $successMessages));
        }
    }
    
}
