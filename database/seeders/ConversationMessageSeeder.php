<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Customer;
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
        $agent = User::first();

        if (!$agent) {
            $agent = User::factory()->create([
                'name' => 'Support Agent',
                'email' => 'agent@example.com',
            ]);
        }

        $customers = [
            ['name' => 'Andi Prasetyo', 'email' => 'andi@example.com'],
            ['name' => 'Siti Rahma', 'email' => 'siti@example.com'],
        ];

        foreach ($customers as $customerData) {
            $customer = Customer::firstOrCreate(
                ['email' => $customerData['email']],
                ['name' => $customerData['name']]
            );

            $conversation = Conversation::create([
                'customer_id' => $customer->id,
                'status' => 'open',
                'priority' => 'medium',
            ]);

            $messages = [
                [
                    'sender_type' => 'customer',
                    'sender_id' => null,
                    'content' => 'Halo, saya butuh bantuan untuk akun saya.',
                    'created_at' => Carbon::now()->subMinutes(12),
                ],
                [
                    'sender_type' => 'agent',
                    'sender_id' => $agent->id,
                    'content' => 'Halo! Tentu, bisa jelaskan masalahnya?',
                    'created_at' => Carbon::now()->subMinutes(10),
                ],
                [
                    'sender_type' => 'customer',
                    'sender_id' => null,
                    'content' => 'Saya tidak bisa login sejak pagi.',
                    'created_at' => Carbon::now()->subMinutes(7),
                ],
                [
                    'sender_type' => 'agent',
                    'sender_id' => $agent->id,
                    'content' => 'Baik, saya cek dulu. Mohon tunggu sebentar ya.',
                    'created_at' => Carbon::now()->subMinutes(5),
                ],
            ];

            foreach ($messages as $message) {
                Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_type' => $message['sender_type'],
                    'sender_id' => $message['sender_id'],
                    'content' => $message['content'],
                    'created_at' => $message['created_at'],
                ]);
            }

            $lastMessage = end($messages);

            $conversation->update([
                'last_message_from' => $lastMessage['sender_type'],
                'last_message_at' => $lastMessage['created_at'],
            ]);
        }
    }
}
