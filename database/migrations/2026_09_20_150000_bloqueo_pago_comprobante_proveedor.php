<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dos políticas posibles cuando la factura se sale de la tolerancia contra la provisión de la COM:
 *
 * - DEVOLVER_COMPRAS (default, criterio AGG): no se deja cargar, el legajo vuelve a Compras y se
 *   avisa por correo. El problema no entra a la contabilidad, pero frena el circuito.
 * - BLOQUEAR_PAGO (criterio SAP MRBR/ZLSPR): la factura se contabiliza igual, queda en la cuenta
 *   del proveedor y suma al gasto, pero nace bloqueada para pago y no la toma ninguna propuesta
 *   hasta que alguien la libere explícitamente. Cierra el circuito contable sin pagar de más.
 *
 * Se configura por empresa y centro de costo, en la misma tabla que la tolerancia.
 */
return new class extends Migration
{
    private const TABLA_CP = 'comprobante_proveedor';

    private const TABLA_CFG = 'configuracion_comprobante_proveedor_tolerancia';

    public function up(): void
    {
        Schema::table(self::TABLA_CP, function (Blueprint $table) {
            if (! Schema::hasColumn(self::TABLA_CP, 'bloqueado_pago')) {
                $table->boolean('bloqueado_pago')->default(false)
                    ->comment('Factura contabilizada pero excluida de las propuestas de pago hasta liberarla');
            }
            if (! Schema::hasColumn(self::TABLA_CP, 'bloqueado_pago_motivo')) {
                $table->string('bloqueado_pago_motivo', 500)->nullable()
                    ->comment('Por qué quedó bloqueada para pago');
            }
            if (! Schema::hasColumn(self::TABLA_CP, 'bloqueado_pago_at')) {
                $table->timestamp('bloqueado_pago_at')->nullable();
            }
            if (! Schema::hasColumn(self::TABLA_CP, 'bloqueado_pago_user_id')) {
                $table->unsignedBigInteger('bloqueado_pago_user_id')->nullable()
                    ->comment('Null si el bloqueo lo puso el control automático');
            }
            if (! Schema::hasColumn(self::TABLA_CP, 'liberado_pago_at')) {
                $table->timestamp('liberado_pago_at')->nullable();
            }
            if (! Schema::hasColumn(self::TABLA_CP, 'liberado_pago_user_id')) {
                $table->unsignedBigInteger('liberado_pago_user_id')->nullable();
            }
            if (! Schema::hasColumn(self::TABLA_CP, 'liberado_pago_motivo')) {
                $table->string('liberado_pago_motivo', 500)->nullable();
            }
        });

        Schema::table(self::TABLA_CP, function (Blueprint $table) {
            // La propuesta de pago filtra por esta columna en cada sincronización de deuda.
            $table->index(['empresa_id', 'bloqueado_pago'], 'idx_cp_empresa_bloqueado_pago');
        });

        Schema::table(self::TABLA_CFG, function (Blueprint $table) {
            if (! Schema::hasColumn(self::TABLA_CFG, 'accion_fuera_tolerancia')) {
                $table->string('accion_fuera_tolerancia', 20)->default('DEVOLVER_COMPRAS')
                    ->comment('DEVOLVER_COMPRAS (no deja cargar) | BLOQUEAR_PAGO (carga y bloquea el pago)');
            }
        });
    }

    public function down(): void
    {
        Schema::table(self::TABLA_CP, function (Blueprint $table) {
            $table->dropIndex('idx_cp_empresa_bloqueado_pago');
        });

        Schema::table(self::TABLA_CP, function (Blueprint $table) {
            foreach ([
                'bloqueado_pago',
                'bloqueado_pago_motivo',
                'bloqueado_pago_at',
                'bloqueado_pago_user_id',
                'liberado_pago_at',
                'liberado_pago_user_id',
                'liberado_pago_motivo',
            ] as $col) {
                if (Schema::hasColumn(self::TABLA_CP, $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table(self::TABLA_CFG, function (Blueprint $table) {
            if (Schema::hasColumn(self::TABLA_CFG, 'accion_fuera_tolerancia')) {
                $table->dropColumn('accion_fuera_tolerancia');
            }
        });
    }
};
