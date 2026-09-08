<?php

use App\Support\Configuracion\ParametroSistemaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Umbral de escalamiento factura portal en Configuración general + textos Modulo aviso.
 */
return new class extends Migration
{
    public function up(): void
    {
        $clave = ParametroSistemaSupport::CLAVE_SUSCRIPCION_COMPROBANTE_DIA_ESCALAMIENTO;
        $def = ParametroSistemaSupport::definiciones()[$clave] ?? null;

        if ($def && Schema::hasTable('parametro_sistema')) {
            $valor = (string) config('compras.suscripcion_comprobantes.dia_escalamiento', 'ultimo');
            if ($valor === '' || $valor === '0') {
                $valor = 'ultimo';
            }
            DB::table('parametro_sistema')->updateOrInsert(
                ['clave' => $clave],
                [
                    'grupo' => $def['grupo'],
                    'etiqueta' => $def['etiqueta'],
                    'ayuda' => $def['ayuda'],
                    'tipo' => $def['tipo'],
                    'valor' => $valor,
                    'orden' => $def['orden'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            Cache::forget('parametro_sistema.mapa');
        }

        if (Schema::hasTable('modulo_aviso_tipo')) {
            DB::table('modulo_aviso_tipo')
                ->where('modulo', 'compras')
                ->where('codigo', 'suscripcion_comprobante_escalamiento')
                ->update([
                    'nombre' => 'Suscripción: escalamiento factura portal',
                    'descripcion' => 'Desde el umbral configurado (Configuración general → Escalamiento factura de portal; default último día del mes), '
                        .'avisa a gerencia (destinatarios de este tipo) si sigue faltando el PDF del portal.',
                    'mail_texto' => "Hay suscripciones con gasto en tarjeta sin factura de portal cargada "
                        ."(umbral de escalamiento del período).\n\n"
                        ."Cantidad: {cantidad}\n"
                        ."Fecha: {fecha}\n\n"
                        ."{pendientes}\n\n"
                        ."Listado: {link_consulta}\n",
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('parametro_sistema')) {
            DB::table('parametro_sistema')
                ->where('clave', ParametroSistemaSupport::CLAVE_SUSCRIPCION_COMPROBANTE_DIA_ESCALAMIENTO)
                ->delete();
            Cache::forget('parametro_sistema.mapa');
        }
    }
};
