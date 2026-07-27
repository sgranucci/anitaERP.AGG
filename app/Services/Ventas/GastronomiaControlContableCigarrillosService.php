<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Queries\Ventas\GastronomiaControlContableCigarrillosQuery;
use App\Queries\Ventas\GastronomiaInsumosTipoarticuloReporteQuery;
use App\Services\Configuracion\ImpuestoService;
use App\Support\Contable\Anita\AnitaMayorAnaliticoSupport;
use App\Support\Contable\Anita\AnitaMovimientoDetalleModuloSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoConfigSupport;
use App\Support\Ventas\GastronomiaInsumosTipoarticuloReporteFiltros;
use Illuminate\Support\Facades\DB;

/**
 * Control Contaduría de cigarrillos: matriz por SKU/día (precio factura + II por vigencia)
 * y conciliación Sumatoria(II+NETO) vs mayor Anita cuenta tabaco (414020001).
 */
class GastronomiaControlContableCigarrillosService
{
    private const TASA_IVA = 21.0;

    private const TOLERANCIA_DIF = 0.10;

    public function __construct(
        private readonly GastronomiaInsumosTipoarticuloReporteQuery $insumosQuery,
        private readonly GastronomiaControlContableCigarrillosQuery $preciosQuery,
        private readonly ImpuestoService $impuestoService,
        private readonly AnitaMayorAnaliticoSupport $mayorSupport,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   columnas_dias: list<array{ymd:string,label:string}>,
     *   productos: list<array<string,mixed>>,
     *   sumatoria_por_dia: array<string,float>,
     *   mayor_por_dia: array<string,float>,
     *   diferencia_por_dia: array<string,float>,
     *   cuenta_tabaco_codigo: string,
     *   tolerancia: float,
     *   hay_diferencias: bool
     * }
     */
    public function generar(array $filtros): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $tipoarticuloId = (int) ($filtros['tipoarticulo_id'] ?? 0);
        [$desde, $hasta] = GastronomiaInsumosTipoarticuloReporteFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );

        $columnasDias = GastronomiaInsumosTipoarticuloReporteFiltros::columnasDias($desde, $hasta);
        $ventas = $this->insumosQuery->cantidadesPorArticuloDia($filtros);
        $preciosMenu = $this->indexPrecioPorArticuloDia(
            $this->preciosQuery->preciosMenuHistoricoPorArticuloDia($filtros)
        );
        $preciosPropios = $this->indexPrecioPorArticuloDia(
            $this->preciosQuery->preciosLineaPropiaPorArticuloDia($filtros)
        );

        $listaprecioIiId = $empresaId > 0
            ? $this->impuestoService->listaprecioImpuestoInternoPorEmpresa($empresaId)
            : null;

        $cantidades = [];
        $metaArticulo = [];
        foreach ($ventas as $row) {
            $articuloId = (int) $row->articulo_id;
            $dia = (string) $row->dia;
            $cantidades[$articuloId][$dia] = round((float) ($row->cantidad ?? 0), 4);
            $metaArticulo[$articuloId] = [
                'articulo_id' => $articuloId,
                'sku' => (string) $row->sku,
                'descripcion' => (string) $row->descripcion,
            ];
        }

        $productos = [];
        $sumatoriaPorDia = [];
        foreach ($columnasDias as $col) {
            $sumatoriaPorDia[$col['ymd']] = 0.0;
        }

        $articuloIds = array_keys($metaArticulo);
        sort($articuloIds, SORT_NUMERIC);

        foreach ($articuloIds as $articuloId) {
            $meta = $metaArticulo[$articuloId];
            $porDia = [];
            $totalCantidad = 0.0;

            foreach ($columnasDias as $col) {
                $ymd = $col['ymd'];
                $cantidad = (float) ($cantidades[$articuloId][$ymd] ?? 0);
                $totalCantidad += $cantidad;

                $pcio = (float) ($preciosMenu[$articuloId][$ymd]
                    ?? $preciosPropios[$articuloId][$ymd]
                    ?? 0);
                $pcio = round($pcio, 2);

                $coef = 0.0;
                if ($listaprecioIiId !== null && $listaprecioIiId > 0 && $pcio > 0) {
                    $coef = $this->impuestoService->coeficienteImpuestoInterno(
                        $articuloId,
                        $listaprecioIiId,
                        $ymd,
                    );
                }

                $iiUnit = round($pcio * $coef, 2);
                $gravadoUnit = $pcio > 0
                    ? round(($pcio - $iiUnit) / (1.0 + self::TASA_IVA / 100.0), 6)
                    : 0.0;

                $ventaTotal = round($cantidad * $pcio, 2);
                $impInterno = round($cantidad * $iiUnit, 2);
                $neto = round($cantidad * $gravadoUnit, 2);
                $iva = round($neto * (self::TASA_IVA / 100.0), 2);
                $redondeo = round(($impInterno + $neto + $iva) - $ventaTotal, 2);

                $porDia[$ymd] = [
                    'pcio_vta' => $pcio,
                    'imp_interno_unit' => $iiUnit,
                    'cantidad' => $cantidad,
                    'venta_total' => $ventaTotal,
                    'imp_interno' => $impInterno,
                    'gravado' => round($gravadoUnit, 6),
                    'neto' => $neto,
                    'iva' => $iva,
                    'redondeo' => $redondeo,
                    'coeficiente' => $coef,
                ];

                $sumatoriaPorDia[$ymd] = round(
                    $sumatoriaPorDia[$ymd] + $impInterno + $neto,
                    2
                );
            }

            if (abs($totalCantidad) < 0.0001) {
                continue;
            }

            $productos[] = array_merge($meta, [
                'por_dia' => $porDia,
                'total_cantidad' => round($totalCantidad, 4),
            ]);
        }

        $cuentaTabacoCodigo = $this->resolverCodigoCuentaTabaco($empresaId);
        $mayorPorDia = $this->mayorTabacoPorDia($empresaId, $desde, $hasta, $cuentaTabacoCodigo);

        $diferenciaPorDia = [];
        $hayDiferencias = false;
        foreach ($columnasDias as $col) {
            $ymd = $col['ymd'];
            $sum = (float) ($sumatoriaPorDia[$ymd] ?? 0);
            $mayor = (float) ($mayorPorDia[$ymd] ?? 0);
            $dif = round($sum - $mayor, 2);
            $diferenciaPorDia[$ymd] = $dif;
            if (abs($dif) > self::TOLERANCIA_DIF) {
                $hayDiferencias = true;
            }
        }

        return [
            'columnas_dias' => $columnasDias,
            'productos' => $productos,
            'sumatoria_por_dia' => $sumatoriaPorDia,
            'mayor_por_dia' => $mayorPorDia,
            'diferencia_por_dia' => $diferenciaPorDia,
            'cuenta_tabaco_codigo' => $cuentaTabacoCodigo,
            'tolerancia' => self::TOLERANCIA_DIF,
            'hay_diferencias' => $hayDiferencias,
            'tipoarticulo_id' => $tipoarticuloId,
            'empresa_id' => $empresaId,
        ];
    }

    /**
     * @param  Collection<int, object{articulo_id:int,dia:string,precio:float}>  $rows
     * @return array<int, array<string, float>>
     */
    private function indexPrecioPorArticuloDia($rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $articuloId = (int) $row->articulo_id;
            $dia = (string) $row->dia;
            $out[$articuloId][$dia] = round((float) ($row->precio ?? 0), 2);
        }

        return $out;
    }

    private function resolverCodigoCuentaTabaco(int $empresaId): string
    {
        if ($empresaId <= 0) {
            return '414020001';
        }

        $cfg = CierreJornadaProcesoConfigSupport::paraEmpresa($empresaId);
        $cuentaId = (int) ($cfg['cuenta_ventas_kiosco_id'] ?? 0);
        if ($cuentaId <= 0) {
            return '414020001';
        }

        $codigo = DB::table('cuentacontable')->where('id', $cuentaId)->value('codigo');

        return $codigo !== null && trim((string) $codigo) !== ''
            ? trim((string) $codigo)
            : '414020001';
    }

    /**
     * @return array<string, float>
     */
    private function mayorTabacoPorDia(
        int $empresaId,
        string $desde,
        string $hasta,
        string $cuentaCodigo,
    ): array {
        if ($empresaId <= 0 || $desde === '' || $hasta === '') {
            return [];
        }

        $codigoCuenta = (int) preg_replace('/\D+/', '', $cuentaCodigo);
        if ($codigoCuenta <= 0) {
            return [];
        }

        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        $fechaDesdeYmd = (int) str_replace('-', '', $desde);
        $fechaHastaYmd = (int) str_replace('-', '', $hasta);

        try {
            $movimientos = $this->mayorSupport->listarMovimientosPeriodo(
                $empresaAnita,
                $fechaDesdeYmd,
                $fechaHastaYmd,
                [$codigoCuenta],
            );
        } catch (\Throwable) {
            return [];
        }

        $porFecha = [];
        foreach ($movimientos as $mov) {
            if (! AnitaMovimientoDetalleModuloSupport::esGastronomia($mov)) {
                continue;
            }
            $fecha = (string) ($mov['fecha'] ?? '');
            if ($fecha === '') {
                continue;
            }
            $neto = (float) ($mov['neto_haber'] ?? 0);
            if ($neto == 0.0) {
                $neto = round((float) ($mov['haber'] ?? 0) - (float) ($mov['debe'] ?? 0), 2);
            }
            $porFecha[$fecha] = round(($porFecha[$fecha] ?? 0) + $neto, 2);
        }

        return $porFecha;
    }
}
