<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
    

    

    

    

    /**
     * Test that the chatbot returns a response for a known query.
     *
     * @return void
     */
    public function testChatbotReturnsKnownResponse()
    {
        // Seed the RiveScript brain with a known rule
        file_put_contents(base_path('brain/modules/test_rules.rive'), "+ hello bot\n- Hello there!\n");

        $response = $this->postJson('/api/chat', ['query' => 'hello bot']);

        $response->assertStatus(200)
                 ->assertJson(['response' => ['text' => 'Hello there!']]);
    }

    /**
     * Test that the chatbot returns a fallback response for an unknown query.
     *
     * @return void
     */
    public function testChatbotReturnsFallbackResponse()
    {
        $response = $this->postJson('/api/chat', ['query' => 'unknown query']);

        $response->assertStatus(200)
                 ->assertJson(['response' => ['text' => '{random}أنا آسف، لم أفهم سؤالك. هل يمكنك إعادة صياغته؟|أنا آسف، لا أفهم ما تقصده. هل يمكنك أن تكون أكثر تحديدًا؟|عفواً، لم أفهم سؤالك.  يرجى إعادة طرحه بطريقة أخرى. |لا أفهم سؤالك، هل يمكنك إعادة صياغته بشكل أوضح؟{/random}']]);
    }

    /**
     * Test that the chatbot logs feedback for unknown queries.
     *
     * @return void
     */
    public function testChatbotLogsFeedbackForUnknownQuery()
    {
        $this->postJson('/api/chat', ['query' => 'another unknown query']);

        $this->assertDatabaseHas('gemini_feedback_logs', [
            'user_query' => 'another unknown query',
            'feedback_status' => 'rivescript_new_rule',
        ]);
    }
}
