<?php

use App\Models\Configuracion\UsoSalidaImpresora;
use App\Support\Configuracion\SeteoSalidaProgramaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $mapa = [
            'pedido' => [SeteoSalidaProgramaSupport::VENTAS_PEDIDO],
            'pedidos' => [SeteoSalidaProgramaSupport::VENTAS_PEDIDO],
            'emision ot' => [SeteoSalidaProgramaSupport::VENTAS_REPEMISIONOT],
            'emision de ot' => [SeteoSalidaProgramaSupport::VENTAS_REPEMISIONOT],
            'etiqueta' => [SeteoSalidaProgramaSupport::STOCK_ARTICULO, SeteoSalidaProgramaSupport::VENTAS_REPETIQUETAOT],
            'etiquetas' => [SeteoSalidaProgramaSupport::STOCK_ARTICULO, SeteoSalidaProgramaSupport::VENTAS_REPETIQUETAOT],
            'uif' => [SeteoSalidaProgramaSupport::UIF_EXPORTA_OPERACION],
            'exportacion uif' => [SeteoSalidaProgramaSupport::UIF_EXPORTA_OPERACION],
        ];

        UsoSalidaImpresora::query()->each(function (UsoSalidaImpresora $uso) use ($mapa) {
            if (! empty($uso->programas_destino)) {
                return;
            }

            $clave = Str::lower(Str::ascii(trim($uso->nombre)));

            if (! isset($mapa[$clave])) {
                return;
            }

            $uso->programas_destino = $mapa[$clave];
            $uso->save();
        });
    }

    public function down(): void
    {
        // Sin reversión: los destinos configurados manualmente no deben borrarse al rollback.
    }
};
