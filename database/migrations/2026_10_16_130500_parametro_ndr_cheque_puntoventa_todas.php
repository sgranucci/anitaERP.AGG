<?php

use App\Support\Configuracion\ParametroSistemaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El parámetro de sucursal de la NDR existe en todas las instalaciones.
 * No copia el id de Ferli: si la fila ya está, no toca el valor.
 * Si no está, guarda el único punto de venta que esta base ya usa (mapa del .env),
 * o vacío cuando hay uno distinto por empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('parametro_sistema')) {
            return;
        }

        $clave = ParametroSistemaSupport::CLAVE_NDR_CHEQUE_PUNTOVENTA_ID;
        $def = ParametroSistemaSupport::definiciones()[$clave] ?? null;
        if (! is_array($def)) {
            return;
        }

        $existe = DB::table('parametro_sistema')->where('clave', $clave)->exists();
        if ($existe) {
            DB::table('parametro_sistema')->where('clave', $clave)->update([
                'grupo' => $def['grupo'],
                'etiqueta' => $def['etiqueta'],
                'ayuda' => $def['ayuda'],
                'tipo' => $def['tipo'],
                'orden' => $def['orden'],
                'updated_at' => now(),
            ]);
            Cache::forget('parametro_sistema.mapa');

            return;
        }

        $puntoventaId = ParametroSistemaSupport::ndrChequePuntoventaId();
        DB::table('parametro_sistema')->insert([
            'clave' => $clave,
            'grupo' => $def['grupo'],
            'etiqueta' => $def['etiqueta'],
            'ayuda' => $def['ayuda'],
            'tipo' => $def['tipo'],
            'valor' => $puntoventaId > 0 ? (string) $puntoventaId : '',
            'orden' => $def['orden'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Cache::forget('parametro_sistema.mapa');
    }

    public function down(): void
    {
        // La fila de Ferli la creó la migración anterior. Esta no la borra.
    }
};
