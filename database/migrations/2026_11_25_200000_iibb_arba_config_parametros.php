<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parámetros IIBB/ARBA configurables por pantalla (antes constantes en código):
 *
 * - iibb_presentacion_config.tolerancia_conciliacion: tolerancia en pesos de la conciliación contra el mayor
 *   (la misma que usa el control de la liquidación ARBA).
 * - iibb_presentacion_config.palabras_banco: palabras que identifican un banco en el nombre del proveedor
 *   (el banco no se toma como proveedor en el neteo).
 * - iibb_solicitudpago_config.dias_duplicado: ventana para detectar solicitudes de pago duplicadas.
 * - iibb_solicitudpago_config.dias_aviso_pendiente: antigüedad a partir de la cual un pendiente de neteo se avisa.
 */
return new class extends Migration
{
    private const TABLA_IIBB = 'iibb_presentacion_config';

    private const TABLA_ARBA = 'iibb_solicitudpago_config';

    public function up(): void
    {
        if (Schema::hasTable(self::TABLA_IIBB)) {
            if (! Schema::hasColumn(self::TABLA_IIBB, 'tolerancia_conciliacion')) {
                Schema::table(self::TABLA_IIBB, function (Blueprint $table) {
                    $table->decimal('tolerancia_conciliacion', 18, 2)->default(100)->after('frecuencia');
                });
            }
            if (! Schema::hasColumn(self::TABLA_IIBB, 'palabras_banco')) {
                Schema::table(self::TABLA_IIBB, function (Blueprint $table) {
                    $table->string('palabras_banco', 255)->default('BCO,BANCO')->after('tolerancia_conciliacion');
                });
            }
        }

        if (Schema::hasTable(self::TABLA_ARBA)) {
            if (! Schema::hasColumn(self::TABLA_ARBA, 'dias_duplicado')) {
                Schema::table(self::TABLA_ARBA, function (Blueprint $table) {
                    $table->unsignedSmallInteger('dias_duplicado')->default(45)->after('plantilla_detalle');
                });
            }
            if (! Schema::hasColumn(self::TABLA_ARBA, 'dias_aviso_pendiente')) {
                Schema::table(self::TABLA_ARBA, function (Blueprint $table) {
                    $table->unsignedSmallInteger('dias_aviso_pendiente')->default(90)->after('dias_duplicado');
                });
            }
        }
    }

    public function down(): void
    {
        $columnas = [
            self::TABLA_IIBB => ['palabras_banco', 'tolerancia_conciliacion'],
            self::TABLA_ARBA => ['dias_aviso_pendiente', 'dias_duplicado'],
        ];

        foreach ($columnas as $tabla => $lista) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            foreach ($lista as $columna) {
                if (Schema::hasColumn($tabla, $columna)) {
                    Schema::table($tabla, function (Blueprint $table) use ($columna) {
                        $table->dropColumn($columna);
                    });
                }
            }
        }
    }
};
