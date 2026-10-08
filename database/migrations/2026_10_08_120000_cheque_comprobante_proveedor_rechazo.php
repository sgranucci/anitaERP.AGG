<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Débito interno al proveedor cuando se rechaza un cheque de terceros endosado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cheque') && ! Schema::hasColumn('cheque', 'comprobante_proveedor_id')) {
            Schema::table('cheque', function (Blueprint $table) {
                $table->unsignedBigInteger('comprobante_proveedor_id')->nullable()->after('venta_nd_id');
                $table->foreign('comprobante_proveedor_id', 'fk_cheque_comprobante_proveedor')
                    ->references('id')->on('comprobante_proveedor')->onDelete('restrict')->onUpdate('restrict');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cheque') && Schema::hasColumn('cheque', 'comprobante_proveedor_id')) {
            Schema::table('cheque', function (Blueprint $table) {
                $table->dropForeign('fk_cheque_comprobante_proveedor');
                $table->dropColumn('comprobante_proveedor_id');
            });
        }
    }
};
