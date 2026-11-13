<?php

namespace App\Support\Contable;

use Illuminate\Support\Facades\DB;

/**
 * Rellena asiento.ordencompra_id desde recepción o comprobante vinculado.
 *
 * Histórico Anita (ene–ago 2026): la OC quedó en la recepción/CP pero no en el asiento.
 * El mayor ya la muestra vía enricher; este backfill materializa el FK del asiento.
 */
final class AsientoOrdencompraBackfillSupport
{
    /**
     * Fuente: recepción si tiene OC; si no, comprobante. Si ambas con OC distinta → omitir.
     *
     * @return array{ordencompra_id: int, origen: string}|null
     */
    public static function resolverOc(?int $ocRec, ?int $ocCp): ?array
    {
        $ocRec = (int) ($ocRec ?? 0);
        $ocCp = (int) ($ocCp ?? 0);

        if ($ocRec > 0 && $ocCp > 0 && $ocRec !== $ocCp) {
            return null;
        }

        if ($ocRec > 0) {
            return ['ordencompra_id' => $ocRec, 'origen' => 'recepcion'];
        }

        if ($ocCp > 0) {
            return ['ordencompra_id' => $ocCp, 'origen' => 'comprobante'];
        }

        return null;
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array{
     *   candidatos: int,
     *   desde_recepcion: int,
     *   desde_comprobante: int,
     *   conflictos: int,
     *   actualizados: int,
     *   ejemplos: list<array<string, mixed>>
     * }
     */
    public static function ejecutar(
        string $desde,
        string $hasta,
        array $empresaIds,
        bool $persistir,
    ): array {
        $empresaIds = array_values(array_filter(array_map('intval', $empresaIds), static fn (int $id) => $id > 0));
        if ($empresaIds === [] || $desde === '' || $hasta === '') {
            return [
                'candidatos' => 0,
                'desde_recepcion' => 0,
                'desde_comprobante' => 0,
                'conflictos' => 0,
                'actualizados' => 0,
                'ejemplos' => [],
            ];
        }

        $filas = DB::table('asiento as a')
            ->leftJoin('recepcion_proveedor as rp', 'rp.id', '=', 'a.recepcionproveedor_id')
            ->leftJoin('comprobante_proveedor as cp', 'cp.id', '=', 'a.comprobante_proveedor_id')
            ->whereNull('a.ordencompra_id')
            ->whereBetween('a.fecha', [$desde, $hasta])
            ->whereIn('a.empresa_id', $empresaIds)
            ->where(function ($q) {
                $q->where('rp.ordencompra_id', '>', 0)
                    ->orWhere('cp.ordencompra_id', '>', 0);
            })
            ->orderBy('a.id')
            ->get([
                'a.id',
                'a.empresa_id',
                'a.numeroasiento',
                'a.fecha',
                'a.anita_tipo',
                'rp.ordencompra_id as oc_rec',
                'cp.ordencompra_id as oc_cp',
            ]);

        $desdeRec = 0;
        $desdeCp = 0;
        $conflictos = 0;
        $actualizados = 0;
        $ejemplos = [];
        /** @var list<array{id: int, ordencompra_id: int}> $updates */
        $updates = [];

        foreach ($filas as $fila) {
            $resuelto = self::resolverOc(
                isset($fila->oc_rec) ? (int) $fila->oc_rec : null,
                isset($fila->oc_cp) ? (int) $fila->oc_cp : null,
            );

            if ($resuelto === null) {
                $ocRec = (int) ($fila->oc_rec ?? 0);
                $ocCp = (int) ($fila->oc_cp ?? 0);
                if ($ocRec > 0 && $ocCp > 0 && $ocRec !== $ocCp) {
                    $conflictos++;
                    if (count($ejemplos) < 5) {
                        $ejemplos[] = [
                            'id' => (int) $fila->id,
                            'numeroasiento' => $fila->numeroasiento,
                            'conflicto' => true,
                            'oc_rec' => $ocRec,
                            'oc_cp' => $ocCp,
                        ];
                    }
                }

                continue;
            }

            if ($resuelto['origen'] === 'recepcion') {
                $desdeRec++;
            } else {
                $desdeCp++;
            }

            if (count($ejemplos) < 8) {
                $ejemplos[] = [
                    'id' => (int) $fila->id,
                    'empresa_id' => (int) $fila->empresa_id,
                    'numeroasiento' => $fila->numeroasiento,
                    'fecha' => (string) $fila->fecha,
                    'anita_tipo' => $fila->anita_tipo,
                    'ordencompra_id' => $resuelto['ordencompra_id'],
                    'origen' => $resuelto['origen'],
                ];
            }

            $updates[] = [
                'id' => (int) $fila->id,
                'ordencompra_id' => $resuelto['ordencompra_id'],
            ];
        }

        if ($persistir && $updates !== []) {
            $now = now();
            foreach (array_chunk($updates, 500) as $chunk) {
                foreach ($chunk as $u) {
                    $actualizados += DB::table('asiento')
                        ->where('id', $u['id'])
                        ->whereNull('ordencompra_id')
                        ->update([
                            'ordencompra_id' => $u['ordencompra_id'],
                            'updated_at' => $now,
                        ]);
                }
            }
        }

        return [
            'candidatos' => count($updates),
            'desde_recepcion' => $desdeRec,
            'desde_comprobante' => $desdeCp,
            'conflictos' => $conflictos,
            'actualizados' => $actualizados,
            'ejemplos' => $ejemplos,
        ];
    }
}
