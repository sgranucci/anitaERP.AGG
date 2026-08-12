<?php

namespace App\Support\Contable\MayorConcepto;

use Illuminate\Support\Facades\DB;

/**
 * Ajusta Nro.OC. / ordencompra_id en la vista del mayor por concepto.
 *
 * El motor guarda el nro del comprobante COM (recepción Anita). En pantalla eso
 * hace que el link/impresión apunte a la recepción o a una OC vieja con el mismo
 * número. Acá se resuelve la OC real sin tocar el motor de imputación.
 */
class MayorConceptoOrdencompraVistaEnricher
{
    /** @var array<string, array{nro: int, id: int}|null> */
    private array $cacheResolucion = [];

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    public function enriquecer(array $filas, ?MayorConceptoAnitaBridgeReader $bridge = null): array
    {
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
            return $filas;
        }

        foreach ($candidatos as $empresaId => $numeros) {
            $this->precargarDesdeRecepcionErp((int) $empresaId, array_keys($numeros));
        }

        foreach ($filas as $idx => $fila) {
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }

            $nroCom = (int) ($fila['nro_oc'] ?? 0);
            $empresaId = (int) ($fila['empresa_id'] ?? 0);
            if ($nroCom <= 0 || $empresaId <= 0) {
                $filas[$idx]['ordencompra_id'] = 0;

                continue;
            }

            $resuelto = $this->resolver($empresaId, $nroCom, $bridge);
            if ($resuelto === null) {
                // No enlazar por número crudo: suele ser COM/recepción u OC ajena.
                $filas[$idx]['ordencompra_id'] = 0;

                continue;
            }

            $filas[$idx]['nro_oc'] = $resuelto['nro'];
            $filas[$idx]['ordencompra_id'] = $resuelto['id'];
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

        $ocIds = $recepciones->pluck('ordencompra_id')->unique()->filter()->all();
        $ocs = DB::table('ordencompra')
            ->whereIn('id', $ocIds)
            ->get(['id', 'numeroordencompra'])
            ->keyBy('id');

        foreach ($recepciones as $rp) {
            $oc = $ocs->get((int) $rp->ordencompra_id);
            if ($oc === null) {
                continue;
            }
            $nroOc = (int) $oc->numeroordencompra;
            $ocId = (int) $oc->id;
            if ($nroOc <= 0 || $ocId <= 0) {
                continue;
            }

            foreach ([(int) $rp->anita_nro, (int) $rp->numerorecepcion] as $nroCom) {
                if ($nroCom <= 0) {
                    continue;
                }
                $this->cacheResolucion[$this->clave($empresaId, $nroCom)] = [
                    'nro' => $nroOc,
                    'id' => $ocId,
                ];
            }
        }
    }

    /**
     * @return array{nro: int, id: int}|null
     */
    private function resolver(int $empresaId, int $nroCom, ?MayorConceptoAnitaBridgeReader $bridge): ?array
    {
        $clave = $this->clave($empresaId, $nroCom);
        if (array_key_exists($clave, $this->cacheResolucion)) {
            return $this->cacheResolucion[$clave];
        }

        $desdeAnita = $this->resolverDesdePepHermano($empresaId, $nroCom, $bridge);
        $this->cacheResolucion[$clave] = $desdeAnita;

        return $desdeAnita;
    }

    /**
     * COM referenciada desde una factura suele tener PEP hermana = OC real.
     *
     * @return array{nro: int, id: int}|null
     */
    private function resolverDesdePepHermano(
        int $empresaId,
        int $nroCom,
        ?MayorConceptoAnitaBridgeReader $bridge,
    ): ?array {
        if ($bridge === null || $nroCom <= 0) {
            return null;
        }

        $nroOc = $this->nroOcDesdePepHermano($bridge, $empresaId, $nroCom);
        if ($nroOc <= 0) {
            return null;
        }

        $ocId = (int) DB::table('ordencompra')
            ->where('empresa_id', $empresaId)
            ->where('numeroordencompra', $nroOc)
            ->value('id');

        if ($ocId <= 0) {
            $ocId = (int) DB::table('ordencompra')
                ->where('numeroordencompra', $nroOc)
                ->orderBy('id')
                ->value('id');
        }

        if ($ocId <= 0) {
            return null;
        }

        return ['nro' => $nroOc, 'id' => $ocId];
    }

    private function nroOcDesdePepHermano(
        MayorConceptoAnitaBridgeReader $bridge,
        int $empresaId,
        int $nroCom,
    ): int {
        $errores = [];
        $intentos = [
            ['X', $empresaId],
            ['X', 1],
            [' ', 0],
        ];

        $refs = [];
        foreach ($intentos as [$letra, $suc]) {
            $refs = $bridge->cargarAplicpedPorReferencia('COM', $letra, (int) $suc, $nroCom, '', $errores);
            if ($refs !== []) {
                break;
            }
        }

        foreach ($refs as $ref) {
            $prov = trim((string) ($ref->aplp_proveedor ?? ''));
            $tipo = trim((string) ($ref->aplp_tipo ?? ''));
            $letra = trim((string) ($ref->aplp_letra ?? ' '));
            $suc = (int) ($ref->aplp_sucursal ?? 0);
            $nro = (int) ($ref->aplp_nro ?? 0);
            if ($prov === '' || $tipo === '' || $nro <= 0) {
                continue;
            }

            $apl = $bridge->cargarAplicpedFactura($prov, $tipo, $letra, $suc, $nro, $errores);
            foreach ($apl as $linea) {
                if (strtoupper(trim((string) ($linea->aplp_ref_tipo ?? ''))) !== 'PEP') {
                    continue;
                }
                $pep = (int) ($linea->aplp_ref_nro ?? 0);
                if ($pep > 0 && $pep !== $nroCom) {
                    return $pep;
                }
            }
        }

        return 0;
    }

    private function clave(int $empresaId, int $nroCom): string
    {
        return $empresaId.'|'.$nroCom;
    }
}
