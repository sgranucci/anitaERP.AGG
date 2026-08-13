<?php

namespace App\Support\Compras;

use App\Models\Presupuesto\Capex;
use App\Models\Presupuesto\Partidagasto;
use App\Models\Presupuesto\Presupuesto;

/**
 * Valida que partidas de gasto y CAPEX en líneas de documentos de compras
 * correspondan al presupuesto vigente y al centro de costo destino de cada línea.
 *
 * En edición, los IDs ya grabados en el documento se aceptan aunque pertenezcan
 * a un presupuesto anterior (OC / requisición de un ejercicio viejo).
 *
 * Si `compras.oc_pedir_partida_capex` es false (El Bierzo), no exige ni valida.
 */
final class ValidacionPresupuestoPartidaCapexLineas
{
    /**
     * @param  array<string, mixed>  $payload  Request all() con articulo_ids[], cantidades[], centrocostodestino_ids[],
     *                                          partidagasto_ids[], capex_ids[], empresa_id, centrocosto_id
     * @param  array{capex_ids?: list<int>, partidagasto_ids?: list<int>}  $idsYaAsignados
     *         IDs ya persistidos. IDs nuevos siguen exigiendo presupuesto vigente y CAPEX ACTIVO.
     *
     * @throws \InvalidArgumentException
     */
    public static function validar(array $payload, array $idsYaAsignados = []): void
    {
        if (! OrdencompraUiConfigSupport::pedirPartidaCapex()) {
            return;
        }

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
        $capexYa = self::idsEnteros($idsYaAsignados['capex_ids'] ?? []);
        $pgYa = self::idsEnteros($idsYaAsignados['partidagasto_ids'] ?? []);

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
                $partidaYaAsignada = in_array((int) $pgId, $pgYa, true);
                if (! $partidaYaAsignada && (int) $pg->presupuesto_id !== $ultimoPid) {
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
                $capexYaAsignado = in_array((int) $capexId, $capexYa, true);
                if (! $capexYaAsignado && (string) $cx->estado !== 'ACTIVO') {
                    throw new \InvalidArgumentException('Línea '.($i + 1).': el CAPEX debe estar ACTIVO.');
                }
                if (! $capexYaAsignado && (int) $cx->presupuesto_id !== $ultimoPid) {
                    throw new \InvalidArgumentException('Línea '.($i + 1).': el CAPEX debe pertenecer al presupuesto vigente.');
                }
                if ((int) $cx->centrocosto_id !== $ccLinea) {
                    throw new \InvalidArgumentException('Línea '.($i + 1).': el CAPEX no coincide con el centro de costo de destino de la línea.');
                }
            }
        }
    }

    /**
     * Extrae IDs de CAPEX / partida ya grabados en las líneas del documento.
     *
     * @param  iterable<mixed>  $lineas
     * @return array{capex_ids: list<int>, partidagasto_ids: list<int>}
     */
    public static function idsAsignadosDesdeLineas(iterable $lineas): array
    {
        $capex = [];
        $pg = [];
        foreach ($lineas as $linea) {
            $capexId = (int) ($linea->capex_id ?? 0);
            if ($capexId > 0) {
                $capex[] = $capexId;
            }
            $pgId = (int) ($linea->partidagasto_id ?? 0);
            if ($pgId > 0) {
                $pg[] = $pgId;
            }
        }

        return [
            'capex_ids' => array_values(array_unique($capex)),
            'partidagasto_ids' => array_values(array_unique($pg)),
        ];
    }

    /**
     * @param  mixed  $ids
     * @return list<int>
     */
    private static function idsEnteros($ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        $out = [];
        foreach ($ids as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $out[] = $n;
            }
        }

        return $out;
    }
}
