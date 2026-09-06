<?php

namespace App\Support\Contable\MayorConcepto;

use Illuminate\Support\Facades\DB;

/**
 * Ajusta Nro.OC. / ordencompra_id / Capex en la vista del mayor por concepto.
 *
 * El motor deja el nro COM (recepción). Solo presentación, sin tocar el motor:
 * - Busca en anitaERP: recepción COM → ordencompra
 * - Si hay OC, lee códigos Capex de las líneas (ordencompra_articulo)
 * - Si no hay OC en anitaERP, no enlaza (no sync Anita; la mayoría ya está local)
 * - No matchea por número crudo (chocaba con OC viejas / imprimía recepción)
 */
class MayorConceptoOrdencompraVistaEnricher
{
    /** @var array<string, array{nro: int, id: int, capex_codigo: string, capex_id: int}> */
    private array $cacheResolucion = [];

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    public function enriquecer(array $filas, ?MayorConceptoLectorInterface $bridge = null): array
    {
        unset($bridge);
        $this->cacheResolucion = [];

        $candidatos = [];
        foreach ($filas as $fila) {
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }
            $nro = (int) ($fila['nro_oc'] ?? 0);
            $empresaId = (int) ($fila['empresa_id'] ?? 0);
            if ($nro <= 0 || $empresaId <= 0) {
                continue;
            }
            $candidatos[$empresaId][$nro] = true;
        }

        if ($candidatos === []) {
            return $this->inicializarCapexVacios($filas);
        }

        foreach ($candidatos as $empresaId => $numeros) {
            $this->precargarDesdeRecepcionErp((int) $empresaId, array_map('intval', array_keys($numeros)));
        }

        foreach ($filas as $idx => $fila) {
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }

            $nroCom = (int) ($fila['nro_oc'] ?? 0);
            $empresaId = (int) ($fila['empresa_id'] ?? 0);
            if ($nroCom <= 0 || $empresaId <= 0) {
                $filas[$idx]['ordencompra_id'] = 0;
                $filas[$idx]['capex_codigo'] = '';
                $filas[$idx]['capex_id'] = 0;

                continue;
            }

            $resuelto = $this->cacheResolucion[$this->clave($empresaId, $nroCom)] ?? null;
            if ($resuelto === null) {
                $filas[$idx]['ordencompra_id'] = 0;
                $filas[$idx]['capex_codigo'] = '';
                $filas[$idx]['capex_id'] = 0;

                continue;
            }

            $filas[$idx]['nro_oc'] = $resuelto['nro'];
            $filas[$idx]['ordencompra_id'] = $resuelto['id'];
            $filas[$idx]['capex_codigo'] = $resuelto['capex_codigo'];
            $filas[$idx]['capex_id'] = $resuelto['capex_id'];
        }

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function inicializarCapexVacios(array $filas): array
    {
        foreach ($filas as $idx => $fila) {
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }
            $filas[$idx]['capex_codigo'] = $fila['capex_codigo'] ?? '';
            $filas[$idx]['capex_id'] = (int) ($fila['capex_id'] ?? 0);
        }

        return $filas;
    }

    /**
     * @param  list<int>  $numerosCom
     */
    private function precargarDesdeRecepcionErp(int $empresaId, array $numerosCom): void
    {
        if ($empresaId <= 0 || $numerosCom === []) {
            return;
        }

        $recepciones = DB::table('recepcion_proveedor')
            ->where('empresa_id', $empresaId)
            ->where('anita_tipo', 'COM')
            ->where(function ($q) use ($numerosCom) {
                $q->whereIn('anita_nro', $numerosCom)
                    ->orWhereIn('numerorecepcion', $numerosCom);
            })
            ->whereNotNull('ordencompra_id')
            ->where('ordencompra_id', '>', 0)
            ->get(['anita_nro', 'numerorecepcion', 'ordencompra_id']);

        if ($recepciones->isEmpty()) {
            return;
        }

        $ocIds = $recepciones->pluck('ordencompra_id')->unique()->filter()->map(fn ($id) => (int) $id)->all();

        $ocs = DB::table('ordencompra')
            ->whereIn('id', $ocIds)
            ->get(['id', 'numeroordencompra'])
            ->keyBy('id');

        $capexPorOc = $this->mapearCapexPorOrdencompra($ocIds);

        foreach ($recepciones as $rp) {
            $ocId = (int) $rp->ordencompra_id;
            $oc = $ocs->get($ocId);
            if ($oc === null) {
                continue;
            }
            $nroOc = (int) $oc->numeroordencompra;
            if ($nroOc <= 0) {
                continue;
            }

            $capex = $capexPorOc[$ocId] ?? ['codigo' => '', 'id' => 0];

            foreach ([(int) $rp->anita_nro, (int) $rp->numerorecepcion] as $nroCom) {
                if ($nroCom <= 0) {
                    continue;
                }
                $this->cacheResolucion[$this->clave($empresaId, $nroCom)] = [
                    'nro' => $nroOc,
                    'id' => $ocId,
                    'capex_codigo' => $capex['codigo'],
                    'capex_id' => $capex['id'],
                ];
            }
        }
    }

    /**
     * Códigos Capex distintos de las líneas de cada OC.
     * Si hay uno solo, deja capex_id para enlace; si hay varios, solo el texto unido.
     *
     * @param  list<int>  $ordencompraIds
     * @return array<int, array{codigo: string, id: int}>
     */
    private function mapearCapexPorOrdencompra(array $ordencompraIds): array
    {
        if ($ordencompraIds === []) {
            return [];
        }

        $filas = DB::table('ordencompra_articulo as oa')
            ->join('capex as c', 'c.id', '=', 'oa.capex_id')
            ->whereIn('oa.ordencompra_id', $ordencompraIds)
            ->whereNotNull('oa.capex_id')
            ->where('oa.capex_id', '>', 0)
            ->whereNotNull('c.codigo')
            ->where('c.codigo', '!=', '')
            ->orderBy('c.codigo')
            ->get(['oa.ordencompra_id', 'c.id', 'c.codigo']);

        $mapa = [];
        foreach ($filas as $fila) {
            $ocId = (int) $fila->ordencompra_id;
            $codigo = trim((string) $fila->codigo);
            $capexId = (int) $fila->id;
            if ($ocId <= 0 || $codigo === '') {
                continue;
            }

            if (! isset($mapa[$ocId])) {
                $mapa[$ocId] = [
                    'codigos' => [],
                    'ids' => [],
                ];
            }

            if (! in_array($codigo, $mapa[$ocId]['codigos'], true)) {
                $mapa[$ocId]['codigos'][] = $codigo;
                $mapa[$ocId]['ids'][] = $capexId;
            }
        }

        $resultado = [];
        foreach ($mapa as $ocId => $datos) {
            $codigos = $datos['codigos'];
            $resultado[$ocId] = [
                'codigo' => implode(', ', $codigos),
                'id' => count($codigos) === 1 ? (int) ($datos['ids'][0] ?? 0) : 0,
            ];
        }

        return $resultado;
    }

    private function clave(int $empresaId, int $nroCom): string
    {
        return $empresaId.'|'.$nroCom;
    }
}
