<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_records', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('inventory_records', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('item_id')
                ->constrained('users')->nullOnDelete();
            $table->string('crew_name')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_records', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'crew_name']);
        });

        Schema::table('inventory_records', function (Blueprint $table) {
            $table->foreignId('user_id')->after('item_id')->constrained()->cascadeOnDelete();
        });
    }
};