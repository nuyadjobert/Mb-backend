<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('shift_number');
            $table->date('record_date');

            $table->unsignedInteger('pieces_1000')->default(0);
            $table->unsignedInteger('pieces_500')->default(0);
            $table->unsignedInteger('pieces_100')->default(0);
            $table->unsignedInteger('pieces_50')->default(0);
            $table->unsignedInteger('pieces_20')->default(0);
            $table->unsignedInteger('pieces_10')->default(0);
            $table->unsignedInteger('pieces_5')->default(0);
            $table->unsignedInteger('pieces_1')->default(0);

            $table->json('serials_1000')->nullable();
            $table->json('serials_500')->nullable();

            $table->decimal('total_cash', 12, 2)->default(0);
            $table->decimal('total_expenses', 12, 2)->default(0);
            $table->decimal('net_cash', 12, 2)->default(0);

            $table->string('crew_name')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['branch_id', 'shift_number', 'record_date'], 'unique_cash_count_shift');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_counts');
    }
};