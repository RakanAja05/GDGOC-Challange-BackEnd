<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ConversationMessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $agent1 = User::query()->where('email', 'agent@example.com')->first();
        if (! $agent1) {
            $attrs = [
                'name' => 'Support Agent',
                'email' => 'agent@example.com',
                'email_verified_at' => $now,
            ];
            if (Schema::hasColumn('users', 'role')) {
                $attrs['role'] = 'agent';
            }
            $agent1 = User::factory()->create($attrs);
        }

        $agent2 = User::query()->where('email', 'agent2@example.com')->first();
        if (! $agent2) {
            $attrs = [
                'name' => 'Support Agent 2',
                'email' => 'agent2@example.com',
                'email_verified_at' => $now,
            ];
            if (Schema::hasColumn('users', 'role')) {
                $attrs['role'] = 'agent';
            }
            $agent2 = User::factory()->create($attrs);
        }

        $lead = User::query()->where('email', 'lead@example.com')->first();
        if (! $lead) {
            $attrs = [
                'name' => 'Support Lead',
                'email' => 'lead@example.com',
                'email_verified_at' => $now,
            ];
            if (Schema::hasColumn('users', 'role')) {
                $attrs['role'] = 'lead';
            }
            $lead = User::factory()->create($attrs);
        }

        $agents = [$agent1, $agent2, $lead];

        $customers = [
            ['name' => 'Andi Prasetyo', 'email' => 'andi@example.com'],
            ['name' => 'Siti Rahma', 'email' => 'siti@example.com'],
            ['name' => 'Budi Santoso', 'email' => 'budi@example.com'],
            ['name' => 'Nadia Putri', 'email' => 'nadia@example.com'],
            ['name' => 'Rizky Mahendra', 'email' => 'rizky@example.com'],
            // Edge case: customer exists but no conversations yet
            ['name' => 'No Conversation User', 'email' => 'noconv@example.com'],
        ];

        $scenarios = [
            [
                'status' => 'open',
                'priority' => 'high',
                'issue_category' => 'auth',
                'sentiment' => 'negative',
                'sentiment_score' => -0.85,
                'messages' => [
                    ['from' => 'customer', 'text' => 'Halo, saya tidak bisa login sejak pagi. Selalu gagal walaupun password benar.'],
                    ['from' => 'agent', 'text' => 'Halo! Aku bantu cek ya. Bisa info email akun dan error yang muncul?'],
                    ['from' => 'customer', 'text' => 'Email saya sama seperti yang saya pakai chat ini. Error: "invalid credentials".'],
                ],
            ],
            [
                'status' => 'pending',
                'priority' => 'medium',
                'issue_category' => 'billing',
                'sentiment' => 'neutral',
                'sentiment_score' => -0.10,
                'messages' => [
                    ['from' => 'customer', 'text' => 'Tagihan bulan ini kok dobel ya? Saya lihat ada 2 transaksi.'],
                    ['from' => 'agent', 'text' => 'Baik, saya cek transaksi di sistem. Mohon tunggu sebentar ya.'],
                ],
            ],
            [
                'status' => 'closed',
                'priority' => 'low',
                'issue_category' => 'general',
                'sentiment' => 'positive',
                'sentiment_score' => 0.65,
                'messages' => [
                    ['from' => 'customer', 'text' => 'Terima kasih, masalah saya sudah selesai.'],
                    ['from' => 'agent', 'text' => 'Sama-sama! Kalau butuh bantuan lagi, kabari saja.'],
                ],
            ],
            // Edge case: no agent reply yet (useful for inbox triage)
            [
                'status' => 'open',
                'priority' => 'high',
                'issue_category' => 'delivery',
                'sentiment' => 'negative',
                'sentiment_score' => -0.72,
                'messages' => [
                    ['from' => 'customer', 'text' => 'Pesanan saya belum sampai padahal sudah lewat estimasi 3 hari.'],
                    ['from' => 'customer', 'text' => 'Mohon update statusnya ya, saya butuh segera.'],
                ],
            ],
            // Edge case: conversation created but no messages yet
            [
                'status' => 'open',
                'priority' => 'low',
                'issue_category' => 'general',
                'sentiment' => null,
                'sentiment_score' => null,
                'messages' => [],
            ],
        ];

        $createMessages = function (Conversation $conversation, array $scenarioMessages, Carbon $startAt) use ($agents): void {
            $cursor = $startAt->copy();

            foreach ($scenarioMessages as $idx => $messageDef) {
                $senderType = $messageDef['from'] === 'agent' ? 'agent' : 'customer';
                $senderId = null;
                if ($senderType === 'agent') {
                    $senderId = $agents[$idx % count($agents)]->id;
                }

                // Space messages by 2-7 minutes to keep ordering realistic.
                $cursor = $cursor->copy()->addMinutes(2 + ($idx % 6));

                Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_type' => $senderType,
                    'sender_id' => $senderId,
                    'content' => $messageDef['text'],
                    'created_at' => $cursor,
                ]);

                $conversation->forceFill([
                    'last_message_from' => $senderType,
                    'last_message_at' => $cursor,
                ])->save();
            }
        };

        foreach ($customers as $index => $customerData) {
            $customerUser = User::query()->where('email', $customerData['email'])->first();
            if (! $customerUser) {
                $attrs = [
                    'name' => $customerData['name'],
                    'email' => $customerData['email'],
                    'email_verified_at' => $now,
                ];
                if (Schema::hasColumn('users', 'role')) {
                    $attrs['role'] = 'customer';
                }
                $customerUser = User::factory()->create($attrs);
            }

            // Last customer is intentionally left with no conversations.
            if ($customerData['email'] === 'noconv@example.com') {
                continue;
            }

            // Create 2-3 conversations per customer (deterministic-ish).
            $conversationCount = 2 + ($index % 2);

            for ($i = 0; $i < $conversationCount; $i++) {
                $scenario = $scenarios[($index + $i) % count($scenarios)];

                // Create a spread of last activity time across customers and conversations.
                $createdAt = $now->copy()->subDays(7 - ($index % 5))->subHours(6 - ($i % 6));

                $conversation = Conversation::create([
                    'user_id' => $customerUser->id,
                    'status' => $scenario['status'],
                    'priority' => $scenario['priority'],
                    'issue_category' => $scenario['issue_category'],
                    'sentiment' => $scenario['sentiment'],
                    'sentiment_score' => $scenario['sentiment_score'],
                    'last_message_from' => null,
                    'last_message_at' => null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                // Add a unique marker so searching can find something predictable.
                if (! empty($scenario['messages'])) {
                    $scenarioMessages = $scenario['messages'];
                    $scenarioMessages[0]['text'] .= ' [seed:' . Str::lower(Str::random(6)) . ']';

                    $createMessages($conversation, $scenarioMessages, $createdAt);
                }

                // If conversation has messages, ensure updated_at reflects the last activity.
                if ($conversation->last_message_at) {
                    $conversation->forceFill(['updated_at' => $conversation->last_message_at])->save();
                }
            }
        }
    }
}
