<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('items', 'sort_order')) {
            Schema::table('items', function (Blueprint $table) {
                $table->integer('sort_order')->default(0)->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('items', 'sort_order')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropColumn('sort_order');
            });
        }
    }
};