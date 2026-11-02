<?php

use App\Support\Configuracion\ParametroSistemaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración general → Compras: días que la fecha impresa
 * puede adelantarse a la fecha de contabilización.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('parametro_sistema')) {
            return;
        }

        $clave = ParametroSistemaSupport::CLAVE_COMPROBANTE_FECHA_TOLERANCIA_DIAS;
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
            'valor' => (string) max(0, (int) config(
                'comprobante_proveedor.fecha_comprobante_tolerancia_dias_contabilizacion',
                5
            )),
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
            ->where('clave', ParametroSistemaSupport::CLAVE_COMPROBANTE_FECHA_TOLERANCIA_DIAS)
            ->delete();
        Cache::forget('parametro_sistema.mapa');
    }
};
