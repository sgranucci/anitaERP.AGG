<?php

use App\Support\Compras\ProveedorSuspendidoOperacionSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Configuracion\ParametroSistemaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración general → Compras: severidad si el proveedor está suspendido
 * al cargar un comprobante (bloquear | avisar). Ferli arranca en avisar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('parametro_sistema')) {
            return;
        }

        $clave = ParametroSistemaSupport::CLAVE_COMPROBANTE_PROVEEDOR_SUSPENDIDO_SEVERIDAD;
        $def = ParametroSistemaSupport::definiciones()[$clave] ?? null;
        if (! is_array($def)) {
            return;
        }

        if (DB::table('parametro_sistema')->where('clave', $clave)->exists()) {
            return;
        }

        $valor = EntornoEmpresaSupport::esFerli()
            ? ProveedorSuspendidoOperacionSupport::SEVERIDAD_AVISAR
            : ProveedorSuspendidoOperacionSupport::SEVERIDAD_BLOQUEAR;

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
            ->where('clave', ParametroSistemaSupport::CLAVE_COMPROBANTE_PROVEEDOR_SUSPENDIDO_SEVERIDAD)
            ->delete();
        Cache::forget('parametro_sistema.mapa');
    }
};
