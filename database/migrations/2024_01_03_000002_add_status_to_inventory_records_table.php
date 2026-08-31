<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_records', function (Blueprint $table) {
            $table->enum('status', ['pending', 'checked'])->default('pending')->after('crew_name');
            $table->string('checked_by')->nullable()->after('status');
            $table->timestamp('checked_at')->nullable()->after('checked_by');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_records', function (Blueprint $table) {
            $table->dropColumn(['status', 'checked_by', 'checked_at']);
        });
    }
};