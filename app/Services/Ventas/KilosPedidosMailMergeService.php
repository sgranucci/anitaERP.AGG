<?php

namespace App\Services\Ventas;

use App\Queries\Ventas\PedidoQueryInterface;
use App\Support\Ventas\KilosPedidosAnitaBridgeReader;

/**
 * Arma kilos pedidos (modo ABRE / ítem) mergeando ERP + Anita sin duplicar,
 * y produce el control de diferencias entre ambas fuentes.
 */
class KilosPedidosMailMergeService
{
    private const TOLERANCIA = 0.05;

    public function __construct(
        private readonly PedidoQueryInterface $pedidoQuery,
        private readonly KilosPedidosAnitaBridgeReader $anitaReader,
        private readonly KiloPedidoReporteService $kiloPedidoReporteService,
    ) {
    }

    /**
     * @param  array{fecha_desde: string, fecha_hasta: string, reparto_desde?: string, reparto_hasta?: string, estado?: string}  $filtros
     * @return array{
     *     filtros: array<string, mixed>,
     *     filas_erp: list<array<string, mixed>>,
     *     filas_anita: list<array<string, mixed>>,
     *     filas_merge: list<array<string, mixed>>,
     *     diffs: list<array<string, mixed>>,
     *     totales_erp: array{pieza: float, kilo: float, caja: float, pesada: float},
     *     totales_anita: array{pieza: float, kilo: float, caja: float, pesada: float},
     *     totales_merge: array{pieza: float, kilo: float, caja: float, pesada: float},
     *     cuadran: bool,
     *     anita_ok: bool,
     *     anita_error: ?string
     * }
     */
    public function generar(array $filtros): array
    {
        $filtros = array_merge([
            'tipolistado' => 'ABRE',
            'estado' => 'TODO',
            'reparto_desde' => '',
            'reparto_hasta' => '',
        ], $filtros);

        $mapErp = $this->mapearErp($filtros);
        $anitaOk = true;
        $anitaError = null;
        $mapAnita = [];
        try {
            $mapAnita = $this->mapearAnita($filtros);
        } catch (\Throwable $e) {
            $anitaOk = false;
            $anitaError = $e->getMessage();
        }

        $claves = array_values(array_unique(array_merge(array_keys($mapErp), array_keys($mapAnita))));
        usort($claves, static function (string $a, string $b): int {
            [$ra, $sa] = explode('|', $a, 2);
            [$rb, $sb] = explode('|', $b, 2);
            $cmp = ((int) $ra) <=> ((int) $rb);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp($sa, $sb);
        });

        $filasErp = [];
        $filasAnita = [];
        $filasMerge = [];
        $diffs = [];
        $totErp = $this->totalesVacios();
        $totAnita = $this->totalesVacios();
        $totMerge = $this->totalesVacios();

        foreach ($claves as $clave) {
            $erp = $mapErp[$clave] ?? null;
            $anita = $mapAnita[$clave] ?? null;

            if ($erp !== null) {
                $filasErp[] = $erp;
                $this->acumular($totErp, $erp);
            }
            if ($anita !== null) {
                $filasAnita[] = $anita;
                $this->acumular($totAnita, $anita);
            }

            $merge = $this->mergeFila($erp, $anita);
            $filasMerge[] = $merge;
            $this->acumular($totMerge, $merge);

            $diff = $this->diffFila($clave, $erp, $anita);
            if ($diff !== null) {
                $diffs[] = $diff;
            }
        }

        $filasMergeAplanadas = $this->aplanarPorReparto($filasMerge);
        $filasErpAplanadas = $this->aplanarPorReparto($filasErp);
        $filasAnitaAplanadas = $this->aplanarPorReparto($filasAnita);

        $cuadran = $diffs === []
            && $this->totalesCasiIguales($totErp, $totAnita);

        return [
            'filtros' => $filtros,
            'filas_erp' => $filasErpAplanadas,
            'filas_anita' => $filasAnitaAplanadas,
            'filas_merge' => $filasMergeAplanadas,
            'diffs' => $diffs,
            'totales_erp' => $totErp,
            'totales_anita' => $totAnita,
            'totales_merge' => $totMerge,
            'cuadran' => $cuadran && $anitaOk,
            'anita_ok' => $anitaOk,
            'anita_error' => $anitaError,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, array<string, mixed>>
     */
    private function mapearErp(array $filtros): array
    {
        $datos = $this->pedidoQuery->findPorKiloPedidoFiltros($filtros);
        $map = [];
        foreach ($datos as $row) {
            $reparto = (string) (int) ($row->codigotransporte ?? 0);
            $sku = $this->normalizarSku((string) ($row->sku ?? ''));
            if ($sku === '') {
                continue;
            }
            $clave = $this->clave($reparto, $sku);
            if (isset($map[$clave])) {
                $map[$clave]['total_pieza'] += (float) ($row->total_pieza ?? 0);
                $map[$clave]['total_kilo'] += (float) ($row->total_kilo ?? 0);
                $map[$clave]['total_caja'] += (float) ($row->total_caja ?? 0);
                $map[$clave]['total_pesada'] += (float) ($row->total_pesada ?? 0);
                continue;
            }
            $map[$clave] = [
                'fuente' => 'ERP',
                'codigotransporte' => $reparto,
                'nombretransporte' => (string) ($row->nombretransporte ?? ''),
                'transporte_id' => (int) ($row->transporte_id ?? 0),
                'sku' => $sku,
                'descripcion' => (string) ($row->descripcion ?? ''),
                'articulo_id' => (int) ($row->articulo_id ?? 0),
                'total_pieza' => (float) ($row->total_pieza ?? 0),
                'total_kilo' => (float) ($row->total_kilo ?? 0),
                'total_caja' => (float) ($row->total_caja ?? 0),
                'total_pesada' => (float) ($row->total_pesada ?? 0),
            ];
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, array<string, mixed>>
     */
    private function mapearAnita(array $filtros): array
    {
        $lineas = $this->anitaReader->listarLineas($filtros);
        $map = [];
        foreach ($lineas as $row) {
            $repartoRaw = (int) ($row->penv_reparto ?? 0);
            if ($repartoRaw <= 0) {
                $repartoRaw = (int) ($row->penm_expreso ?? 0);
            }
            $reparto = (string) $repartoRaw;
            $sku = $this->normalizarSku((string) ($row->penv_articulo ?? ''));
            if ($sku === '') {
                continue;
            }
            $clave = $this->clave($reparto, $sku);
            $pieza = $this->floatAnita($row->penv_pieza ?? 0);
            $kilo = $this->floatAnita($row->penv_cantidad ?? 0); // teórico (l-pedkilo TEORICO)
            $pesada = $this->floatAnita($row->penv_kilos_reales ?? 0);
            $caja = 0.0;
            if (! isset($map[$clave])) {
                $map[$clave] = [
                    'fuente' => 'ANITA',
                    'codigotransporte' => $reparto,
                    'nombretransporte' => '',
                    'transporte_id' => 0,
                    'sku' => $sku,
                    'descripcion' => trim((string) ($row->stkm_desc ?? '')),
                    'articulo_id' => 0,
                    'total_pieza' => 0.0,
                    'total_kilo' => 0.0,
                    'total_caja' => 0.0,
                    'total_pesada' => 0.0,
                ];
            }
            $map[$clave]['total_pieza'] += $pieza;
            $map[$clave]['total_kilo'] += $kilo;
            $map[$clave]['total_pesada'] += $pesada;
            $map[$clave]['total_caja'] += $caja;
            if ($map[$clave]['descripcion'] === '' && trim((string) ($row->stkm_desc ?? '')) !== '') {
                $map[$clave]['descripcion'] = trim((string) $row->stkm_desc);
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>|null  $erp
     * @param  array<string, mixed>|null  $anita
     * @return array<string, mixed>
     */
    private function mergeFila(?array $erp, ?array $anita): array
    {
        if ($erp !== null && $anita !== null) {
            $fila = $erp;
            $fila['fuente'] = 'AMBOS';
            if (($fila['descripcion'] ?? '') === '' && ($anita['descripcion'] ?? '') !== '') {
                $fila['descripcion'] = $anita['descripcion'];
            }

            return $fila;
        }
        if ($erp !== null) {
            $fila = $erp;
            $fila['fuente'] = 'SOLO_ERP';

            return $fila;
        }

        $fila = $anita ?? [];
        $fila['fuente'] = 'SOLO_ANITA';

        return $fila;
    }

    /**
     * @param  array<string, mixed>|null  $erp
     * @param  array<string, mixed>|null  $anita
     * @return array<string, mixed>|null
     */
    private function diffFila(string $clave, ?array $erp, ?array $anita): ?array
    {
        if ($erp === null && $anita === null) {
            return null;
        }
        if ($erp === null) {
            return [
                'clave' => $clave,
                'motivo' => 'solo_anita',
                'codigotransporte' => $anita['codigotransporte'] ?? '',
                'sku' => $anita['sku'] ?? '',
                'descripcion' => $anita['descripcion'] ?? '',
                'erp_kilo' => 0.0,
                'anita_kilo' => (float) ($anita['total_kilo'] ?? 0),
                'delta_kilo' => -1 * (float) ($anita['total_kilo'] ?? 0),
                'erp_pieza' => 0.0,
                'anita_pieza' => (float) ($anita['total_pieza'] ?? 0),
            ];
        }
        if ($anita === null) {
            return [
                'clave' => $clave,
                'motivo' => 'solo_erp',
                'codigotransporte' => $erp['codigotransporte'] ?? '',
                'sku' => $erp['sku'] ?? '',
                'descripcion' => $erp['descripcion'] ?? '',
                'erp_kilo' => (float) ($erp['total_kilo'] ?? 0),
                'anita_kilo' => 0.0,
                'delta_kilo' => (float) ($erp['total_kilo'] ?? 0),
                'erp_pieza' => (float) ($erp['total_pieza'] ?? 0),
                'anita_pieza' => 0.0,
            ];
        }

        $dKilo = (float) $erp['total_kilo'] - (float) $anita['total_kilo'];
        $dPieza = (float) $erp['total_pieza'] - (float) $anita['total_pieza'];
        if (abs($dKilo) <= self::TOLERANCIA && abs($dPieza) <= self::TOLERANCIA) {
            return null;
        }

        return [
            'clave' => $clave,
            'motivo' => 'importe_distinto',
            'codigotransporte' => $erp['codigotransporte'] ?? '',
            'sku' => $erp['sku'] ?? '',
            'descripcion' => $erp['descripcion'] ?? '',
            'erp_kilo' => (float) $erp['total_kilo'],
            'anita_kilo' => (float) $anita['total_kilo'],
            'delta_kilo' => $dKilo,
            'erp_pieza' => (float) $erp['total_pieza'],
            'anita_pieza' => (float) $anita['total_pieza'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function aplanarPorReparto(array $filas): array
    {
        if ($filas === []) {
            return [];
        }

        usort($filas, static function (array $a, array $b): int {
            $cmp = ((int) ($a['codigotransporte'] ?? 0)) <=> ((int) ($b['codigotransporte'] ?? 0));
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) ($a['sku'] ?? ''), (string) ($b['sku'] ?? ''));
        });

        $coleccion = collect($filas)->map(static function (array $f) {
            return (object) [
                'codigotransporte' => $f['codigotransporte'] ?? '',
                'nombretransporte' => $f['nombretransporte'] ?? '',
                'transporte_id' => $f['transporte_id'] ?? 0,
                'sku' => $f['sku'] ?? '',
                'descripcion' => $f['descripcion'] ?? '',
                'articulo_id' => $f['articulo_id'] ?? 0,
                'codigocategoria' => '',
                'fechaentrega' => '',
                'total_pieza' => $f['total_pieza'] ?? 0,
                'total_kilo' => $f['total_kilo'] ?? 0,
                'total_caja' => $f['total_caja'] ?? 0,
                'total_pesada' => $f['total_pesada'] ?? 0,
                'fuente' => $f['fuente'] ?? '',
            ];
        });

        $aplanadas = $this->kiloPedidoReporteService->aplanarFilas($coleccion, ['tipolistado' => 'ABRE']);
        // Propagar fuente en detalle.
        $porClave = [];
        foreach ($filas as $f) {
            $porClave[$this->clave((string) ($f['codigotransporte'] ?? ''), (string) ($f['sku'] ?? ''))] = $f['fuente'] ?? '';
        }
        foreach ($aplanadas as &$fila) {
            if (($fila['tipo_fila'] ?? '') !== 'detalle') {
                continue;
            }
            $k = $this->clave((string) ($fila['codigotransporte'] ?? ''), (string) ($fila['sku'] ?? ''));
            $fila['fuente'] = $porClave[$k] ?? '';
        }
        unset($fila);

        return $aplanadas;
    }

    private function clave(string $reparto, string $sku): string
    {
        return ((string) (int) $reparto).'|'.$this->normalizarSku($sku);
    }

    /** Misma normalización que PedidoImportarDesdeAnitaService::resolverArticulo. */
    private function normalizarSku(string $skuRaw): string
    {
        $sku = ltrim(trim($skuRaw), '0');

        return $sku !== '' ? $sku : trim($skuRaw);
    }

    /**
     * @return array{pieza: float, kilo: float, caja: float, pesada: float}
     */
    private function totalesVacios(): array
    {
        return ['pieza' => 0.0, 'kilo' => 0.0, 'caja' => 0.0, 'pesada' => 0.0];
    }

    /**
     * @param  array{pieza: float, kilo: float, caja: float, pesada: float}  $tot
     * @param  array<string, mixed>  $fila
     */
    private function acumular(array &$tot, array $fila): void
    {
        $tot['pieza'] += (float) ($fila['total_pieza'] ?? 0);
        $tot['kilo'] += (float) ($fila['total_kilo'] ?? 0);
        $tot['caja'] += (float) ($fila['total_caja'] ?? 0);
        $tot['pesada'] += (float) ($fila['total_pesada'] ?? 0);
    }

    /**
     * @param  array{pieza: float, kilo: float, caja: float, pesada: float}  $a
     * @param  array{pieza: float, kilo: float, caja: float, pesada: float}  $b
     */
    private function totalesCasiIguales(array $a, array $b): bool
    {
        return abs($a['kilo'] - $b['kilo']) <= self::TOLERANCIA
            && abs($a['pieza'] - $b['pieza']) <= self::TOLERANCIA;
    }

    private function floatAnita(mixed $valor): float
    {
        if (is_numeric($valor)) {
            return (float) $valor;
        }
        $s = str_replace(',', '.', trim((string) $valor));

        return is_numeric($s) ? (float) $s : 0.0;
    }
}
