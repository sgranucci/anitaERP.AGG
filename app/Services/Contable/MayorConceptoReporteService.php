<?php

namespace App\Services\Contable;

use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Contable\MayorConcepto\MayorConceptoAuditoriaSupport;
use App\Support\Contable\MayorConcepto\MayorConceptoMonedaConverter;
use App\Support\Contable\MayorConcepto\MayorConceptoPeriodoProcesador;
use App\Support\Contable\MayorConcepto\MayorConceptoRuntimeSupport;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as PaginatorImpl;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MayorConceptoReporteService
{
    public function __construct(
        private readonly MayorConceptoPeriodoProcesador $procesador,
        private readonly MayorConceptoMonedaConverter $monedaConverter,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly MayorConceptoAuditoriaSupport $auditoriaSupport,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generarDesdeFiltros(array $filtros): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $monedaId = (int) ($filtros['moneda_id'] ?? 1);
        $usarMes = ($filtros['modo_periodo'] ?? 'mes') === 'mes';
        $soloOrigen = (bool) ($filtros['solo_moneda_origen'] ?? false);

        $fechaDesde = null;
        $fechaHasta = null;
        $mes = null;
        $anio = null;

        if ($usarMes) {
            $mes = (int) ($filtros['mes'] ?? 0);
            $anio = (int) ($filtros['anio'] ?? 0);
        } else {
            [$fechaDesde, $fechaHasta] = MayorConceptoListadoFiltros::normalizarRangoFechas(
                (string) ($filtros['fecha_desde'] ?? ''),
                (string) ($filtros['fecha_hasta'] ?? ''),
            );
        }

        return $this->generar(
            $empresaId,
            $fechaDesde !== '' ? $fechaDesde : null,
            $fechaHasta !== '' ? $fechaHasta : null,
            $mes,
            $anio,
            $usarMes,
            $monedaId,
            $soloOrigen,
        );
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return list<array{
     *   concepto_id: int,
     *   concepto_nombre: string,
     *   total_debe: float,
     *   total_haber: float,
     *   cuentas: list<array<string, mixed>>
     * }>
     */
    public function resumenAgrupado(array $resultado): array
    {
        $empresaId = (int) ($resultado['parametros']['empresa_id'] ?? 0);
        $codigosCuenta = [];

        foreach ($resultado['secciones'] ?? [] as $seccion) {
            foreach ($seccion['cuentas'] ?? [] as $cuentaBlock) {
                $codigo = (int) ($cuentaBlock['cuenta'] ?? 0);
                if ($codigo > 0) {
                    $codigosCuenta[$codigo] = true;
                }
            }
        }

        $cuentasPorCodigo = $this->lookupCuentasPorCodigo($empresaId, array_keys($codigosCuenta));

        $resumen = [];
        foreach ($resultado['secciones'] ?? [] as $seccion) {
            $conceptoId = (int) ($seccion['concepto_id'] ?? 0);
            $totalDebe = 0.0;
            $totalHaber = 0.0;
            $cuentas = [];

            foreach ($seccion['cuentas'] ?? [] as $cuentaBlock) {
                $codigo = (int) ($cuentaBlock['cuenta'] ?? 0);
                $totalDebe += (float) ($cuentaBlock['total_debe'] ?? 0);
                $totalHaber += (float) ($cuentaBlock['total_haber'] ?? 0);
                $cuentas[] = [
                    'cuenta' => $codigo,
                    'cuenta_codigo' => $cuentaBlock['cuenta_codigo'] ?? '',
                    'cuenta_nombre' => $cuentaBlock['cuenta_nombre'] ?? '',
                    'cuentacontable_id' => $codigo > 0 ? (int) ($cuentasPorCodigo[$codigo] ?? 0) : 0,
                    'cantidad_lineas' => count($cuentaBlock['lineas'] ?? []),
                    'total_debe' => (float) ($cuentaBlock['total_debe'] ?? 0),
                    'total_haber' => (float) ($cuentaBlock['total_haber'] ?? 0),
                ];
            }

            $resumen[] = [
                'concepto_id' => $conceptoId,
                'concepto_nombre' => $seccion['concepto_nombre'] ?? '',
                'total_debe' => round($totalDebe, 2),
                'total_haber' => round($totalHaber, 2),
                'cantidad_cuentas' => count($cuentas),
                'cantidad_lineas' => array_sum(array_column($cuentas, 'cantidad_lineas')),
                'cuentas' => $cuentas,
            ];
        }

        return $resumen;
    }

    /**
     * Totales agrupados primero por cuenta de imputación y luego por concepto.
     *
     * @param  array<string, mixed>  $resultado
     * @return list<array<string, mixed>>
     */
    public function resumenAgrupadoPorCuenta(array $resultado): array
    {
        $porConcepto = $this->resumenAgrupado($resultado);
        $porCuenta = [];

        foreach ($porConcepto as $seccion) {
            foreach ($seccion['cuentas'] as $cuenta) {
                $codigo = (int) ($cuenta['cuenta'] ?? 0);
                if (! isset($porCuenta[$codigo])) {
                    $porCuenta[$codigo] = [
                        'cuenta' => $codigo,
                        'cuenta_codigo' => $cuenta['cuenta_codigo'] ?? '',
                        'cuenta_nombre' => $cuenta['cuenta_nombre'] ?? '',
                        'cuentacontable_id' => (int) ($cuenta['cuentacontable_id'] ?? 0),
                        'total_debe' => 0.0,
                        'total_haber' => 0.0,
                        'cantidad_lineas' => 0,
                        'conceptos' => [],
                    ];
                }

                $porCuenta[$codigo]['total_debe'] += (float) ($cuenta['total_debe'] ?? 0);
                $porCuenta[$codigo]['total_haber'] += (float) ($cuenta['total_haber'] ?? 0);
                $porCuenta[$codigo]['cantidad_lineas'] += (int) ($cuenta['cantidad_lineas'] ?? 0);
                $porCuenta[$codigo]['conceptos'][] = [
                    'concepto_id' => (int) ($seccion['concepto_id'] ?? 0),
                    'concepto_nombre' => (string) ($seccion['concepto_nombre'] ?? ''),
                    'total_debe' => (float) ($cuenta['total_debe'] ?? 0),
                    'total_haber' => (float) ($cuenta['total_haber'] ?? 0),
                    'cantidad_lineas' => (int) ($cuenta['cantidad_lineas'] ?? 0),
                ];
            }
        }

        $resumen = array_values($porCuenta);
        foreach ($resumen as $idx => $sec) {
            $resumen[$idx]['total_debe'] = round($sec['total_debe'], 2);
            $resumen[$idx]['total_haber'] = round($sec['total_haber'], 2);
            usort($resumen[$idx]['conceptos'], fn ($a, $b) => $a['concepto_id'] <=> $b['concepto_id']);
        }

        usort($resumen, fn ($a, $b) => $a['cuenta'] <=> $b['cuenta']);

        return $resumen;
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    public function auditarContraMayorPlano(array $resultado): array
    {
        return $this->auditoriaSupport->auditar($resultado);
    }

    /**
     * Cruza contrapartidas imputadas vs plano acotado a operaciones de disponibilidad.
     *
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    public function auditarContrapartidasDesdeDisponibilidad(array $resultado): array
    {
        return $this->auditoriaSupport->auditarMayorPlanoAnalitico($resultado);
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return array{
     *   cuadra: bool,
     *   disponibilidad: array<string, mixed>,
     *   contrapartidas: array<string, mixed>
     * }
     */
    public function armarAuditoriaPanel(array $resultado): array
    {
        $disponibilidad = $this->auditarContraMayorPlano($resultado);
        $contrapartidas = $this->auditarContrapartidasDesdeDisponibilidad($resultado);

        return [
            'cuadra' => (bool) ($disponibilidad['cuadra'] ?? false)
                && (bool) ($contrapartidas['cuadra'] ?? false),
            'disponibilidad' => $disponibilidad,
            'contrapartidas' => $contrapartidas,
        ];
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    public function resumenSegunAgrupacion(array $resultado, array $filtros): array
    {
        if (($filtros['agrupacion_resumen'] ?? 'concepto_cuenta') === 'cuenta_concepto') {
            return $this->resumenAgrupadoPorCuenta($resultado);
        }

        return $this->resumenAgrupado($resultado);
    }

    /**
     * Solo líneas de detalle (para paginación en pantalla).
     *
     * @param  array<string, mixed>  $resultado
     * @return list<array<string, mixed>>
     */
    public function aplanarFilas(array $resultado): array
    {
        return $this->aplanarFilasInterno($resultado, false);
    }

    /**
     * Detalle intercalado con totales por cuenta y concepto (exportaciones).
     *
     * @param  array<string, mixed>  $resultado
     * @return list<array<string, mixed>>
     */
    public function aplanarFilasConTotales(array $resultado): array
    {
        return $this->aplanarFilasInterno($resultado, true);
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return list<array<string, mixed>>
     */
    private function aplanarFilasInterno(array $resultado, bool $conTotales): array
    {
        $empresaId = (int) ($resultado['parametros']['empresa_id'] ?? 0);
        $nombreEmpresa = $this->empresaRepository->find($empresaId)?->nombre ?? '';

        $filas = [];
        foreach ($resultado['secciones'] ?? [] as $seccion) {
            $conceptoId = (int) ($seccion['concepto_id'] ?? 0);
            $conceptoNombre = (string) ($seccion['concepto_nombre'] ?? '');
            $totalConceptoDebe = 0.0;
            $totalConceptoHaber = 0.0;

            foreach ($seccion['cuentas'] ?? [] as $cuentaBlock) {
                foreach ($cuentaBlock['lineas'] ?? [] as $ln) {
                    $filas[] = array_merge($ln, [
                        'tipo_fila' => 'detalle',
                        'empresa_id' => $empresaId,
                        'nombreempresa' => $nombreEmpresa,
                    ]);
                }

                if ($conTotales) {
                    $filas[] = [
                        'tipo_fila' => 'total_cuenta',
                        'concepto_id' => $conceptoId,
                        'concepto_nombre' => $conceptoNombre,
                        'cuenta' => (int) ($cuentaBlock['cuenta'] ?? 0),
                        'cuenta_codigo' => $cuentaBlock['cuenta_codigo'] ?? '',
                        'cuenta_nombre' => $cuentaBlock['cuenta_nombre'] ?? '',
                        'debe' => (float) ($cuentaBlock['total_debe'] ?? 0),
                        'haber' => (float) ($cuentaBlock['total_haber'] ?? 0),
                        'empresa_id' => $empresaId,
                        'nombreempresa' => $nombreEmpresa,
                    ];
                }

                $totalConceptoDebe += (float) ($cuentaBlock['total_debe'] ?? 0);
                $totalConceptoHaber += (float) ($cuentaBlock['total_haber'] ?? 0);
            }

            if ($conTotales && ($totalConceptoDebe > 0 || $totalConceptoHaber > 0 || count($seccion['cuentas'] ?? []) > 0)) {
                $filas[] = [
                    'tipo_fila' => 'total_concepto',
                    'concepto_id' => $conceptoId,
                    'concepto_nombre' => $conceptoNombre,
                    'debe' => round($totalConceptoDebe, 2),
                    'haber' => round($totalConceptoHaber, 2),
                    'empresa_id' => $empresaId,
                    'nombreempresa' => $nombreEmpresa,
                ];
            }
        }

        return $this->enriquecerEnlaces($filas, $empresaId);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, array<string, mixed>>|Collection<int, array<string, mixed>>
     */
    public function listado(array $filtros, bool $paginar = true, int $perPage = 50): LengthAwarePaginator|Collection
    {
        $resultado = $this->generarDesdeFiltros($filtros);
        $filas = collect($this->aplanarFilas($resultado));

        if (! $paginar) {
            return $filas->values();
        }

        $perPage = max(10, min(200, $perPage));
        $currentPage = Paginator::resolveCurrentPage();
        $items = $filas->slice(($currentPage - 1) * $perPage, $perPage)->values();

        return new PaginatorImpl(
            $items,
            $filas->count(),
            $perPage,
            $currentPage,
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   cantidad_filas: int,
     *   total_debe: float,
     *   total_haber: float,
     *   errores_bridge: list<string>,
     *   stats: array<string, int>
     * }
     */
    public function totales(array $filtros): array
    {
        $resultado = $this->generarDesdeFiltros($filtros);

        return [
            'cantidad_filas' => (int) ($resultado['totales']['lineas'] ?? 0),
            'total_debe' => (float) ($resultado['totales']['debe'] ?? 0),
            'total_haber' => (float) ($resultado['totales']['haber'] ?? 0),
            'errores_bridge' => $resultado['errores_bridge'] ?? [],
            'stats' => $resultado['stats'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function generar(
        int $empresaId,
        ?string $fechaDesde,
        ?string $fechaHasta,
        ?int $mes,
        ?int $anio,
        bool $usarMesCompleto,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
    ): array {
        MayorConceptoRuntimeSupport::elevarLimites();

        [$desde, $hasta] = $this->resolverRangoFechas($fechaDesde, $fechaHasta, $mes, $anio, $usarMesCompleto);

        return $this->procesador->generar(
            $empresaId,
            (int) $desde->format('Ymd'),
            (int) $hasta->format('Ymd'),
            $monedaReporteId,
            $soloMonedaOrigen,
            $this->monedaConverter,
        );
    }

    public function formatearPeriodoTexto(array $filtros): string
    {
        if (($filtros['modo_periodo'] ?? 'mes') === 'mes') {
            $mes = (int) ($filtros['mes'] ?? 0);
            $anio = (int) ($filtros['anio'] ?? 0);
            if ($mes > 0 && $anio > 0) {
                $d = Carbon::createFromDate($anio, $mes, 1);

                return $d->format('01/m/Y').' — '.$d->copy()->endOfMonth()->format('d/m/Y');
            }
        }

        [$desde, $hasta] = MayorConceptoListadoFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );

        if ($desde === '' || $hasta === '') {
            return '';
        }

        $d = Carbon::parse($desde);
        $h = Carbon::parse($hasta);

        return $d->format('d/m/Y').' — '.$h->format('d/m/Y');
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function enriquecerEnlaces(array $filas, int $empresaId): array
    {
        if ($filas === [] || $empresaId <= 0) {
            return $filas;
        }

        $numerosAsiento = array_values(array_unique(array_filter(array_map(
            fn (array $f) => (int) ($f['nro_asiento'] ?? 0),
            $filas
        ), fn (int $n) => $n > 0)));

        $codigosCuenta = array_values(array_unique(array_filter(array_map(
            fn (array $f) => (int) ($f['cuenta'] ?? 0),
            $filas
        ), fn (int $n) => $n > 0)));

        $asientosPorNumero = [];
        if ($numerosAsiento !== []) {
            $asientosPorNumero = DB::table('asiento')
                ->where('empresa_id', $empresaId)
                ->whereIn('numeroasiento', $numerosAsiento)
                ->pluck('id', 'numeroasiento')
                ->all();
        }

        $cuentasPorCodigo = $this->lookupCuentasPorCodigo($empresaId, $codigosCuenta);

        foreach ($filas as $idx => $fila) {
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                $codigo = (int) ($fila['cuenta'] ?? 0);
                if ($codigo > 0) {
                    $filas[$idx]['cuentacontable_id'] = (int) ($cuentasPorCodigo[$codigo] ?? 0);
                }

                continue;
            }

            $nro = (int) ($fila['nro_asiento'] ?? 0);
            $codigo = (int) ($fila['cuenta'] ?? 0);
            $filas[$idx]['asiento_id'] = $nro > 0 ? (int) ($asientosPorNumero[$nro] ?? 0) : 0;
            $filas[$idx]['cuentacontable_id'] = $codigo > 0 ? (int) ($cuentasPorCodigo[$codigo] ?? 0) : 0;
        }

        return $filas;
    }

    /**
     * @param  list<int>  $codigosCuenta
     * @return array<int, int>
     */
    private function lookupCuentasPorCodigo(int $empresaId, array $codigosCuenta): array
    {
        if ($empresaId <= 0 || $codigosCuenta === []) {
            return [];
        }

        return DB::table('cuentacontable')
            ->where('empresa_id', $empresaId)
            ->whereIn('codigo', $codigosCuenta)
            ->pluck('id', 'codigo')
            ->all();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolverRangoFechas(
        ?string $fechaDesde,
        ?string $fechaHasta,
        ?int $mes,
        ?int $anio,
        bool $usarMesCompleto,
    ): array {
        if ($usarMesCompleto && $mes && $anio) {
            $desde = Carbon::createFromDate($anio, $mes, 1)->startOfDay();
            $hasta = $desde->copy()->endOfMonth();

            return [$desde, $hasta];
        }

        $desde = Carbon::parse($fechaDesde ?? now()->startOfMonth()->toDateString())->startOfDay();
        $hasta = Carbon::parse($fechaHasta ?? now()->toDateString())->startOfDay();

        if ($hasta->lt($desde)) {
            $hasta = $desde->copy();
        }

        return [$desde, $hasta];
    }
}
