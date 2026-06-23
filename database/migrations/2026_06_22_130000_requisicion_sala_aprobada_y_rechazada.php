<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TIPO_ARBOL = 'Requisiciones de sala';

    private const CC_TECNICA = '93';

    /**
     * Unifica APROBADO → APROBADA y configura árbol CC 93 con estado APROBADA.
     */
    public function up(): void
    {
        DB::table('requisicion_sala')
            ->where('estado', 'APROBADO')
            ->update(['estado' => 'APROBADA', 'updated_at' => now()]);

        DB::table('requisicion_sala_estado')
            ->where('estado', 'APROBADO')
            ->update(['estado' => 'APROBADA', 'updated_at' => now()]);

        if (strtoupper((string) config('app.empresa')) !== 'AGG') {
            return;
        }

        $arbol = DB::table('arbolaprobacion')
            ->where('tipoarbol', self::TIPO_ARBOL)
            ->whereNull('deleted_at')
            ->first();

        if (! $arbol) {
            return;
        }

        $centroCostoId = (int) DB::table('centrocosto')->where('codigo', self::CC_TECNICA)->value('id');
        if ($centroCostoId <= 0) {
            return;
        }

        DB::table('arbolaprobacion_nivel')
            ->where('arbolaprobacion_id', $arbol->id)
            ->where('centrocosto_id', $centroCostoId)
            ->whereNull('deleted_at')
            ->where('documento_estado_al_aprobar', 'APROBADO')
            ->update([
                'documento_estado_al_aprobar' => 'APROBADA',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
    }
};
