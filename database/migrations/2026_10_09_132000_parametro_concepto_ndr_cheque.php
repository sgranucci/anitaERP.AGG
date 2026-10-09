<?php

use App\Support\Configuracion\ParametroSistemaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Concepto de IVA compra del débito interno al proveedor por cheque rechazado.
 * El valor inicial es el que ya usan esos comprobantes, si existen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('parametro_sistema')) {
            return;
        }

        $clave = ParametroSistemaSupport::CLAVE_NDR_CHEQUE_CONCEPTO_IVACOMPRA_ID;
        $def = ParametroSistemaSupport::definiciones()[$clave] ?? null;
        if (! is_array($def)) {
            return;
        }

        if (DB::table('parametro_sistema')->where('clave', $clave)->exists()) {
            return;
        }

        $conceptoId = 0;
        if (Schema::hasTable('cheque') && Schema::hasTable('comprobante_proveedor_concepto')) {
            $conceptoId = (int) DB::table('comprobante_proveedor_concepto as cpc')
                ->join('cheque as ch', 'ch.comprobante_proveedor_id', '=', 'cpc.comprobante_proveedor_id')
                ->whereNotNull('ch.comprobante_proveedor_id')
                ->orderByDesc('cpc.id')
                ->value('cpc.concepto_ivacompra_id');
        }

        DB::table('parametro_sistema')->insert([
            'clave' => $clave,
            'grupo' => $def['grupo'],
            'etiqueta' => $def['etiqueta'],
            'ayuda' => $def['ayuda'],
            'tipo' => $def['tipo'],
            'valor' => $conceptoId > 0 ? (string) $conceptoId : '',
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
            ->where('clave', ParametroSistemaSupport::CLAVE_NDR_CHEQUE_CONCEPTO_IVACOMPRA_ID)
            ->delete();
        Cache::forget('parametro_sistema.mapa');
    }
};
