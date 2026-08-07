<?php

namespace App\Support\Sala;

use App\Models\Stock\Articulo;
use App\Services\Stock\ArticuloParteUnicaService;
use App\Support\Stock\ArticuloParteUnicaDisponibilidadSupport;
use App\Support\Stock\ArticuloParteUnicaEstados;
use App\Support\Stock\RecepcionProveedorParteUnicaSupport;
use Illuminate\Support\Facades\Log;

/**
 * Al transferir a laboratorio, el NPU de la línea de requisición debe viajar en la TM
 * y existir en articulo_parte_unica (sin NPUAL de stock: el ingreso lo hace la TRA).
 */
final class RequisicionSalaNpuTransferenciaSupport
{
    /**
     * @param  list<array{articulo_id: int, cantidad: float, numeroparte?: string}>  $lineas
     */
    public static function asegurarRegistrados(array $lineas): void
    {
        $service = app(ArticuloParteUnicaService::class);

        foreach ($lineas as $linea) {
            $articuloId = (int) ($linea['articulo_id'] ?? 0);
            $npuRaw = trim((string) ($linea['numeroparte'] ?? ''));
            if ($articuloId <= 0 || $npuRaw === '' || ! ctype_digit($npuRaw)) {
                continue;
            }

            $npu = (int) $npuRaw;
            if ($npu <= 0) {
                continue;
            }

            $articulo = Articulo::query()->find($articuloId);
            if (! RecepcionProveedorParteUnicaSupport::articuloManejaParteUnica($articulo)) {
                continue;
            }

            $parte = ArticuloParteUnicaDisponibilidadSupport::findPorNumeroparte($npu);
            if ($parte !== null) {
                if (ArticuloParteUnicaEstados::esBaja($parte->estado)) {
                    throw new \RuntimeException(
                        "El NPU {$npu} está dado de baja y no puede transferirse al laboratorio."
                    );
                }
                if ((int) $parte->articulo_id !== $articuloId) {
                    throw new \RuntimeException("El NPU {$npu} pertenece a otro artículo.");
                }

                continue;
            }

            $service->crear($articuloId, $npu);
            Log::info('RequisicionSala: NPU registrado al transferir a laboratorio (sin movimiento NPUAL)', [
                'articulo_id' => $articuloId,
                'numeroparte' => $npu,
            ]);
        }
    }
}
