<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            // Ensure no rows still contain 'lead' before restricting the enum.
            DB::statement("UPDATE `messages` SET `sender_type`='agent' WHERE `sender_type`='lead'");
            DB::statement("UPDATE `conversations` SET `last_message_from`='agent' WHERE `last_message_from`='lead'");

            DB::statement("ALTER TABLE `messages` MODIFY `sender_type` ENUM('user','agent') NOT NULL");
            DB::statement("ALTER TABLE `conversations` MODIFY `last_message_from` ENUM('user','agent') NULL");

            return;
        }

        if ($driver === 'sqlite') {
            $this->rebuildMessagesForSqlite();
            $this->rebuildConversationsForSqlite();
        }
    }

    private function rebuildMessagesForSqlite(): void
    {
        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('messages_tmp')) {
            Schema::drop('messages_tmp');
        }

        Schema::create('messages_tmp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->enum('sender_type', ['user', 'agent']);
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('content');
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement("INSERT INTO messages_tmp (id, conversation_id, sender_type, sender_id, content, created_at) SELECT id, conversation_id, CASE WHEN sender_type = 'lead' THEN 'agent' ELSE sender_type END, sender_id, content, created_at FROM messages");

        Schema::drop('messages');
        Schema::rename('messages_tmp', 'messages');

        Schema::enableForeignKeyConstraints();
    }

    private function rebuildConversationsForSqlite(): void
    {
        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('conversations_tmp')) {
            Schema::drop('conversations_tmp');
        }

        Schema::create('conversations_tmp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['open', 'pending', 'closed'])->default('open');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->string('issue_category')->nullable();
            $table->string('sentiment')->nullable();
            $table->decimal('sentiment_score', 5, 2)->nullable();
            $table->enum('last_message_from', ['user', 'agent'])->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        DB::statement("INSERT INTO conversations_tmp (id, user_id, status, priority, issue_category, sentiment, sentiment_score, last_message_from, last_message_at, created_at, updated_at) SELECT id, user_id, status, priority, issue_category, sentiment, sentiment_score, CASE WHEN last_message_from = 'lead' THEN 'agent' ELSE last_message_from END, last_message_at, created_at, updated_at FROM conversations");

        Schema::drop('conversations');
        Schema::rename('conversations_tmp', 'conversations');

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Intentionally no-op: we do not want to re-introduce 'lead' values.
    }
};
