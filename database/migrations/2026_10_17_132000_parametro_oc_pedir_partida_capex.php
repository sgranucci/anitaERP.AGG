<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Configuracion\ParametroSistemaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración general → Compras: pedir partida/CAPEX en OC y requisición.
 * Siembra TRUE solo en AGG; en el resto (Crown, Bierzo, Ferli, etc.) FALSE.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('parametro_sistema')) {
            return;
        }

        $clave = ParametroSistemaSupport::CLAVE_OC_PEDIR_PARTIDA_CAPEX;
        $def = ParametroSistemaSupport::definiciones()[$clave] ?? null;
        if (! is_array($def)) {
            return;
        }

        $valor = EntornoEmpresaSupport::esAgg() ? '1' : '0';

        $existe = DB::table('parametro_sistema')->where('clave', $clave)->exists();
        if ($existe) {
            // No pisar un valor que el operador ya haya cambiado en AGG;
            // en no-AGG forzar apagado solo si nunca existió (arriba).
            return;
        }

        DB::table('parametro_sistema')->insert([
            'clave' => $clave,
            'grupo' => $def['grupo'],
            'etiqueta' => $def['etiqueta'],
            'ayuda' => $def['ayuda'],
            'tipo' => $def['tipo'],
            'valor' => $valor,
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
            ->where('clave', ParametroSistemaSupport::CLAVE_OC_PEDIR_PARTIDA_CAPEX)
            ->delete();
        Cache::forget('parametro_sistema.mapa');
    }
};
