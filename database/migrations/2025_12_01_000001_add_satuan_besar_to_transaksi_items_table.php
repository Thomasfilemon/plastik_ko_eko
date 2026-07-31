<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_items', function (Blueprint $table) {
            if (!Schema::hasColumn('transaksi_items', 'satuan_besar')) {
                $table->string('satuan_besar', 20)->nullable()->after('satuan');
            }
            if (!Schema::hasColumn('transaksi_items', 'qty_besar')) {
                $table->decimal('qty_besar', 15, 2)->nullable()->after('satuan_besar');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_items', function (Blueprint $table) {
            foreach (['satuan_besar', 'qty_besar'] as $col) {
                if (Schema::hasColumn('transaksi_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
