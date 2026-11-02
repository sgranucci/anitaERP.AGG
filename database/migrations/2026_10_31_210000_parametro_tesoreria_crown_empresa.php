<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Configuracion\ParametroSistemaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Empresa de sueldos que la bóveda Crown usa para el legajo. Default: empresa 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('parametro_sistema')) {
            return;
        }

        $clave = ParametroSistemaSupport::CLAVE_TESORERIA_CROWN_EMPRESA_ID;
        $def = ParametroSistemaSupport::definiciones()[$clave] ?? null;
        if (! is_array($def) || DB::table('parametro_sistema')->where('clave', $clave)->exists()) {
            return;
        }

        $empresaId = Schema::hasTable('empresa') && DB::table('empresa')->where('id', 1)->exists()
            ? '1'
            : '';

        DB::table('parametro_sistema')->insert([
            'clave' => $clave,
            'grupo' => $def['grupo'],
            'etiqueta' => $def['etiqueta'],
            'ayuda' => $def['ayuda'],
            'tipo' => $def['tipo'],
            'valor' => $empresaId,
            'orden' => $def['orden'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Cache::forget('parametro_sistema.mapa');
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown() || ! Schema::hasTable('parametro_sistema')) {
            return;
        }

        DB::table('parametro_sistema')
            ->where('clave', ParametroSistemaSupport::CLAVE_TESORERIA_CROWN_EMPRESA_ID)
            ->delete();
        Cache::forget('parametro_sistema.mapa');
    }
};
