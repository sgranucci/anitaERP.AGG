<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rendicion_maquina', function (Blueprint $table) {
            if (! Schema::hasColumn('rendicion_maquina', 'asiento_id')) {
                $table->unsignedBigInteger('asiento_id')->nullable()->after('anita_sincronizado_en');
                $table->index('asiento_id', 'idx_rend_maquina_asiento');
            }
            if (! Schema::hasColumn('rendicion_maquina', 'asientos_cierre_ids_json')) {
                $table->json('asientos_cierre_ids_json')->nullable()->after('asiento_id');
            }
            if (! Schema::hasColumn('rendicion_maquina', 'cierre_contable_en')) {
                $table->timestamp('cierre_contable_en')->nullable()->after('asientos_cierre_ids_json');
            }
            if (! Schema::hasColumn('rendicion_maquina', 'cierre_contable_usuario_id')) {
                $table->unsignedBigInteger('cierre_contable_usuario_id')->nullable()->after('cierre_contable_en');
            }
            if (! Schema::hasColumn('rendicion_maquina', 'cierre_contable_legacy')) {
                $table->boolean('cierre_contable_legacy')->default(false)->after('cierre_contable_usuario_id');
            }
            if (! Schema::hasColumn('rendicion_maquina', 'factura_tipo')) {
                $table->string('factura_tipo', 3)->nullable()->after('cierre_contable_legacy');
            }
            if (! Schema::hasColumn('rendicion_maquina', 'factura_letra')) {
                $table->string('factura_letra', 1)->nullable()->after('factura_tipo');
            }
            if (! Schema::hasColumn('rendicion_maquina', 'factura_sucursal')) {
                $table->unsignedInteger('factura_sucursal')->nullable()->after('factura_letra');
            }
            if (! Schema::hasColumn('rendicion_maquina', 'factura_nro')) {
                $table->unsignedBigInteger('factura_nro')->nullable()->after('factura_sucursal');
            }
            if (! Schema::hasColumn('rendicion_maquina', 'factura_fecha')) {
                $table->date('factura_fecha')->nullable()->after('factura_nro');
            }
            if (! Schema::hasColumn('rendicion_maquina', 'estado_facturacion')) {
                $table->string('estado_facturacion', 1)->nullable()->after('factura_fecha');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rendicion_maquina', function (Blueprint $table) {
            foreach ([
                'estado_facturacion',
                'factura_fecha',
                'factura_nro',
                'factura_sucursal',
                'factura_letra',
                'factura_tipo',
                'cierre_contable_legacy',
                'cierre_contable_usuario_id',
                'cierre_contable_en',
                'asientos_cierre_ids_json',
                'asiento_id',
            ] as $col) {
                if (Schema::hasColumn('rendicion_maquina', $col)) {
                    if ($col === 'asiento_id') {
                        try {
                            $table->dropIndex('idx_rend_maquina_asiento');
                        } catch (\Throwable) {
                        }
                    }
                    $table->dropColumn($col);
                }
            }
        });
    }
};
