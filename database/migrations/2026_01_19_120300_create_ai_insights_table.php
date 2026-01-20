<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete()->unique();
            $table->string('issue_category')->nullable();
            $table->string('sentiment')->nullable();
            $table->decimal('sentiment_score', 5, 2)->nullable();
            $table->text('summary')->nullable();
            $table->text('suggested_reply')->nullable();
            $table->timestamp('analyzed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_insights');
    }
};
