<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * SoftDeletes de procesos: baja física + audits. Maestros (cliente, proveedor, PV,
 * tipotransacción, cliente_uif) se conservan.
 */
return new class extends Migration
{
    /** Hijas a borrar antes de purgar el padre (FK). */
    private const PRE_PURGE = [
        'ticket' => [
            'ticket_tarea_comentario_usuario' => 'ticket_tarea_id',
            'ticket_tarea_novedad' => 'ticket_tarea_id',
        ],
        'recuento' => [
            'recuento_item' => 'recuento_id',
            'recuento_estado' => 'recuento_id',
            'recuento_archivo' => 'recuento_id',
        ],
        'rendicion_maquina' => [
            'rendicion_maquina_valor' => 'rendicion_maquina_id',
            'rendicion_maquina_gasto' => 'rendicion_maquina_id',
            'rendicion_maquina_ajuste_wigos' => 'rendicion_maquina_id',
        ],
        'propuesta_pago' => [
            'propuesta_pago_linea' => 'propuesta_pago_id',
        ],
        'prestamo' => [
            'prestamo_item' => 'prestamo_id',
            'prestamo_estado' => 'prestamo_id',
            'prestamo_token' => 'prestamo_id',
        ],
    ];

    /** Hijas primero. */
    private const TABLAS = [
        'ticket_tarea_comentario_usuario',
        'ticket_tarea_novedad',
        'ticket_archivo',
        'ticket_articulo',
        'ticket_estado',
        'ticket_tarea',
        'ticket',
        'rendicionreceptivo_adelanto',
        'rendicionreceptivo_formapago',
        'rendicionreceptivo_voucher',
        'rendicionreceptivo_caja_movimiento',
        'rendicionreceptivo_comision',
        'rendicionreceptivo',
        'voucher_reserva',
        'voucher_guia',
        'voucher_formapago',
        'voucher',
        'pagoproveedor_archivo',
        'pagoproveedor_comprobante',
        'pagoproveedor_estado',
        'pagoproveedor_retencion',
        'pagoproveedor',
        'proveedor_cuentacorriente',
        'propuesta_pago',
        'remito_articulo',
        'remito',
        'ordencompra_historia',
        'movimientoordentrabajo',
        'ordentrabajo',
        'articulo_archivo',
        'lote',
        'recuento',
        'prestamo',
        'transferencia_mercaderia',
        'movimientostock',
        'rendicion_maquina',
        'cliente_premio_uif',
        'cliente_riesgo_uif',
        'arbolaprobacion_oc_trigger',
    ];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, 'deleted_at')) {
                continue;
            }

            $this->purgarHijas($tabla);

            $bajas = (int) DB::table($tabla)->whereNotNull('deleted_at')->count();
            if ($bajas > 0) {
                DB::table($tabla)->whereNotNull('deleted_at')->delete();
                Log::info('softdeletes_proceso: purga', ['tabla' => $tabla, 'filas' => $bajas]);
            }

            Schema::table($tabla, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLAS) as $tabla) {
            if (! Schema::hasTable($tabla) || Schema::hasColumn($tabla, 'deleted_at')) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    private function purgarHijas(string $padre): void
    {
        $mapa = self::PRE_PURGE[$padre] ?? [];
        if ($mapa === []) {
            return;
        }

        $ids = DB::table($padre)->whereNotNull('deleted_at')->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        foreach ($mapa as $hija => $fk) {
            if (! Schema::hasTable($hija) || ! Schema::hasColumn($hija, $fk)) {
                continue;
            }
            if ($padre === 'ticket' && in_array($hija, ['ticket_tarea_comentario_usuario', 'ticket_tarea_novedad'], true)) {
                $tareaIds = DB::table('ticket_tarea')->whereIn('ticket_id', $ids)->pluck('id');
                if ($tareaIds->isEmpty()) {
                    continue;
                }
                DB::table($hija)->whereIn($fk, $tareaIds)->delete();
                continue;
            }
            DB::table($hija)->whereIn($fk, $ids)->delete();
        }
    }
};
