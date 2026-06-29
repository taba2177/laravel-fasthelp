<?php
// app/Services/ChatbotGeminiService.php
namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class ChatbotGeminiService
{
    protected Client $httpClient;
    protected string $generativeModel;
    protected string $embeddingModel;
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY');
        if (empty($this->apiKey)) {
            throw new \Exception('GEMINI_API_KEY environment variable is not set for Chatbot Service.');
        }
        $this->httpClient = new Client([
            'base_uri' => 'https://generativelanguage.googleapis.com/v1beta/',
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
        $this->generativeModel = 'gemini-1.5-flash'; // Or 'gemini-pro'
        $this->embeddingModel = 'embedding-001';
    }

    public function generateResponse(string $prompt, array $contextChunks = []): ?string
    {
        try {
            $fullPrompt = $prompt;
            if (!empty($contextChunks)) {
                $contextText = implode("\n\n---\n\n", array_map(function($chunk) {
                    return "Source: {$chunk->source_type} ({$chunk->source_identifier})\nContent: {$chunk->content}";
                }, $contextChunks));
                // $fullPrompt = "Based on the following context, answer the user's question. If the answer is not in the context, state that you don't know or cannot find the information within the provided context. Do not make up answers.\n\nContext:\n{$contextText}\n\nUser's question: {$prompt}";
                $fullPrompt = "بناءً على السياق التالي، أجب على سؤال المستخدم. يجب أن تكون الإجابة باللغة العربية **اللهجة السعودية**، وبأسلوب **موظف خدمة عملاء ودود ومختص**.
                * **أهم نقطة:** إذا كانت الإجابة **موجودة** في السياق، أجب عليها مباشرة ووضوح.
                * **ثاني أهم نقطة:** إذا كانت الإجابة **غير موجودة** في السياق، أو كان السؤال يخرج عن سياق الستائر أو يتطلب معلومات غير متوفرة (مثل الأسعار الدقيقة، الاستشارات الشخصية، أو حلول لمشاكل لا يمكن البت فيها عن بعد)، أو حسيت إن الرد ما يناسب كخدمة عملاء مباشرة؛ فاذكر بوضوح أنك لا تملك المعلومة ضمن السياق المتاح، و**اطلب من العميل الاتصال بنا** على الأرقام المحددة.
                * **رسالة الاتصال تكون بصيغة تشجع العميل:** 'عشان نخدمك بشكل أفضل ونعطيك تفاصيل دقيقة، ياليت تتصل على أرقامنا هذي: [أرقام التواصل]، فريقنا موجود عشان يساعدك ويجاوب على كل استفساراتك بشكل مباشر ومفصّل.'
                * **لا تذكر أنك لا تعرف المعلومة مباشرة في الرد الذي يحول للاتصال، بل ركز على أن الاتصال سيوفر خدمة أفضل.**
                * **لا تقم باختلاق الإجابات إطلاقاً، والتزم دائمًا بسياق الستائر وخدمة العملاء فقط ولا تخرج عنه أبداً.**
                * **مثال لردود غير مرغوبة:** 'أنا آسف، لكني لا أملك القدرة على تقديم أسعار محددة للستائر. للحصول على معلومات عن الأسعار، يُرجى الاتصال بمورد ستائر محلي أو زيارة متجر متخصص في بيع الستائر...' - **تجنب هذا النوع من الردود تمامًا.**
                السياق:\n{$contextText}\n\nسؤال المستخدم: {$prompt}";

            }

            $response = $this->httpClient->post("models/{$this->generativeModel}:generateContent?key={$this->apiKey}", [
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $fullPrompt]
                            ]
                        ]
                    ]
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        } catch (\Throwable $e) {
            Log::error("Gemini Response Generation Error: " . $e->getMessage(), ['prompt' => $prompt, 'error' => $e]);
            return null;
        }
    }

    public function generateEmbedding(string $text): ?array
    {
        try {
            $response = $this->httpClient->post("models/{$this->embeddingModel}:embedContent?key={$this->apiKey}", [
                'json' => [
                    'model' => "models/{$this->embeddingModel}",
                    'content' => [
                        'parts' => [
                            ['text' => $text]
                        ]
                    ]
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['embedding']['values'] ?? null;
        } catch (\Throwable $e) {
            Log::error("Gemini Embedding Generation Error: " . $e->getMessage(), ['text' => $text, 'error' => $e]);
            return null;
        }
    }

    public function generateRiveScriptRule(string $userQuery, string $riveScriptContent): ?string
    {
        try {
            $prompt = "You are an expert RiveScript programmer and bot trainer. Your task is to analyze a user's query in the context of the existing RiveScript brain and suggest the best way to improve the bot's response.

            Here is the current content of the RiveScript brain (brain/begin.rive):

            ```rivescript
            {$riveScriptContent}
            ```

            The user's query that the chatbot could not answer is: \"{$userQuery}\"

            Based on this query and the existing RiveScript content, determine the most appropriate action. Your goal is to make the bot's responses more accurate, varied, and natural.

            **Decision Logic:**
            1.  **NEW_RULE:** If the user's query represents a completely new concept or question not covered by any existing RiveScript patterns.
            2.  **MODIFY_PATTERN:** If the user's query is semantically similar to an existing RiveScript pattern, but the current pattern is too rigid and doesn't catch this specific phrasing. In this case, the existing pattern should be made more flexible using RiveScript features like wildcards (*, _), alternatives ((word1|word2)), and optional words ([word]).
            3.  **ADD_RESPONSE:** If the user's query matches an existing RiveScript pattern, but the current responses for that pattern are insufficient, incorrect, or lack variety. A new, more appropriate response should be added to that existing pattern.
            4.  **CLARIFY:** If the user's query is ambiguous, too vague, or requires more specific information to provide a helpful answer. In this case, the bot should ask for clarification.

            **Output Format:**
            Your output MUST be a JSON object with the following structure:
            ```json
            {
                \"action\": \"<ACTION_TYPE>\", // One of: NEW_RULE, MODIFY_PATTERN, ADD_RESPONSE, CLARIFY
                \"rivescript_code\": \"<RiveScript code snippet>\", // Required for NEW_RULE, MODIFY_PATTERN, ADD_RESPONSE. Escaped newlines (\n) are allowed.
                \"target_pattern\": \"<Existing RiveScript pattern>\" // Required for MODIFY_PATTERN, ADD_RESPONSE. The exact pattern to modify.
            }
            ```

            **RiveScript Code Guidelines:**
            *   For `NEW_RULE`: Provide a `+` line (flexible pattern) and one or more `-` lines (varied responses using {random} tags). Ensure responses are in Arabic (Saudi dialect) and professional.
            *   For `MODIFY_PATTERN`: Provide the `+` line with the *updated* flexible pattern. The responses should remain the same as the original rule.
            *   For `ADD_RESPONSE`: Provide the `+` line (the existing pattern) and the new `-` line(s) to be added. Use {random} tags if adding multiple new responses.
            *   For `CLARIFY`: Provide a `+` line (flexible pattern for the ambiguous query) and one or more `-` lines that ask for clarification in Arabic (Saudi dialect) using {random} tags.

            **Examples:**

            // Example for NEW_RULE (Conversational)
            // User Query: \"كيف حالك؟\"
            // Output:
            // {
            //     \"action\": \"NEW_RULE\",
            //     \"rivescript_code\": \"+ (كيف حالك|شلونك|كيفك)\n- {random}الحمدلله، بخير. وأنت؟ كيف حالك؟|أنا بخير، شكراً لسؤالك! كيف يمكنني مساعدتك؟{/random}\"
            // }

            // Example for MODIFY_PATTERN
            // Existing RiveScript: + كم سعر الستائر
            // User Query: \"كم تكلفة الستائر؟\"
            // Output:
            // {
            //     \"action\": \"MODIFY_PATTERN\",
            //     \"target_pattern\": \"كم سعر الستائر\",
            //     \"rivescript_code\": \"+ (كم|كم سعر|كم تكلفة) * (الستائر|الستاره)\"
            // }

            // Example for ADD_RESPONSE
            // Existing RiveScript: + سلام
            // - وعليكم السلام! يا هلا فيك. كيف أقدر أخدمك اليوم?
            // User Query: \"سلام\" (and the bot's response felt repetitive)
            // Output:
            // {
            //     \"action\": \"ADD_RESPONSE\",
            //     \"target_pattern\": \"سلام\",
            //     \"rivescript_code\": \"- أهلاً بك! كيف يمكنني مساعدتك اليوم?\"
            // }

            // Example for CLARIFY
            // User Query: \"تكلم عن الستائر\"
            // Output:
            // {
            //     \"action\": \"CLARIFY\",
            //     \"rivescript_code\": \"+ (تكلم عن|حدثني عن) * (الستائر|الستاره)\n- {random}هل يمكنك توضيح سؤالك عن الستائر؟ مثلاً، هل تبحث عن أنواع معينة، أسعار، أو خدمات تركيب؟|لأقدم لك المساعدة المناسبة، يرجى تحديد استفسارك عن الستائر بشكل أوضح. ما الذي تود معرفته بالتحديد؟{/random}\"
            // }

            **IMPORTANT:** Only output the JSON object. Do not include any other text, explanation, or formatting outside the JSON.
            ";

            $response = $this->httpClient->post("models/{$this->generativeModel}:generateContent?key={$this->apiKey}", [
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    // Add safety settings to get raw output if needed
                    'safetySettings' => [
                        [
                            'category' => 'HARM_CATEGORY_HARASSMENT',
                            'threshold' => 'BLOCK_NONE'
                        ],
                        [
                            'category' => 'HARM_CATEGORY_HATE_SPEECH',
                            'threshold' => 'BLOCK_NONE'
                        ],
                        [
                            'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                            'threshold' => 'BLOCK_NONE'
                        ],
                        [
                            'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                            'threshold' => 'BLOCK_NONE'
                        ]
                    ]
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            // Clean up the response to ensure it's valid JSON
            $responseText = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($responseText) {
                // Find the start and end of the JSON object
                $jsonStart = strpos($responseText, '{');
                $jsonEnd = strrpos($responseText, '}');
                if ($jsonStart !== false && $jsonEnd !== false) {
                    $jsonString = substr($responseText, $jsonStart, $jsonEnd - $jsonStart + 1);
                    // Decode the JSON string
                    $decodedJson = json_decode($jsonString, true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($decodedJson['action'])) {
                        return $jsonString; // Return the clean JSON string
                    }
                }
            }
            Log::warning('Gemini RiveScript Rule Generation: Could not extract valid JSON from response.', ['response' => $responseText]);
            return null;

        } catch (\Throwable $e) {
            Log::error("Gemini RiveScript Rule Generation Error: " . $e->getMessage(), ['query' => $userQuery, 'error' => $e]);
            return null;
        }
    }

    }
