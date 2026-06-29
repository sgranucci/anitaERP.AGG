<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ESTADO_VIEJO = 'A COMPRAS';

    private const ESTADO_NUEVO = 'EN LABORATORIO';

    public function up(): void
    {
        DB::table('requisicion_sala')
            ->where('estado', self::ESTADO_VIEJO)
            ->update(['estado' => self::ESTADO_NUEVO]);

        DB::table('requisicion_sala_estado')
            ->where('estado', self::ESTADO_VIEJO)
            ->update(['estado' => self::ESTADO_NUEVO]);

        DB::table('arbolaprobacion_nivel')
            ->where('documento_estado_al_aprobar', self::ESTADO_VIEJO)
            ->whereNull('deleted_at')
            ->update(['documento_estado_al_aprobar' => self::ESTADO_NUEVO]);
    }

    public function down(): void
    {
        DB::table('requisicion_sala')
            ->where('estado', self::ESTADO_NUEVO)
            ->update(['estado' => self::ESTADO_VIEJO]);

        DB::table('requisicion_sala_estado')
            ->where('estado', self::ESTADO_NUEVO)
            ->update(['estado' => self::ESTADO_VIEJO]);

        DB::table('arbolaprobacion_nivel')
            ->where('documento_estado_al_aprobar', self::ESTADO_NUEVO)
            ->whereNull('deleted_at')
            ->update(['documento_estado_al_aprobar' => self::ESTADO_VIEJO]);
    }
};
