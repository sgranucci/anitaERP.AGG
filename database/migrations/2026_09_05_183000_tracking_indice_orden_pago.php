<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué orden de pago canceló el comprobante.
 *
 * Va en el índice y no en un join de la grilla porque el dato tiene dos
 * orígenes y uno de ellos es el puente: en el ERP la OP se llega por
 * `proveedor_cuentacorriente` → `pagoproveedor_comprobante`, pero en lo
 * importado del Anita vive en `promov.prov_ref_*`, que ya se consulta para
 * resolver el estado de pago. Resolverlo en el mismo lugar evita una segunda
 * vuelta al Informix.
 */
return new class extends Migration
{
    private const TABLA = 'comprobante_tracking_indice';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLA)) {
            return;
        }

        Schema::table(self::TABLA, function (Blueprint $table) {
            if (! Schema::hasColumn(self::TABLA, 'pago_op_referencia')) {
                // Etiqueta ya armada ('OPA A 0001-00124102'): la del ERP y la
                // del Anita no comparten formato ni numeración.
                $table->string('pago_op_referencia', 60)->nullable()->after('pago_fecha');
            }
            if (! Schema::hasColumn(self::TABLA, 'pago_op_cantidad')) {
                // Un comprobante en cuotas se cancela con varias OP.
                $table->unsignedSmallInteger('pago_op_cantidad')->default(0)->after('pago_op_referencia');
            }
            if (! Schema::hasColumn(self::TABLA, 'pago_op_id')) {
                // Sólo para lo nativo del ERP: es lo que permite enlazar la OP.
                $table->unsignedBigInteger('pago_op_id')->nullable()->after('pago_op_cantidad');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLA)) {
            return;
        }

        Schema::table(self::TABLA, function (Blueprint $table) {
            foreach (['pago_op_referencia', 'pago_op_cantidad', 'pago_op_id'] as $columna) {
                if (Schema::hasColumn(self::TABLA, $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });
    }
};
