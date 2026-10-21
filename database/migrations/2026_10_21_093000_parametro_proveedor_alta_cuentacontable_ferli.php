<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Configuracion\ParametroSistemaSupport;
use App\Support\Contable\CuentacontableArbolSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuenta de proveedores que se propone al dar de alta un proveedor.
 * En Ferli arranca con la cuenta que ya usa la mayoría de los proveedores.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli() || ! Schema::hasTable('parametro_sistema')) {
            return;
        }

        $clave = ParametroSistemaSupport::CLAVE_PROVEEDOR_ALTA_CUENTACONTABLE_ID;
        $def = ParametroSistemaSupport::definiciones()[$clave] ?? null;
        if (! is_array($def) || DB::table('parametro_sistema')->where('clave', $clave)->exists()) {
            return;
        }

        DB::table('parametro_sistema')->insert([
            'clave' => $clave,
            'grupo' => $def['grupo'],
            'etiqueta' => $def['etiqueta'],
            'ayuda' => $def['ayuda'],
            'tipo' => $def['tipo'],
            'valor' => $this->cuentaInicialId(),
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
            ->where('clave', ParametroSistemaSupport::CLAVE_PROVEEDOR_ALTA_CUENTACONTABLE_ID)
            ->delete();
        Cache::forget('parametro_sistema.mapa');
    }

    private function cuentaInicialId(): string
    {
        if (! Schema::hasTable('proveedor') || ! Schema::hasTable('cuentacontable')) {
            return '';
        }

        $fila = DB::table('proveedor')
            ->select('cuentacontable_id')
            ->selectRaw('COUNT(*) as n')
            ->where('cuentacontable_id', '>', 0)
            ->groupBy('cuentacontable_id')
            ->orderByDesc('n')
            ->first();
        $cuentaId = (int) ($fila->cuentacontable_id ?? 0);
        if ($cuentaId <= 0) {
            return '';
        }

        $imputable = DB::table('cuentacontable')
            ->where('id', $cuentaId)
            ->where('tipocuenta', CuentacontableArbolSupport::TIPO_IMPUTABLE)
            ->exists();

        return $imputable ? (string) $cuentaId : '';
    }
};
