<?php

namespace App\Support\Compras;

use App\Models\Presupuesto\Capex;
use App\Models\Presupuesto\Partidagasto;
use App\Models\Presupuesto\Presupuesto;

/**
 * Valida que partidas de gasto y CAPEX en líneas de documentos de compras
 * correspondan al presupuesto vigente y al centro de costo destino de cada línea.
 */
final class ValidacionPresupuestoPartidaCapexLineas
{
    /**
     * @param  array<string, mixed>  $payload  Request all() con articulo_ids[], cantidades[], centrocostodestino_ids[],
     *                                          partidagasto_ids[], capex_ids[], empresa_id, centrocosto_id
     *
     * @throws \InvalidArgumentException
     */
    public static function validar(array $payload): void
    {
        $empresaId = (int) ($payload['empresa_id'] ?? 0);
        $ultimoPid = (int) Presupuesto::query()->max('id');
        if ($ultimoPid <= 0) {
            throw new \InvalidArgumentException('No hay presupuestos cargados.');
        }

        $articulo_ids = $payload['articulo_ids'] ?? [];
        if (! is_array($articulo_ids)) {
            return;
        }

        $n = count($articulo_ids);
        $ccCabecera = (int) ($payload['centrocosto_id'] ?? 0);

        for ($i = 0; $i < $n; $i++) {
            $articulo_id = $payload['articulo_ids'][$i] ?? null;
            if ($articulo_id === null || $articulo_id === '') {
                continue;
            }
            $cantidad = (float) ($payload['cantidades'][$i] ?? 0);
            if ($cantidad <= 0) {
                continue;
            }

            $ccLinea = isset($payload['centrocostodestino_ids'][$i]) && $payload['centrocostodestino_ids'][$i] !== ''
                ? (int) $payload['centrocostodestino_ids'][$i]
                : $ccCabecera;

            $pgId = $payload['partidagasto_ids'][$i] ?? null;
            if ($pgId !== null && $pgId !== '') {
                $pg = Partidagasto::query()->find((int) $pgId);
                if (! $pg) {
                    throw new \InvalidArgumentException('Línea '.($i + 1).': partida de gastos inválida.');
                }
                if ((int) $pg->empresa_id !== $empresaId) {
                    throw new \InvalidArgumentException('Línea '.($i + 1).': la partida no corresponde a la empresa del documento.');
                }
                if ((int) $pg->presupuesto_id !== $ultimoPid) {
                    throw new \InvalidArgumentException('Línea '.($i + 1).': la partida debe pertenecer al presupuesto vigente.');
                }
                if ((int) $pg->centrocosto_id !== $ccLinea) {
                    throw new \InvalidArgumentException('Línea '.($i + 1).': la partida no coincide con el centro de costo de destino de la línea.');
                }
            }

            $capexId = $payload['capex_ids'][$i] ?? null;
            if ($capexId !== null && $capexId !== '') {
                $cx = Capex::query()->find((int) $capexId);
                if (! $cx) {
                    throw new \InvalidArgumentException('Línea '.($i + 1).': CAPEX inválido.');
                }
                if ((int) $cx->empresa_id !== $empresaId) {
                    throw new \InvalidArgumentException('Línea '.($i + 1).': el CAPEX no corresponde a la empresa del documento.');
                }
                if ((string) $cx->estado !== 'ACTIVO') {
                    throw new \InvalidArgumentException('Línea '.($i + 1).': el CAPEX debe estar ACTIVO.');
                }
                if ((int) $cx->presupuesto_id !== $ultimoPid) {
                    throw new \InvalidArgumentException('Línea '.($i + 1).': el CAPEX debe pertenecer al presupuesto vigente.');
                }
                if ((int) $cx->centrocosto_id !== $ccLinea) {
                    throw new \InvalidArgumentException('Línea '.($i + 1).': el CAPEX no coincide con el centro de costo de destino de la línea.');
                }
            }
        }
    }
}
