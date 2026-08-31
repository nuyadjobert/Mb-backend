<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('shift_number');
            $table->date('record_date');

            $table->decimal('beginning_qty', 10, 2)->default(0);
            $table->decimal('del_qty', 10, 2)->default(0);
            $table->decimal('out_qty', 10, 2)->default(0);
            $table->decimal('ending_qty', 10, 2)->default(0);

            $table->decimal('usage_qty', 10, 2)->default(0);
            $table->decimal('total_order', 10, 2)->default(0);
            $table->decimal('total_sales', 12, 2)->default(0);

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'item_id', 'shift_number', 'record_date'], 'unique_shift_record');
            $table->index(['branch_id', 'record_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_records');
    }
};