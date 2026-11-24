<?php

namespace App\Services\Ventas;

use App\Queries\Ventas\PedidoQueryInterface;
use App\Support\Ventas\KilosPedidosAnitaBridgeReader;

/**
 * Arma kilos pedidos en modo batch de l-pedkilo -b (REPARTO_AGRUP + TOTAL):
 * total por artículo en el rango de repartos, sin abrir por reparto.
 * Merge ERP + Anita sin duplicar y control de diferencias.
 * Salida aplanada con el mismo formato visual que l-pedkilo (agrupación + repartos).
 */
class KilosPedidosMailMergeService
{
    private const TOLERANCIA = 0.05;

    public function __construct(
        private readonly PedidoQueryInterface $pedidoQuery,
        private readonly KilosPedidosAnitaBridgeReader $anitaReader,
    ) {
    }

    /**
     * @param  array{fecha_desde: string, fecha_hasta: string, reparto_desde?: string, reparto_hasta?: string, estado?: string}  $filtros
     * @return array{
     *     filtros: array<string, mixed>,
     *     filas_erp: list<array<string, mixed>>,
     *     filas_anita: list<array<string, mixed>>,
     *     filas_merge: list<array<string, mixed>>,
     *     repartos: list<array{codigo: string, nombre: string, total_kilo: float}>,
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
            // Modo batch l-pedkilo -b: total por artículo (no ABRE por reparto).
            'tipolistado' => 'ARTICULO',
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
            return strnatcasecmp($a, $b);
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

        $filasMergeAplanadas = $this->aplanarEstiloAnita($filasMerge);
        $filasErpAplanadas = $this->aplanarEstiloAnita($filasErp);
        $filasAnitaAplanadas = $this->aplanarEstiloAnita($filasAnita);

        $cuadran = $diffs === []
            && $this->totalesCasiIguales($totErp, $totAnita);

        return [
            'filtros' => $filtros,
            'filas_erp' => $filasErpAplanadas,
            'filas_anita' => $filasAnitaAplanadas,
            'filas_merge' => $filasMergeAplanadas,
            'repartos' => $this->listarRepartos($filtros),
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
        // Misma totalización por artículo que el batch REPARTO_AGRUP+TOTAL (sin abrir reparto).
        $datos = $this->pedidoQuery->findPorKiloCategoriaFiltros($filtros);
        $map = [];
        foreach ($datos as $row) {
            $sku = $this->normalizarSku((string) ($row->sku ?? ''));
            if ($sku === '') {
                continue;
            }
            $clave = $this->clave($sku);
            if (isset($map[$clave])) {
                $map[$clave]['total_pieza'] += (float) ($row->total_pieza ?? 0);
                $map[$clave]['total_kilo'] += (float) ($row->total_kilo ?? 0);
                $map[$clave]['total_caja'] += (float) ($row->total_caja ?? 0);
                continue;
            }
            $map[$clave] = [
                'fuente' => 'ERP',
                'sku' => $sku,
                'descripcion' => (string) ($row->descripcion ?? ''),
                'articulo_id' => (int) ($row->articulo_id ?? 0),
                'categoria_id' => (int) ($row->categoria_id ?? 0),
                'codigocategoria' => (string) ($row->codigocategoria ?? ''),
                'nombrecategoria' => (string) ($row->nombrecategoria ?? ''),
                'total_pieza' => (float) ($row->total_pieza ?? 0),
                'total_kilo' => (float) ($row->total_kilo ?? 0),
                'total_caja' => (float) ($row->total_caja ?? 0),
                'total_pesada' => 0.0,
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
            $sku = $this->normalizarSku((string) ($row->penv_articulo ?? ''));
            if ($sku === '') {
                continue;
            }
            $clave = $this->clave($sku);
            $pieza = $this->floatAnita($row->penv_pieza ?? 0);
            $kilo = $this->floatAnita($row->penv_cantidad ?? 0); // teórico (l-pedkilo TEORICO)
            $pesada = $this->floatAnita($row->penv_kilos_reales ?? 0);
            $ux = $this->floatAnita($row->stkm_unidad_xenv ?? 0);
            $caja = $ux > 0.00001 ? ($pieza / $ux) : 0.0;
            $agrup = trim((string) ($row->stkm_agrupacion ?? ''));
            if (! isset($map[$clave])) {
                $map[$clave] = [
                    'fuente' => 'ANITA',
                    'sku' => $sku,
                    'descripcion' => trim((string) ($row->stkm_desc ?? '')),
                    'articulo_id' => 0,
                    'categoria_id' => 0,
                    'codigocategoria' => $this->codigoAgrupacionAnita($agrup),
                    'nombrecategoria' => '',
                    'total_pieza' => 0.0,
                    'total_kilo' => 0.0,
                    'total_caja' => 0.0,
                    'total_pesada' => 0.0,
                ];
            }
            $map[$clave]['total_pieza'] += $pieza;
            $map[$clave]['total_kilo'] += $kilo;
            $map[$clave]['total_caja'] += $caja;
            $map[$clave]['total_pesada'] += $pesada;
            if ($map[$clave]['descripcion'] === '' && trim((string) ($row->stkm_desc ?? '')) !== '') {
                $map[$clave]['descripcion'] = trim((string) $row->stkm_desc);
            }
            if (($map[$clave]['codigocategoria'] ?? '') === '' && $agrup !== '') {
                $map[$clave]['codigocategoria'] = $this->codigoAgrupacionAnita($agrup);
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array{codigo: string, nombre: string, total_kilo: float}>
     */
    private function listarRepartos(array $filtros): array
    {
        $rows = $this->pedidoQuery->findKilosPorRepartoFiltros($filtros);
        $out = [];
        foreach ($rows as $row) {
            $kilo = (float) ($row->total_kilo ?? 0);
            if (abs($kilo) < 0.00001) {
                continue;
            }
            $out[] = [
                'codigo' => (string) ($row->codigotransporte ?? ''),
                'nombre' => (string) ($row->nombretransporte ?? ''),
                'total_kilo' => $kilo,
            ];
        }

        return $out;
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
            if (($fila['codigocategoria'] ?? '') === '' && ($anita['codigocategoria'] ?? '') !== '') {
                $fila['codigocategoria'] = $anita['codigocategoria'];
                $fila['nombrecategoria'] = $anita['nombrecategoria'] ?? '';
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
     * Formato l-pedkilo REPARTO_AGRUP+TOTAL: encabezado de agrupación, detalle Art/Desc/Piezas/Kilos/Cajas,
     * sin subtotal por grupo; al final Total general.
     *
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function aplanarEstiloAnita(array $filas): array
    {
        if ($filas === []) {
            return [];
        }

        usort($filas, static function (array $a, array $b): int {
            $cmp = strnatcasecmp(
                (string) ($a['codigocategoria'] ?? ''),
                (string) ($b['codigocategoria'] ?? '')
            );
            if ($cmp !== 0) {
                return $cmp;
            }

            return strnatcasecmp((string) ($a['sku'] ?? ''), (string) ($b['sku'] ?? ''));
        });

        $aplanadas = [];
        $agrupActual = null;
        $totales = $this->totalesVacios();

        foreach ($filas as $f) {
            $codigo = (string) ($f['codigocategoria'] ?? '');
            $nombre = (string) ($f['nombrecategoria'] ?? '');
            if ($codigo !== $agrupActual) {
                $agrupActual = $codigo;
                $aplanadas[] = [
                    'tipo_fila' => 'encabezado_agrupacion',
                    'codigocategoria' => $this->codigoAgrupacionDisplay($codigo),
                    'nombrecategoria' => $nombre,
                ];
            }

            $pieza = (float) ($f['total_pieza'] ?? 0);
            $kilo = (float) ($f['total_kilo'] ?? 0);
            $caja = (float) ($f['total_caja'] ?? 0);
            $totales['pieza'] += $pieza;
            $totales['kilo'] += $kilo;
            $totales['caja'] += $caja;

            $aplanadas[] = [
                'tipo_fila' => 'detalle',
                'sku' => $this->skuDisplay((string) ($f['sku'] ?? '')),
                'descripcion' => (string) ($f['descripcion'] ?? ''),
                'codigocategoria' => $this->codigoAgrupacionDisplay($codigo),
                'nombrecategoria' => $nombre,
                'articulo_id' => (int) ($f['articulo_id'] ?? 0),
                'total_pieza' => $pieza,
                'total_kilo' => $kilo,
                'total_caja' => $caja,
                'fuente' => (string) ($f['fuente'] ?? ''),
            ];
        }

        $aplanadas[] = [
            'tipo_fila' => 'total_final',
            'total_pieza' => $totales['pieza'],
            'total_kilo' => $totales['kilo'],
            'total_caja' => $totales['caja'],
        ];

        return $aplanadas;
    }

    /** Anita muestra stkm_agrupacion en 4 chars (00G0); ERP suele traer G0. */
    private function codigoAgrupacionDisplay(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return '';
        }
        if (strlen($codigo) >= 4) {
            return substr($codigo, 0, 4);
        }

        return str_pad($codigo, 4, '0', STR_PAD_LEFT);
    }

    /** Anita bridge trae stkm_agrupacion (00G0); ERP compara por G0. */
    private function codigoAgrupacionAnita(string $agrup): string
    {
        $agrup = trim($agrup);
        if ($agrup === '') {
            return '';
        }
        // Misma normalización que Articulo::… ltrim zeros sobre stkm_agrupacion.
        $sinCeros = ltrim($agrup, '0');

        return $sinCeros !== '' ? $sinCeros : $agrup;
    }

    /** l-pedkilo imprime stkm_articulo+9 (últimos 4). */
    private function skuDisplay(string $sku): string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return '';
        }
        if (strlen($sku) > 4) {
            return substr($sku, -4);
        }

        return str_pad($sku, 4, '0', STR_PAD_LEFT);
    }

    private function clave(string $sku): string
    {
        return $this->normalizarSku($sku);
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
