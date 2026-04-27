<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessagingApiAndWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_can_create_direct_conversation_and_send_and_poll_messages(): void
    {
        $a = User::factory()->create(['phone_verified_at' => now()]);
        $b = User::factory()->create(['phone_verified_at' => now()]);

        Sanctum::actingAs($a);

        $resp = $this->postJson('/api/conversations/direct', [
            'recipient_user_id' => $b->id,
        ])->assertCreated();

        $conversationId = (int) $resp->json('conversation_id');
        $this->assertGreaterThan(0, $conversationId);

        $this->postJson("/api/conversations/{$conversationId}/messages", [
            'body' => 'hello there',
        ])->assertCreated();

        $poll = $this->getJson("/api/conversations/{$conversationId}/messages?after_id=0&limit=50")
            ->assertOk()
            ->json('messages');

        $this->assertNotEmpty($poll);
        $this->assertSame('hello there', $poll[0]['body']);
    }

    public function test_web_messages_pages_render_for_participants_and_sending_creates_message(): void
    {
        $a = User::factory()->create(['phone_verified_at' => now()]);
        $b = User::factory()->create(['phone_verified_at' => now()]);

        $conversation = Conversation::query()->create(['type' => 'direct']);
        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $a->id,
            'joined_at' => now(),
        ]);
        ConversationParticipant::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $b->id,
            'joined_at' => now(),
        ]);

        $this->actingAs($a)
            ->get('/messages')
            ->assertOk()
            ->assertSee('Messages');

        $this->actingAs($a)
            ->get("/messages/{$conversation->id}")
            ->assertOk();

        $this->actingAs($a)
            ->post("/messages/{$conversation->id}", [
                'body' => 'web hi',
            ])
            ->assertRedirect("/messages/{$conversation->id}");

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $a->id,
            'body' => 'web hi',
        ]);
    }

    public function test_non_participant_cannot_fetch_messages(): void
    {
        $a = User::factory()->create(['phone_verified_at' => now()]);
        $b = User::factory()->create(['phone_verified_at' => now()]);
        $c = User::factory()->create(['phone_verified_at' => now()]);

        $conversation = Conversation::query()->create(['type' => 'direct']);
        ConversationParticipant::query()->create(['conversation_id' => $conversation->id, 'user_id' => $a->id, 'joined_at' => now()]);
        ConversationParticipant::query()->create(['conversation_id' => $conversation->id, 'user_id' => $b->id, 'joined_at' => now()]);

        Sanctum::actingAs($c);
        $this->getJson("/api/conversations/{$conversation->id}/messages")->assertForbidden();

        $this->actingAs($c)->get("/messages/{$conversation->id}")->assertForbidden();
    }

    public function test_block_prevents_sending_messages(): void
    {
        $a = User::factory()->create(['phone_verified_at' => now()]);
        $b = User::factory()->create(['phone_verified_at' => now()]);

        $conversation = Conversation::query()->create(['type' => 'direct']);
        ConversationParticipant::query()->create(['conversation_id' => $conversation->id, 'user_id' => $a->id, 'joined_at' => now()]);
        ConversationParticipant::query()->create(['conversation_id' => $conversation->id, 'user_id' => $b->id, 'joined_at' => now()]);

        Sanctum::actingAs($a);
        $this->postJson("/api/users/{$b->id}/block")->assertOk();

        $this->postJson("/api/conversations/{$conversation->id}/messages", [
            'body' => 'should fail',
        ])->assertForbidden();

        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $conversation->id,
            'body' => 'should fail',
        ]);
    }
}

