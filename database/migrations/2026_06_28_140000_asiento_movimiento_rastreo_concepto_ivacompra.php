<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asiento_movimiento', function (Blueprint $table) {
            if (! Schema::hasColumn('asiento_movimiento', 'comprobante_proveedor_id')) {
                $table->unsignedBigInteger('comprobante_proveedor_id')->nullable()->after('observacion');
                $table->foreign('comprobante_proveedor_id', 'fk_asiento_mov_cp')
                    ->references('id')->on('comprobante_proveedor')->onDelete('set null')->onUpdate('cascade');
            }

            if (! Schema::hasColumn('asiento_movimiento', 'comprobante_proveedor_concepto_id')) {
                $table->unsignedBigInteger('comprobante_proveedor_concepto_id')->nullable()->after('comprobante_proveedor_id');
                $table->foreign('comprobante_proveedor_concepto_id', 'fk_asiento_mov_cp_concepto')
                    ->references('id')->on('comprobante_proveedor_concepto')->onDelete('set null')->onUpdate('cascade');
            }

            if (! Schema::hasColumn('asiento_movimiento', 'concepto_ivacompra_id')) {
                $table->unsignedBigInteger('concepto_ivacompra_id')->nullable()->after('comprobante_proveedor_concepto_id');
                $table->foreign('concepto_ivacompra_id', 'fk_asiento_mov_concepto_ivacompra')
                    ->references('id')->on('concepto_ivacompra')->onDelete('set null')->onUpdate('restrict');
            }
        });
    }

    public function down(): void
    {
        Schema::table('asiento_movimiento', function (Blueprint $table) {
            foreach ([
                'fk_asiento_mov_concepto_ivacompra' => 'concepto_ivacompra_id',
                'fk_asiento_mov_cp_concepto' => 'comprobante_proveedor_concepto_id',
                'fk_asiento_mov_cp' => 'comprobante_proveedor_id',
            ] as $fk => $col) {
                if (Schema::hasColumn('asiento_movimiento', $col)) {
                    $table->dropForeign($fk);
                    $table->dropColumn($col);
                }
            }
        });
    }
};
