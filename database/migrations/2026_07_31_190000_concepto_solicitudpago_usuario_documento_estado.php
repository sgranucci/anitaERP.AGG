<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de cabecera SP al aprobar cada nivel del árbol del concepto
 * (mismo rol que arbolaprobacion_nivel.documento_estado_al_aprobar).
 *
 * Convención inicial por número de nivel:
 * 1=EMITIDA (pasa automático), 2=CONTROLADA, 3=AUTORIZADA, 4=PAGADA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concepto_solicitudpago_usuario', function (Blueprint $table) {
            $table->string('documento_estado_al_aprobar', 50)
                ->nullable()
                ->after('desde_monto');
        });

        $mapa = [
            1 => 'EMITIDA',
            2 => 'CONTROLADA',
            3 => 'AUTORIZADA',
            4 => 'PAGADA',
        ];

        foreach ($mapa as $nivel => $estado) {
            DB::table('concepto_solicitudpago_usuario')
                ->where('nivel', $nivel)
                ->update(['documento_estado_al_aprobar' => $estado]);
        }
    }

    public function down(): void
    {
        Schema::table('concepto_solicitudpago_usuario', function (Blueprint $table) {
            $table->dropColumn('documento_estado_al_aprobar');
        });
    }
};
