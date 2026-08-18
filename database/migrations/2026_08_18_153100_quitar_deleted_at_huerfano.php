<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * deleted_at sin SoftDeletes: Eloquent no filtraba; las filas con timestamp
 * (cobranza_archivo, ordenventa_archivo) siguen vivas. Solo se dropea la columna.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLAS = [
        'arbolaprobacion',
        'arbolaprobacion_movimiento',
        'articulo_estado',
        'capex_archivo',
        'capex_estado',
        'cliente_cuentacorriente',
        'cobranza',
        'cobranza_archivo',
        'cobranza_comprobante',
        'cobranza_estado',
        'cobranza_retencion',
        'estadocheque_banco',
        'ordentrabajo_tarea',
        'ordenventa',
        'ordenventa_archivo',
        'ordenventa_cuota',
        'ordenventa_estado',
        'partidagasto_archivo',
        'partidagasto_estado',
        'pedido',
        'pedido_articulo',
        'pedido_articulo_caja',
        'pedido_articulo_estado',
        'pedido_combinacion',
        'pedido_combinacion_estado',
        'pedido_combinacion_talle',
        'requisicion_estado',
        'tipodocumento',
    ];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'deleted_at')) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            if (! Schema::hasTable($tabla) || Schema::hasColumn($tabla, 'deleted_at')) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }
};
