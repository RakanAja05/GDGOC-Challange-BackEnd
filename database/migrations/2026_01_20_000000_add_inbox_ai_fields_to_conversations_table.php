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
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('issue_category')->nullable()->after('priority');
            $table->string('sentiment')->nullable()->after('issue_category');
            $table->decimal('sentiment_score', 5, 2)->nullable()->after('sentiment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['issue_category', 'sentiment', 'sentiment_score']);
        });
    }
};
