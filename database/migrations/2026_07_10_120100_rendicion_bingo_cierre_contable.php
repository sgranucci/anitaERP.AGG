<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rendicion_bingo_caja', function (Blueprint $table) {
            if (! Schema::hasColumn('rendicion_bingo_caja', 'asiento_id')) {
                $table->unsignedBigInteger('asiento_id')->nullable()->after('observacion');
                $table->index('asiento_id', 'idx_rend_bingo_caja_asiento');
            }
            if (! Schema::hasColumn('rendicion_bingo_caja', 'cierre_contable_en')) {
                $table->timestamp('cierre_contable_en')->nullable()->after('asiento_id');
            }
            if (! Schema::hasColumn('rendicion_bingo_caja', 'cierre_contable_usuario_id')) {
                $table->unsignedBigInteger('cierre_contable_usuario_id')->nullable()->after('cierre_contable_en');
            }
            if (! Schema::hasColumn('rendicion_bingo_caja', 'refuerzo_prestamo')) {
                $table->decimal('refuerzo_prestamo', 16, 2)->default(0)->after('redondeo');
            }
            if (! Schema::hasColumn('rendicion_bingo_caja', 'factura_tipo')) {
                $table->string('factura_tipo', 3)->nullable()->after('cierre_contable_usuario_id');
            }
            if (! Schema::hasColumn('rendicion_bingo_caja', 'factura_letra')) {
                $table->string('factura_letra', 1)->nullable()->after('factura_tipo');
            }
            if (! Schema::hasColumn('rendicion_bingo_caja', 'factura_sucursal')) {
                $table->unsignedInteger('factura_sucursal')->nullable()->after('factura_letra');
            }
            if (! Schema::hasColumn('rendicion_bingo_caja', 'factura_nro')) {
                $table->unsignedBigInteger('factura_nro')->nullable()->after('factura_sucursal');
            }
            if (! Schema::hasColumn('rendicion_bingo_caja', 'factura_fecha')) {
                $table->date('factura_fecha')->nullable()->after('factura_nro');
            }
            if (! Schema::hasColumn('rendicion_bingo_caja', 'estado_facturacion')) {
                $table->string('estado_facturacion', 1)->nullable()->after('factura_fecha');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rendicion_bingo_caja', function (Blueprint $table) {
            foreach ([
                'estado_facturacion',
                'factura_fecha',
                'factura_nro',
                'factura_sucursal',
                'factura_letra',
                'factura_tipo',
                'refuerzo_prestamo',
                'cierre_contable_usuario_id',
                'cierre_contable_en',
                'asiento_id',
            ] as $col) {
                if (Schema::hasColumn('rendicion_bingo_caja', $col)) {
                    if ($col === 'asiento_id') {
                        $table->dropIndex('idx_rend_bingo_caja_asiento');
                    }
                    $table->dropColumn($col);
                }
            }
        });
    }
};
