<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_counts', function (Blueprint $table) {
            $table->timestamp('finalized_at')->nullable()->after('notes');
            $table->string('finalized_by')->nullable()->after('finalized_at');
        });
    }

    public function down(): void
    {
        Schema::table('cash_counts', function (Blueprint $table) {
            $table->dropColumn(['finalized_at', 'finalized_by']);
        });
    }
};