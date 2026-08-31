<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_records', function (Blueprint $table) {
            $table->decimal('beginning_qty_auto', 10, 2)->nullable()->after('beginning_qty');
            $table->string('beginning_override_reason')->nullable()->after('beginning_qty_auto');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_records', function (Blueprint $table) {
            $table->dropColumn(['beginning_qty_auto', 'beginning_override_reason']);
        });
    }
};