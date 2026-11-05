<?php

namespace App\Support\Contable\MayorConcepto;

use Illuminate\Support\Facades\DB;

/**
 * Completa Cheque, Nro.OC. y Capex en la vista del mayor por concepto.
 *
 * Lee la operación que ya armó el motor (asiento → pago → factura / cheque).
 * No reparte importes ni cambia Debe, Haber, retenciones, cuenta o concepto.
 * Si la fila ya trae cheque o número de OC, ese valor se deja como está.
 */
class MayorConceptoOperacionVistaEnricher
{
    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    public function enriquecer(array $filas): array
    {
        $asientoIds = [];
        foreach ($filas as $fila) {
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }
            if (! $this->faltaCheque($fila) && ! $this->faltaOc($fila)) {
                continue;
            }
            $asientoId = $this->asientoId($fila);
            if ($asientoId > 0) {
                $asientoIds[$asientoId] = true;
            }
        }

        $meta = $asientoIds === []
            ? []
            : $this->resolver(array_map('intval', array_keys($asientoIds)));

        return $this->aplicar($filas, $meta);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<int, array{cheque: string, nro_oc: string, ordencompra_id: int, capex_codigo: string, capex_id: int}>  $metaPorAsiento
     * @return list<array<string, mixed>>
     */
    public function aplicar(array $filas, array $metaPorAsiento): array
    {
        foreach ($filas as $idx => $fila) {
            unset($filas[$idx]['asiento_operacion_id']);
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }

            $meta = $metaPorAsiento[$this->asientoId($fila)] ?? null;
            if ($meta === null) {
                continue;
            }

            if ($this->faltaCheque($fila) && $meta['cheque'] !== '') {
                $filas[$idx]['cheque'] = $meta['cheque'];
            }

            if ($this->faltaOc($fila) && $meta['nro_oc'] !== '') {
                $filas[$idx]['nro_oc'] = $meta['nro_oc'];
                $filas[$idx]['ordencompra_id'] = $meta['ordencompra_id'];
                if (trim((string) ($fila['capex_codigo'] ?? '')) === '') {
                    $filas[$idx]['capex_codigo'] = $meta['capex_codigo'];
                    $filas[$idx]['capex_id'] = $meta['capex_id'];
                }
            }
        }

        return $filas;
    }

    /**
     * @param  list<int>  $asientoIds
     * @return array<int, array{cheque: string, nro_oc: string, ordencompra_id: int, capex_codigo: string, capex_id: int}>
     */
    private function resolver(array $asientoIds): array
    {
        $asientoIds = array_values(array_filter(array_map('intval', $asientoIds), fn (int $id) => $id > 0));
        if ($asientoIds === []) {
            return [];
        }

        $pagoPorAsiento = [];
        $cajaPorAsiento = [];
        /** @var array<int, array<int, true>> $ocIdsPorAsiento */
        $ocIdsPorAsiento = [];

        foreach (array_chunk($asientoIds, 800) as $lote) {
            $filas = DB::table('asiento')
                ->whereIn('id', $lote)
                ->get(['id', 'pagoproveedor_id', 'caja_movimiento_id', 'ordencompra_id', 'comprobante_proveedor_id']);

            $comprobantes = [];
            foreach ($filas as $fila) {
                $asientoId = (int) $fila->id;
                $pagoId = (int) ($fila->pagoproveedor_id ?? 0);
                $cajaId = (int) ($fila->caja_movimiento_id ?? 0);
                $ocId = (int) ($fila->ordencompra_id ?? 0);
                $cpId = (int) ($fila->comprobante_proveedor_id ?? 0);
                if ($pagoId > 0) {
                    $pagoPorAsiento[$asientoId] = $pagoId;
                }
                if ($cajaId > 0) {
                    $cajaPorAsiento[$asientoId] = $cajaId;
                }
                if ($ocId > 0) {
                    $ocIdsPorAsiento[$asientoId][$ocId] = true;
                }
                if ($cpId > 0) {
                    $comprobantes[$cpId][] = $asientoId;
                }
            }

            if ($comprobantes !== []) {
                $ocs = DB::table('comprobante_proveedor')
                    ->whereIn('id', array_keys($comprobantes))
                    ->where('ordencompra_id', '>', 0)
                    ->get(['id', 'ordencompra_id']);
                foreach ($ocs as $oc) {
                    foreach ($comprobantes[(int) $oc->id] ?? [] as $asientoId) {
                        $ocIdsPorAsiento[$asientoId][(int) $oc->ordencompra_id] = true;
                    }
                }
            }
        }

        $this->sumarOcDeRecepcion($asientoIds, $ocIdsPorAsiento);
        $this->sumarOcDePagos($pagoPorAsiento, $ocIdsPorAsiento);
        $cheques = $this->chequesPorAsiento($pagoPorAsiento, $cajaPorAsiento);
        $presentacion = $this->presentarOrdenes($ocIdsPorAsiento);

        $salida = [];
        foreach ($asientoIds as $asientoId) {
            $oc = $presentacion[$asientoId] ?? ['nro_oc' => '', 'ordencompra_id' => 0, 'capex_codigo' => '', 'capex_id' => 0];
            $cheque = $cheques[$asientoId] ?? '';
            if ($cheque === '' && $oc['nro_oc'] === '') {
                continue;
            }
            $salida[$asientoId] = [
                'cheque' => $cheque,
                'nro_oc' => $oc['nro_oc'],
                'ordencompra_id' => $oc['ordencompra_id'],
                'capex_codigo' => $oc['capex_codigo'],
                'capex_id' => $oc['capex_id'],
            ];
        }

        return $salida;
    }

    /**
     * @param  list<int>  $asientoIds
     * @param  array<int, array<int, true>>  $ocIdsPorAsiento
     */
    private function sumarOcDeRecepcion(array $asientoIds, array &$ocIdsPorAsiento): void
    {
        foreach (array_chunk($asientoIds, 800) as $lote) {
            $filas = DB::table('recepcion_proveedor')
                ->whereIn('asiento_id', $lote)
                ->where('ordencompra_id', '>', 0)
                ->get(['asiento_id', 'ordencompra_id']);
            foreach ($filas as $fila) {
                $ocIdsPorAsiento[(int) $fila->asiento_id][(int) $fila->ordencompra_id] = true;
            }
        }
    }

    /**
     * @param  array<int, int>  $pagoPorAsiento
     * @param  array<int, array<int, true>>  $ocIdsPorAsiento
     */
    private function sumarOcDePagos(array $pagoPorAsiento, array &$ocIdsPorAsiento): void
    {
        if ($pagoPorAsiento === []) {
            return;
        }

        $asientosPorPago = [];
        foreach ($pagoPorAsiento as $asientoId => $pagoId) {
            $asientosPorPago[$pagoId][] = $asientoId;
        }

        foreach (array_chunk(array_keys($asientosPorPago), 800) as $lote) {
            $filas = DB::table('pagoproveedor_comprobante as pc')
                ->join('proveedor_cuentacorriente as pcc', 'pcc.id', '=', 'pc.proveedor_cuentacorriente_id')
                ->join('comprobante_proveedor as cp', 'cp.id', '=', 'pcc.comprobante_proveedor_id')
                ->whereIn('pc.pagoproveedor_id', $lote)
                ->where('cp.ordencompra_id', '>', 0)
                ->get(['pc.pagoproveedor_id', 'cp.ordencompra_id']);
            foreach ($filas as $fila) {
                foreach ($asientosPorPago[(int) $fila->pagoproveedor_id] ?? [] as $asientoId) {
                    $ocIdsPorAsiento[$asientoId][(int) $fila->ordencompra_id] = true;
                }
            }
        }
    }

    /**
     * @param  array<int, int>  $pagoPorAsiento
     * @param  array<int, int>  $cajaPorAsiento
     * @return array<int, string>
     */
    private function chequesPorAsiento(array $pagoPorAsiento, array $cajaPorAsiento): array
    {
        /** @var array<int, array<string, true>> $numeros */
        $numeros = [];

        $asientosPorPago = [];
        foreach ($pagoPorAsiento as $asientoId => $pagoId) {
            $asientosPorPago[$pagoId][] = $asientoId;
        }
        foreach (array_chunk(array_keys($asientosPorPago), 800) as $lote) {
            $filas = DB::table('cheque')
                ->whereIn('pagoproveedor_id', $lote)
                ->where('numerocheque', '!=', '')
                ->get(['pagoproveedor_id', 'numerocheque']);
            foreach ($filas as $fila) {
                $nro = trim((string) $fila->numerocheque);
                if ($nro === '' || $nro === '0') {
                    continue;
                }
                foreach ($asientosPorPago[(int) $fila->pagoproveedor_id] ?? [] as $asientoId) {
                    $numeros[$asientoId][$nro] = true;
                }
            }
        }

        $asientosPorCaja = [];
        foreach ($cajaPorAsiento as $asientoId => $cajaId) {
            $asientosPorCaja[$cajaId][] = $asientoId;
        }
        foreach (array_chunk(array_keys($asientosPorCaja), 800) as $lote) {
            $filas = DB::table('cheque')
                ->whereIn('caja_movimiento_id', $lote)
                ->where('numerocheque', '!=', '')
                ->get(['caja_movimiento_id', 'numerocheque']);
            foreach ($filas as $fila) {
                $nro = trim((string) $fila->numerocheque);
                if ($nro === '' || $nro === '0') {
                    continue;
                }
                foreach ($asientosPorCaja[(int) $fila->caja_movimiento_id] ?? [] as $asientoId) {
                    $numeros[$asientoId][$nro] = true;
                }
            }
        }

        $salida = [];
        foreach ($numeros as $asientoId => $set) {
            $lista = array_keys($set);
            sort($lista, SORT_NATURAL);
            $salida[$asientoId] = implode(', ', $lista);
        }

        return $salida;
    }

    /**
     * @param  array<int, array<int, true>>  $ocIdsPorAsiento
     * @return array<int, array{nro_oc: string, ordencompra_id: int, capex_codigo: string, capex_id: int}>
     */
    private function presentarOrdenes(array $ocIdsPorAsiento): array
    {
        $ocIds = [];
        foreach ($ocIdsPorAsiento as $set) {
            foreach (array_keys($set) as $ocId) {
                $ocIds[$ocId] = true;
            }
        }
        if ($ocIds === []) {
            return [];
        }

        $numeroPorId = [];
        foreach (array_chunk(array_keys($ocIds), 800) as $lote) {
            $filas = DB::table('ordencompra')
                ->whereIn('id', $lote)
                ->get(['id', 'numeroordencompra']);
            foreach ($filas as $fila) {
                $nro = (int) $fila->numeroordencompra;
                if ($nro > 0) {
                    $numeroPorId[(int) $fila->id] = $nro;
                }
            }
        }

        $capexPorOc = $this->capexPorOrdencompra(array_keys($numeroPorId));
        $salida = [];
        foreach ($ocIdsPorAsiento as $asientoId => $set) {
            $numeros = [];
            $ids = [];
            $codigos = [];
            $capexIds = [];
            foreach (array_keys($set) as $ocId) {
                $nro = $numeroPorId[$ocId] ?? 0;
                if ($nro <= 0) {
                    continue;
                }
                $numeros[$nro] = $nro;
                $ids[$ocId] = $ocId;
                foreach ($capexPorOc[$ocId] ?? [] as $capex) {
                    $codigos[$capex['codigo']] = $capex['codigo'];
                    $capexIds[$capex['id']] = $capex['id'];
                }
            }
            if ($numeros === []) {
                continue;
            }
            $lista = array_values($numeros);
            sort($lista, SORT_NUMERIC);
            $codigosLista = array_values($codigos);
            sort($codigosLista, SORT_NATURAL);

            $salida[$asientoId] = [
                'nro_oc' => implode(', ', $lista),
                'ordencompra_id' => count($ids) === 1 ? (int) array_key_first($ids) : 0,
                'capex_codigo' => implode(', ', $codigosLista),
                'capex_id' => count($capexIds) === 1 ? (int) array_key_first($capexIds) : 0,
            ];
        }

        return $salida;
    }

    /**
     * @param  list<int>  $ordencompraIds
     * @return array<int, array<int, array{id: int, codigo: string}>>
     */
    private function capexPorOrdencompra(array $ordencompraIds): array
    {
        $salida = [];
        foreach (array_chunk($ordencompraIds, 800) as $lote) {
            if ($lote === []) {
                continue;
            }
            $filas = DB::table('ordencompra_articulo as oa')
                ->join('capex as c', 'c.id', '=', 'oa.capex_id')
                ->whereIn('oa.ordencompra_id', $lote)
                ->where('oa.capex_id', '>', 0)
                ->where('c.codigo', '!=', '')
                ->get(['oa.ordencompra_id', 'c.id', 'c.codigo']);
            foreach ($filas as $fila) {
                $codigo = trim((string) $fila->codigo);
                $capexId = (int) $fila->id;
                if ($codigo === '' || $capexId <= 0) {
                    continue;
                }
                $salida[(int) $fila->ordencompra_id][$capexId] = [
                    'id' => $capexId,
                    'codigo' => $codigo,
                ];
            }
        }

        return $salida;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function asientoId(array $fila): int
    {
        $operacion = (int) ($fila['asiento_operacion_id'] ?? 0);

        return $operacion > 0 ? $operacion : (int) ($fila['asiento_id'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function faltaCheque(array $fila): bool
    {
        return trim((string) ($fila['cheque'] ?? '')) === '';
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function faltaOc(array $fila): bool
    {
        return (int) ($fila['nro_oc'] ?? 0) <= 0;
    }
}
