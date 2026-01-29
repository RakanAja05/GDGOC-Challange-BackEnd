<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ConversationMessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $agent = User::query()->where('email', 'agent@example.com')->first();

        if (! $agent) {
            $agent = User::factory()->create([
                'name' => 'Support Agent',
                'email' => 'agent@example.com',
                'role' => 'agent',
            ]);
        }

        $customers = [
            ['name' => 'Andi Prasetyo', 'email' => 'andi@example.com'],
            ['name' => 'Siti Rahma', 'email' => 'siti@example.com'],
        ];

        foreach ($customers as $index => $customerData) {
            $customerUser = User::query()->where('email', $customerData['email'])->first();
            if (! $customerUser) {
                $customerUser = User::factory()->create([
                    'name' => $customerData['name'],
                    'email' => $customerData['email'],
                    'role' => 'user',
                ]);
            }

            $issueCategory = $index % 2 === 0 ? 'auth' : 'billing';
            $sentiment = $index % 2 === 0 ? 'negative' : 'neutral';
            $sentimentScore = $index % 2 === 0 ? -0.65 : -0.05;
            $priority = $index % 2 === 0 ? 'high' : 'medium';

            $conversation = Conversation::create([
                'user_id' => $customerUser->id,
                'status' => 'open',
                'priority' => $priority,
                'issue_category' => $issueCategory,
                'sentiment' => $sentiment,
                'sentiment_score' => $sentimentScore,
            ]);

            $messages = [
                [
                    'sender_id' => $customerUser->id,
                    'sender_role' => 'user',
                    'content' => 'Halo, saya butuh bantuan untuk akun saya.',
                    'created_at' => Carbon::now()->subMinutes(12),
                ],
                [
                    'sender_id' => $agent->id,
                    'sender_role' => 'agent',
                    'content' => 'Halo! Tentu, bisa jelaskan masalahnya?',
                    'created_at' => Carbon::now()->subMinutes(10),
                ],
                [
                    'sender_id' => $customerUser->id,
                    'sender_role' => 'user',
                    'content' => 'Saya tidak bisa login sejak pagi.',
                    'created_at' => Carbon::now()->subMinutes(7),
                ],
                [
                    'sender_id' => $agent->id,
                    'sender_role' => 'agent',
                    'content' => 'Baik, saya cek dulu. Mohon tunggu sebentar ya.',
                    'created_at' => Carbon::now()->subMinutes(5),
                ],
            ];

            foreach ($messages as $message) {
                Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $message['sender_id'],
                    'content' => $message['content'],
                    'created_at' => $message['created_at'],
                ]);
            }

            $lastMessage = end($messages);

            $conversation->update([
                'last_message_from' => $lastMessage['sender_role'],
                'last_message_at' => $lastMessage['created_at'],
            ]);
        }
    }
}
