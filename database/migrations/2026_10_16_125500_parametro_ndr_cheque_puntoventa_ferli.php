<?php

use App\Models\Ventas\Puntoventa;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Configuracion\ParametroSistemaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sucursal de la NDR por cheque rechazado. En Ferli arranca en el punto de venta 03333.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli() || ! Schema::hasTable('parametro_sistema')) {
            return;
        }

        $clave = ParametroSistemaSupport::CLAVE_NDR_CHEQUE_PUNTOVENTA_ID;
        $def = ParametroSistemaSupport::definiciones()[$clave] ?? null;
        if (! is_array($def) || DB::table('parametro_sistema')->where('clave', $clave)->exists()) {
            return;
        }

        $codigo = Puntoventa::normalizarCodigoArca('03333');
        $puntoventaId = 0;
        if ($codigo !== null && Schema::hasTable('puntoventa')) {
            $puntoventaId = (int) DB::table('puntoventa')
                ->where('codigo', $codigo)
                ->whereNull('deleted_at')
                ->where(function ($query): void {
                    $query->whereNull('estado')
                        ->orWhereNotIn('estado', ['I', 'S', 'i', 's']);
                })
                ->orderBy('id')
                ->value('id');
        }

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
        if (! Schema::hasTable('parametro_sistema')) {
            return;
        }

        DB::table('parametro_sistema')
            ->where('clave', ParametroSistemaSupport::CLAVE_NDR_CHEQUE_PUNTOVENTA_ID)
            ->delete();
        Cache::forget('parametro_sistema.mapa');
    }
};
