<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('conversations', 'user_id')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('customers') && Schema::hasColumn('conversations', 'customer_id')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("UPDATE conversations c JOIN customers cu ON cu.id = c.customer_id JOIN users u ON u.email = cu.email SET c.user_id = u.id");
            }
        }

        if (Schema::hasColumn('conversations', 'customer_id')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->dropForeign(['customer_id']);
                $table->dropColumn('customer_id');
            });
        }

        if (Schema::hasTable('customers')) {
            Schema::drop('customers');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasColumn('conversations', 'customer_id')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->foreignId('customer_id')->nullable()->after('id')->constrained('customers')->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('conversations', 'user_id') && Schema::hasTable('customers')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("INSERT IGNORE INTO customers (name, email, created_at) SELECT u.name, u.email, NOW() FROM users u");
                DB::statement("UPDATE conversations c JOIN users u ON u.id = c.user_id JOIN customers cu ON cu.email = u.email SET c.customer_id = cu.id");
            }
        }

        if (Schema::hasColumn('conversations', 'user_id')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            });
        }
    }
};
