<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pembelian_items', function (Blueprint $table) {
            if (!Schema::hasColumn('pembelian_items', 'satuan')) {
                $table->string('satuan', 20)->nullable()->after('qty');
            }
            if (!Schema::hasColumn('pembelian_items', 'satuan_besar')) {
                $table->string('satuan_besar', 20)->nullable()->after('satuan');
            }
            if (!Schema::hasColumn('pembelian_items', 'qty_besar')) {
                $table->decimal('qty_besar', 15, 2)->nullable()->after('satuan_besar');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pembelian_items', function (Blueprint $table) {
            foreach (['satuan', 'satuan_besar', 'qty_besar'] as $col) {
                if (Schema::hasColumn('pembelian_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
