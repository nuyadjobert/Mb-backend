<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_counts', function (Blueprint $table) {
            $table->decimal('reviewed_expenses', 12, 2)
                ->nullable()
                ->after('total_expenses');

            $table->decimal('expected_cash', 12, 2)
                ->nullable()
                ->after('reviewed_expenses');

            $table->decimal('cash_variance', 12, 2)
                ->nullable()
                ->after('expected_cash');

            $table->string('variance_status', 20)
                ->nullable()
                ->after('cash_variance');

            $table->text('review_notes')
                ->nullable()
                ->after('notes');

            $table->timestamp('reviewed_at')
                ->nullable()
                ->after('finalized_by');

            $table->string('reviewed_by')
                ->nullable()
                ->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('cash_counts', function (Blueprint $table) {
            $table->dropColumn([
                'reviewed_expenses',
                'expected_cash',
                'cash_variance',
                'variance_status',
                'review_notes',
                'reviewed_at',
                'reviewed_by',
            ]);
        });
    }
};