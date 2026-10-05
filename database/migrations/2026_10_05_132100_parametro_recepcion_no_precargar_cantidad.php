<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Configuracion\ParametroSistemaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cantidad recibida de la recepción: en El Bierzo arranca en 0.
 * En el resto se precarga con el pendiente, como venía el circuito.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('parametro_sistema')) {
            return;
        }

        $clave = ParametroSistemaSupport::CLAVE_RECEPCION_NO_PRECARGAR_CANTIDAD;
        $def = ParametroSistemaSupport::definiciones()[$clave] ?? null;
        if (! is_array($def)) {
            return;
        }

        if (DB::table('parametro_sistema')->where('clave', $clave)->exists()) {
            return;
        }

        DB::table('parametro_sistema')->insert([
            'clave' => $clave,
            'grupo' => $def['grupo'],
            'etiqueta' => $def['etiqueta'],
            'ayuda' => $def['ayuda'],
            'tipo' => $def['tipo'],
            'valor' => EntornoEmpresaSupport::esElBierzo() ? '1' : '0',
            'orden' => $def['orden'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Cache::forget('parametro_sistema.mapa');
    }

    public function down(): void
    {
        if (! Schema::hasTable('parametro_sistema')) {
            return;
        }

        DB::table('parametro_sistema')
            ->where('clave', ParametroSistemaSupport::CLAVE_RECEPCION_NO_PRECARGAR_CANTIDAD)
            ->delete();
        Cache::forget('parametro_sistema.mapa');
    }
};
