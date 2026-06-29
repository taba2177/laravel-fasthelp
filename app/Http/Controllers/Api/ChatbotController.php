<?php
// app/Http/Controllers/Api/ChatbotController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatHistory;
use App\Models\KnowledgeBaseChunk;
use App\Services\ChatbotGeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Rive\RiveScript;
use Illuminate\Support\Str;
use App\Models\GeminiFeedbackLog; // Import the new model

class ChatbotController extends Controller
{
    protected ChatbotGeminiService $chatbotService;

    public function __construct(ChatbotGeminiService $chatbotService)
    {
        $this->chatbotService = $chatbotService;
    }

    public function chat(Request $request)
    {
        $userQuery = $request->input('query');
        try {
            $data = json_decode($request->getContent(), true);
            $request->merge($data);
            $request->validate(['query' => 'required|string|max:1000']);
            $userQuery = $request->input('query');
            $queryHash = md5($userQuery); // Simple hash for caching

            // 1. Check Cache
            $cachedResponse = ChatHistory::where('user_query_hash', $queryHash)
                                        ->where('expires_at', '>', now())
                                        ->first();

            if ($cachedResponse) {
                Log::info("Chatbot: Returning cached response for query: {$userQuery}");
                return response()->json(['response' => json_decode($cachedResponse->bot_response, true)]);
            }

            $botResponseText = null;
            $relevantChunks = collect();

            // 2. Try RiveScript first
            $rivescript = new RiveScript();
            $rivescript->load(base_path('brain')); // Load your main RiveScript file
            $riveReply = $rivescript->reply($userQuery);

            if ($riveReply !== "I don't have a reply for that.") {
                $botResponseText = $riveReply;
                Log::info("Chatbot: RiveScript replied to query: {$userQuery}");
            } else {
                // 3. Try simple keyword search in Knowledge Base
                $keywordMatch = KnowledgeBaseChunk::where('content', 'like', '%' . $userQuery . '%')->first();

                if ($keywordMatch) {
                    $botResponseText = $keywordMatch->content;
                    Log::info("Chatbot: Keyword match found in KB for query: {$userQuery}");
                    $relevantChunks->push($keywordMatch); // Add to relevant chunks for source display
                } else {
                    // 4. Generate Query Embedding for Knowledge Base and Gemini
                    $queryEmbedding = $this->chatbotService->generateEmbedding($userQuery);

                    if (!$queryEmbedding) {
                        return response()->json(['error' => 'Failed to generate query embedding.'], 500);
                    }

                    // 5. Retrieve Relevant Chunks from Knowledge Base (Conceptual - requires similarity search)
                    $allChunksWithEmbeddings = KnowledgeBaseChunk::whereNotNull('embedding')->get();

                    $relevantChunks = $allChunksWithEmbeddings->map(function($chunk) use ($queryEmbedding) {
                        $chunkEmbedding = json_decode($chunk->embedding, true);
                        if (!$chunkEmbedding) return null;
                        $similarity = $this->calculateCosineSimilarity($queryEmbedding, $chunkEmbedding);
                        $chunk->similarity = $similarity;
                        return $chunk;
                    })->filter()->sortByDesc('similarity')->take(5)->values();

                    // Define a similarity threshold. Adjust this value based on your data and desired strictness.
                    $similarityThreshold = config('chatbot.gemini_similarity_threshold');

                    if ($relevantChunks->isNotEmpty() && $relevantChunks->first()->similarity > $similarityThreshold) {
                        // If a highly relevant chunk is found, use it as context for Gemini
                        $botResponseText = $this->chatbotService->generateResponse($userQuery, $relevantChunks->all());
                        Log::info("Chatbot: Gemini replied with KB context for query: {$userQuery}");
                    } else {
                        // 6. Fallback to Gemini to TEACH the RiveScript brain
                        $riveBrainPath = base_path('brain/modules/learned.rive');
                        $riveScriptContent = file_get_contents($riveBrainPath);

                        $geminiResponse = $this->chatbotService->generateRiveScriptRule($userQuery, $riveScriptContent);
                        // Extract JSON from markdown code block if present
                        if (preg_match('/```json\n([\s\S]*?)\n```/', $geminiResponse, $matches)) {
                            $jsonString = $matches[1];
                        } else {
                            $jsonString = $geminiResponse;
                        }
                        $parsedResponse = json_decode($jsonString, true);

                        $feedbackStatus = 'failed_to_teach';
                        $logGeminiResponse = ['text' => $geminiResponse ?? 'No response from Gemini'];

                        if ($parsedResponse && isset($parsedResponse['action'])) {
                            $action = $parsedResponse['action'];
                            $rivescriptCode = $parsedResponse['rivescript_code'] ?? null;
                            $targetPattern = $parsedResponse['target_pattern'] ?? null;

                            switch ($action) {
                                case 'NEW_RULE':
                                    if ($rivescriptCode) {
                                        file_put_contents(base_path('brain/modules/learned.rive'), "\n\n" . $rivescriptCode, FILE_APPEND);
                                        Log::info("Chatbot: Gemini taught a NEW_RULE to RiveScript for query: {$userQuery}");
                                        $feedbackStatus = 'rivescript_new_rule';
                                        $rivescript->load(base_path('brain')); // Reload the brain
                                        $botResponseText = $rivescript->reply($userQuery);
                                    }
                                    break;

                                case 'MODIFY_PATTERN':
                                    if ($rivescriptCode && $targetPattern) {
                                        $riveScriptContent = preg_replace('/^\s*\+\s*' . preg_quote($targetPattern, '/') . '\s*$/m', '+' . $rivescriptCode, $riveScriptContent, 1, $count);
                                        if ($count > 0) {
                                            file_put_contents(base_path('brain/modules/learned.rive'), $riveScriptContent);
                                            Log::info("Chatbot: Gemini MODIFIED_PATTERN in RiveScript for query: {$userQuery}");
                                            $feedbackStatus = 'rivescript_modified_pattern';
                                            $rivescript->load(base_path('brain')); // Reload the brain
                                            $botResponseText = $rivescript->reply($userQuery);
                                        } else {
                                            Log::warning("Chatbot: MODIFY_PATTERN failed - target pattern not found for query: {$userQuery}");
                                            $botResponseText = 'نعتذر، لم نتمكن من فهم استفسارك في الوقت الحالي. يعمل فريقنا على تحسين الخدمة. للمساعدة، يرجى الاتصال بنا مباشرة.';
                                        }
                                    }
                                    break;

                                case 'ADD_RESPONSE':
                                    if ($rivescriptCode && $targetPattern) {
                                        $lines = explode("\n", $riveScriptContent);
                                        $newContent = [];
                                        $patternFound = false;
                                        foreach ($lines as $line) {
                                            $newContent[] = $line;
                                            if (preg_match('/^\s*\+\s*' . preg_quote($targetPattern, '/') . '\s*$/m', $line)) {
                                                $patternFound = true;
                                            }
                                            // If pattern found and current line is a response or blank, and next line is not a new pattern
                                            if ($patternFound && (trim($line) === '' || str_starts_with(trim($line), '-')) && !str_starts_with(trim($lines[array_search($line, $lines) + 1] ?? ''), '+')) {
                                                // Append new response after the last existing response for this pattern
                                                $newContent[] = $rivescriptCode; // This assumes $rivescriptCode is just the - response line
                                                $patternFound = false; // Reset after adding
                                            }
                                        }
                                        file_put_contents(base_path('brain/modules/learned.rive'), implode("\n", $newContent));
                                        Log::info("Chatbot: Gemini ADDED_RESPONSE to RiveScript for query: {$userQuery}");
                                        $feedbackStatus = 'rivescript_added_response';
                                        $rivescript->load(base_path('brain')); // Reload the brain
                                        $botResponseText = $rivescript->reply($userQuery);
                                    }
                                    break;


                                case 'CLARIFY':
                                    if ($rivescriptCode) {
                                        $botResponseText = $rivescriptCode; // Use the generated clarification as direct response
                                        Log::info("Chatbot: Gemini generated CLARIFY response for query: {$userQuery}");
                                        $feedbackStatus = 'rivescript_clarify';
                                    }
                                    break;

                                default:
                                    Log::warning("Chatbot: Unknown Gemini action: {$action} for query: {$userQuery}");
                                    $botResponseText = 'نعتذر، لم نتمكن من فهم استفسارك في الوقت الحالي. يعمل فريقنا على تحسين الخدمة. للمساعدة، يرجى الاتصال بنا مباشرة.';
                                    break;
                            }
                        } else {
                            Log::error("Chatbot: Gemini response not parsable or action missing for query: {$userQuery}");
                            $botResponseText = 'نعتذر، لم نتمكن من فهم استفسارك في الوقت الحالي. يعمل فريقنا على تحسين الخدمة. للمساعدة، يرجى الاتصال بنا مباشرة.';
                        }

                        // If botResponseText is still null, it means RiveScript didn't reply even after teaching
                        if (!$botResponseText || $botResponseText === "I don't have a reply for that.") {
                            $botResponseText = 'نعتذر، لم نتمكن من فهم استفسارك في الوقت الحالي. يعمل فريقنا على تحسين الخدمة. للمساعدة، يرجى الاتصال بنا مباشرة.';
                        }

                        // Log this interaction for feedback
                        GeminiFeedbackLog::create([
                            'user_query' => $userQuery,
                            'gemini_response' => $logGeminiResponse,
                            'context_chunks' => $relevantChunks->map(function($chunk) {
                                return [
                                    'type' => $chunk->source_type,
                                    'identifier' => $chunk->source_identifier,
                                    'content_snippet' => Str::limit($chunk->content, 100),
                                ];
                            })->toArray(),
                            'feedback_status' => $feedbackStatus,
                        ]);
                        DB::commit();
                    }
                }
            }

            if (!$botResponseText) {
                return response()->json(['error' => 'Failed to generate bot response.'], 500);
            }

            $responsePayload = [
                'text' => $botResponseText,
                'sources' => $relevantChunks->map(function($chunk) {
                    return [
                        'type' => $chunk->source_type,
                        'identifier' => $chunk->source_identifier,
                        'content_snippet' => Str::limit($chunk->content, 100),
                    ];
                })->toArray(),
            ];

            // 7. Cache Response
            ChatHistory::updateOrCreate(
                ['user_query_hash' => $queryHash],
                [
                    'user_query' => $userQuery,
                    'bot_response' => json_encode($responsePayload),
                    'expires_at' => now()->addHours(24), // Cache for 24 hours
                ]
            );

            Log::info("Chatbot: Generated and cached response for query: {$userQuery}");
            return response()->json(['response' => $responsePayload]);
        } catch (\Throwable $e) {
            Log::error("Chatbot API Error: " . $e->getMessage(), ['query' => $userQuery, 'error' => $e]);
            return response()->json(['error' => 'An unexpected error occurred. Please try again later.'], 500);
        }
    }

    private function calculateCosineSimilarity(array $vecA, array $vecB): float
    {
        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        for ($i = 0; $i < count($vecA); $i++) {
            $dotProduct += $vecA[$i] * $vecB[$i];
            $magnitudeA += $vecA[$i] * $vecA[$i];
            $magnitudeB += $vecB[$i] * $vecB[$i];
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA == 0.0 || $magnitudeB == 0.0) {
            return 0.0; // Avoid division by zero
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    public function rive(Request $request)
    {
        $request->validate(['message' => 'required|string|max:1000']);
        $message = $request->input('message');

        $rivescript = new RiveScript();
        $rivescript->load(base_path('brain/main.rive'));

        $reply = $rivescript->reply($message);

        return response()->json(['reply' => $reply]);
    }

    public function provideFeedback(Request $request, $logId)
    {
        $request->validate([
            'feedback_status' => 'required|string|in:approved,rejected,rivescript_added,kb_added',
            'admin_feedback' => 'nullable|string|max:1000',
        ]);

        $log = GeminiFeedbackLog::find($logId);

        if (!$log) {
            return response()->json(['message' => 'Log entry not found.'], 404);
        }

        $log->feedback_status = $request->input('feedback_status');
        $log->admin_feedback = $request->input('admin_feedback');
        $log->save();

        return response()->json(['message' => 'Feedback recorded successfully.', 'log' => $log]);
    }
}