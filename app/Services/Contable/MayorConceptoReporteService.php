<?php

namespace App\Services\Contable;

use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Contable\MayorConcepto\MayorConceptoAuditoriaSupport;
use App\Support\Contable\MayorConcepto\MayorConceptoConciliacionAsientoSupport;
use App\Support\Contable\MayorConcepto\MayorConceptoMonedaConverter;
use App\Support\Contable\MayorConcepto\MayorConceptoPeriodoProcesador;
use App\Support\Contable\MayorConcepto\MayorConceptoRuntimeSupport;
use App\Support\Contable\MayorConceptoListadoFiltros;
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
        private readonly MayorConceptoConciliacionAsientoSupport $conciliacionAsientoSupport,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generarDesdeFiltros(array $filtros): array
    {
        $empresaIds = MayorConceptoListadoFiltros::empresaIds($filtros);
        $consolidar = (bool) ($filtros['consolidar_empresas'] ?? true);
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

        if ($empresaIds === []) {
            return $this->generar(
                0,
                $fechaDesde !== '' ? $fechaDesde : null,
                $fechaHasta !== '' ? $fechaHasta : null,
                $mes,
                $anio,
                $usarMes,
                $monedaId,
                $soloOrigen,
            );
        }

        if (count($empresaIds) === 1) {
            $resultado = $this->generar(
                (int) $empresaIds[0],
                $fechaDesde !== '' ? $fechaDesde : null,
                $fechaHasta !== '' ? $fechaHasta : null,
                $mes,
                $anio,
                $usarMes,
                $monedaId,
                $soloOrigen,
            );
            $resultado['parametros']['empresa_ids'] = $empresaIds;
            $resultado['parametros']['consolidar_empresas'] = true;

            return $resultado;
        }

        $bloques = [];
        foreach ($empresaIds as $empresaId) {
            $bloques[(int) $empresaId] = $this->generar(
                (int) $empresaId,
                $fechaDesde !== '' ? $fechaDesde : null,
                $fechaHasta !== '' ? $fechaHasta : null,
                $mes,
                $anio,
                $usarMes,
                $monedaId,
                $soloOrigen,
            );
        }

        return $this->fusionarResultadosEmpresas($bloques, $empresaIds, $consolidar);
    }

    /**
     * Genera una sola empresa (para consulta progresiva multiempresa vía AJAX).
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generarUnaEmpresaDesdeFiltros(array $filtros, int $empresaId): array
    {
        $filtrosUna = array_merge($filtros, [
            'empresa_ids' => [$empresaId],
            'empresa_id' => $empresaId,
            'consolidar_empresas' => true,
        ]);

        return $this->generarDesdeFiltros($filtrosUna);
    }

    /**
     * Fusiona bloques ya generados por empresa (sin volver a llamar al motor).
     *
     * @param  array<int, array<string, mixed>>  $bloques
     * @param  list<int>  $empresaIds
     * @return array<string, mixed>
     */
    public function fusionarBloquesEmpresas(array $bloques, array $empresaIds, bool $consolidar): array
    {
        return $this->fusionarResultadosEmpresas($bloques, $empresaIds, $consolidar);
    }

    /**
     * Une resultados por empresa sin modificar el motor: cada bloque ya viene del procesador.
     *
     * @param  array<int, array<string, mixed>>  $bloques
     * @param  list<int>  $empresaIds
     * @return array<string, mixed>
     */
    private function fusionarResultadosEmpresas(array $bloques, array $empresaIds, bool $consolidar): array
    {
        $secciones = [];
        $totalDebe = 0.0;
        $totalHaber = 0.0;
        $totalLineas = 0;
        $erroresBridge = [];
        $stats = [];
        $resultadosPorEmpresa = [];

        foreach ($empresaIds as $empresaId) {
            $bloque = $bloques[$empresaId] ?? null;
            if (! is_array($bloque)) {
                continue;
            }

            $resultadosPorEmpresa[$empresaId] = $bloque;

            foreach ($bloque['secciones'] ?? [] as $seccion) {
                $seccion['empresa_id'] = $empresaId;
                $secciones[] = $seccion;
            }

            $totalDebe += (float) ($bloque['totales']['debe'] ?? 0);
            $totalHaber += (float) ($bloque['totales']['haber'] ?? 0);
            $totalLineas += (int) ($bloque['totales']['lineas'] ?? 0);

            foreach ($bloque['errores_bridge'] ?? [] as $err) {
                $erroresBridge[] = '[Empresa '.$empresaId.'] '.$err;
            }

            foreach ($bloque['stats'] ?? [] as $clave => $valor) {
                if (is_numeric($valor)) {
                    $stats[$clave] = (int) ($stats[$clave] ?? 0) + (int) $valor;
                }
            }
        }

        if ($consolidar) {
            $secciones = $this->fusionarSeccionesConsolidadas($secciones);
        }

        $parametrosBase = ($bloques[$empresaIds[0]] ?? [])['parametros'] ?? [];
        $parametrosBase['empresa_id'] = (int) ($empresaIds[0] ?? 0);
        $parametrosBase['empresa_ids'] = $empresaIds;
        $parametrosBase['consolidar_empresas'] = $consolidar;

        return [
            'parametros' => $parametrosBase,
            'secciones' => $secciones,
            'totales' => [
                'debe' => round($totalDebe, 2),
                'haber' => round($totalHaber, 2),
                'lineas' => $totalLineas,
            ],
            'errores_bridge' => array_values(array_unique($erroresBridge)),
            'stats' => $stats,
            'resultados_por_empresa' => $resultadosPorEmpresa,
        ];
    }

    /**
     * Merge real por concepto/cuenta: mismas cuentas de distintas empresas en un solo bloque,
     * con empresa_id en cada línea (columna Empr. del reporte).
     *
     * @param  list<array<string, mixed>>  $secciones
     * @return list<array<string, mixed>>
     */
    private function fusionarSeccionesConsolidadas(array $secciones): array
    {
        $porConcepto = [];

        foreach ($secciones as $seccion) {
            $conceptoId = (int) ($seccion['concepto_id'] ?? 0);
            $empresaId = (int) ($seccion['empresa_id'] ?? 0);

            if (! isset($porConcepto[$conceptoId])) {
                $porConcepto[$conceptoId] = [
                    'concepto_id' => $conceptoId,
                    'concepto_nombre' => (string) ($seccion['concepto_nombre'] ?? ''),
                    'empresa_id' => 0,
                    'cuentas' => [],
                ];
            } elseif (($porConcepto[$conceptoId]['concepto_nombre'] ?? '') === ''
                && ($seccion['concepto_nombre'] ?? '') !== ''
            ) {
                $porConcepto[$conceptoId]['concepto_nombre'] = (string) $seccion['concepto_nombre'];
            }

            foreach ($seccion['cuentas'] ?? [] as $cuentaBlock) {
                $codigoCuenta = (int) ($cuentaBlock['cuenta'] ?? 0);
                $claveCuenta = (string) ($cuentaBlock['cuenta_codigo'] ?? $codigoCuenta);
                if ($claveCuenta === '' || $claveCuenta === '0') {
                    $claveCuenta = (string) $codigoCuenta;
                }

                if (! isset($porConcepto[$conceptoId]['cuentas'][$claveCuenta])) {
                    $porConcepto[$conceptoId]['cuentas'][$claveCuenta] = [
                        'cuenta' => $codigoCuenta,
                        'cuenta_codigo' => $cuentaBlock['cuenta_codigo'] ?? (string) $codigoCuenta,
                        'cuenta_nombre' => $cuentaBlock['cuenta_nombre'] ?? '',
                        'lineas' => [],
                        'total_debe' => 0.0,
                        'total_haber' => 0.0,
                    ];
                }

                foreach ($cuentaBlock['lineas'] ?? [] as $linea) {
                    $linea['empresa_id'] = $empresaId;
                    $porConcepto[$conceptoId]['cuentas'][$claveCuenta]['lineas'][] = $linea;
                }

                $porConcepto[$conceptoId]['cuentas'][$claveCuenta]['total_debe'] += (float) ($cuentaBlock['total_debe'] ?? 0);
                $porConcepto[$conceptoId]['cuentas'][$claveCuenta]['total_haber'] += (float) ($cuentaBlock['total_haber'] ?? 0);

                if (($porConcepto[$conceptoId]['cuentas'][$claveCuenta]['cuenta_nombre'] ?? '') === ''
                    && ($cuentaBlock['cuenta_nombre'] ?? '') !== ''
                ) {
                    $porConcepto[$conceptoId]['cuentas'][$claveCuenta]['cuenta_nombre'] = (string) $cuentaBlock['cuenta_nombre'];
                }
            }
        }

        ksort($porConcepto, SORT_NUMERIC);

        $salida = [];
        foreach ($porConcepto as $seccionMerge) {
            $cuentas = array_values($seccionMerge['cuentas']);
            usort($cuentas, fn (array $a, array $b) => ((int) ($a['cuenta'] ?? 0)) <=> ((int) ($b['cuenta'] ?? 0)));

            foreach ($cuentas as $idx => $cuenta) {
                $lineas = $cuenta['lineas'] ?? [];
                usort($lineas, function (array $a, array $b): int {
                    $cmpFecha = strcmp((string) ($a['fecha'] ?? ''), (string) ($b['fecha'] ?? ''));
                    if ($cmpFecha !== 0) {
                        return $cmpFecha;
                    }
                    $cmpEmp = ((int) ($a['empresa_id'] ?? 0)) <=> ((int) ($b['empresa_id'] ?? 0));
                    if ($cmpEmp !== 0) {
                        return $cmpEmp;
                    }

                    return ((int) ($a['nro_asiento'] ?? 0)) <=> ((int) ($b['nro_asiento'] ?? 0));
                });
                $cuentas[$idx]['lineas'] = $lineas;
                $cuentas[$idx]['total_debe'] = round((float) ($cuenta['total_debe'] ?? 0), 2);
                $cuentas[$idx]['total_haber'] = round((float) ($cuenta['total_haber'] ?? 0), 2);
            }

            $seccionMerge['cuentas'] = $cuentas;
            $salida[] = $seccionMerge;
        }

        return $salida;
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
        $resumen = [];
        foreach ($resultado['secciones'] ?? [] as $seccion) {
            $empresaId = (int) ($seccion['empresa_id'] ?? $resultado['parametros']['empresa_id'] ?? 0);
            if ($empresaId <= 0) {
                $empresaId = (int) (($resultado['parametros']['empresa_ids'] ?? [])[0] ?? 0);
            }
            $codigosCuenta = [];
            foreach ($seccion['cuentas'] ?? [] as $cuentaBlock) {
                $codigo = (int) ($cuentaBlock['cuenta'] ?? 0);
                if ($codigo > 0) {
                    $codigosCuenta[$codigo] = true;
                }
            }
            $cuentasPorCodigo = $this->lookupCuentasPorCodigo($empresaId, array_keys($codigosCuenta));
            $nombreEmpresa = $empresaId > 0
                ? ($this->empresaRepository->find($empresaId)?->nombre ?? '')
                : '';

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
                    'empresa_id' => (int) ($seccion['empresa_id'] ?? 0),
                    'nombreempresa' => (int) ($seccion['empresa_id'] ?? 0) > 0 ? $nombreEmpresa : '',
                ];
            }

            $resumen[] = [
                'concepto_id' => $conceptoId,
                'concepto_nombre' => $seccion['concepto_nombre'] ?? '',
                'empresa_id' => (int) ($seccion['empresa_id'] ?? 0),
                'nombreempresa' => (int) ($seccion['empresa_id'] ?? 0) > 0 ? $nombreEmpresa : '',
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
     * Conciliación asiento a asiento: neto analítico + neto concepto = 0.
     *
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    public function conciliarPorAsiento(array $resultado): array
    {
        $empresaId = (int) ($resultado['parametros']['empresa_id'] ?? 0);

        return $this->conciliacionAsientoSupport->conciliar($resultado, $empresaId);
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return array{
     *   cuadra: bool,
     *   conciliacion: array<string, mixed>
     * }
     */
    public function armarAuditoriaPanel(array $resultado): array
    {
        $porEmpresa = $resultado['resultados_por_empresa'] ?? null;
        if (! is_array($porEmpresa) || $porEmpresa === []) {
            $conciliacion = $this->conciliarPorAsiento($resultado);

            return [
                'cuadra' => (bool) ($conciliacion['cuadra'] ?? false),
                'conciliacion' => $conciliacion,
            ];
        }

        if (count($porEmpresa) === 1) {
            $empresaId = (int) array_key_first($porEmpresa);
            $bloque = $porEmpresa[$empresaId];
            $conciliacion = $this->conciliacionAsientoSupport->conciliar($bloque, $empresaId);

            return [
                'cuadra' => (bool) ($conciliacion['cuadra'] ?? false),
                'conciliacion' => $conciliacion,
            ];
        }

        $filasDescuadradas = [];
        $filasCuadradas = [];
        $asientosAnalizados = 0;
        $asientosCuadrados = 0;
        $asientosDescuadrados = 0;
        $tolerancia = 1.0;
        $regla = 'Neto analítico + Neto concepto = 0';

        foreach ($porEmpresa as $empresaId => $bloque) {
            $empresaId = (int) $empresaId;
            $nombreEmpresa = $this->empresaRepository->find($empresaId)?->nombre ?? (string) $empresaId;
            $conc = $this->conciliacionAsientoSupport->conciliar($bloque, $empresaId);

            $tolerancia = (float) ($conc['tolerancia'] ?? $tolerancia);
            $regla = (string) ($conc['regla'] ?? $regla);
            $asientosAnalizados += (int) ($conc['asientos_analizados'] ?? 0);
            $asientosCuadrados += (int) ($conc['asientos_cuadrados'] ?? 0);
            $asientosDescuadrados += (int) ($conc['asientos_descuadrados'] ?? 0);

            foreach ($conc['filas_descuadradas'] ?? [] as $fila) {
                $fila['empresa_id'] = $empresaId;
                $fila['nombreempresa'] = $nombreEmpresa;
                $filasDescuadradas[] = $fila;
            }
            foreach ($conc['filas_cuadradas'] ?? [] as $fila) {
                $fila['empresa_id'] = $empresaId;
                $fila['nombreempresa'] = $nombreEmpresa;
                $filasCuadradas[] = $fila;
            }
        }

        $cuadra = $asientosDescuadrados === 0 && $asientosAnalizados > 0;
        $porcentaje = $asientosAnalizados > 0
            ? round(($asientosCuadrados / $asientosAnalizados) * 100, 1)
            : 0.0;

        $conciliacion = [
            'cuadra' => $cuadra,
            'regla' => $regla,
            'tolerancia' => $tolerancia,
            'asientos_analizados' => $asientosAnalizados,
            'asientos_cuadrados' => $asientosCuadrados,
            'asientos_descuadrados' => $asientosDescuadrados,
            'porcentaje_cuadrado' => $porcentaje,
            'filas_descuadradas' => $filasDescuadradas,
            'filas_cuadradas' => $filasCuadradas,
            'multiempresa' => true,
        ];

        return [
            'cuadra' => $cuadra,
            'conciliacion' => $conciliacion,
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
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    public function aplanarFilasConTotalesFiltradas(array $resultado, array $filtros): array
    {
        return MayorConceptoListadoFiltros::aplicarFiltroDetalle(
            $this->aplanarFilasConTotales($resultado),
            $filtros,
        );
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return list<array<string, mixed>>
     */
    private function aplanarFilasInterno(array $resultado, bool $conTotales): array
    {
        $empresaIds = array_values(array_filter(array_map(
            'intval',
            $resultado['parametros']['empresa_ids'] ?? [],
        ), fn (int $id) => $id > 0));
        if ($empresaIds === [] && (int) ($resultado['parametros']['empresa_id'] ?? 0) > 0) {
            $empresaIds = [(int) $resultado['parametros']['empresa_id']];
        }

        $consolidar = (bool) ($resultado['parametros']['consolidar_empresas'] ?? true);
        $nombresEmpresa = [];
        foreach ($empresaIds as $eid) {
            $nombresEmpresa[$eid] = $this->empresaRepository->find($eid)?->nombre ?? '';
        }

        $filas = [];
        $empresaHeaderActual = 0;

        foreach ($resultado['secciones'] ?? [] as $seccion) {
            $empresaId = (int) ($seccion['empresa_id'] ?? $resultado['parametros']['empresa_id'] ?? 0);
            $nombreEmpresa = $nombresEmpresa[$empresaId]
                ?? ($this->empresaRepository->find($empresaId)?->nombre ?? '');

            if (! $consolidar && $empresaId > 0 && $empresaId !== $empresaHeaderActual) {
                $empresaHeaderActual = $empresaId;
                $filas[] = [
                    'tipo_fila' => 'header_empresa',
                    'empresa_id' => $empresaId,
                    'nombreempresa' => $nombreEmpresa,
                ];
            }

            $conceptoId = (int) ($seccion['concepto_id'] ?? 0);
            $conceptoNombre = (string) ($seccion['concepto_nombre'] ?? '');
            $totalConceptoDebe = 0.0;
            $totalConceptoHaber = 0.0;

            foreach ($seccion['cuentas'] ?? [] as $cuentaBlock) {
                foreach ($cuentaBlock['lineas'] ?? [] as $ln) {
                    $empresaLinea = (int) ($ln['empresa_id'] ?? $empresaId);
                    $nombreLinea = $nombresEmpresa[$empresaLinea]
                        ?? ($empresaLinea > 0
                            ? ($this->empresaRepository->find($empresaLinea)?->nombre ?? '')
                            : $nombreEmpresa);

                    $filas[] = array_merge($ln, [
                        'tipo_fila' => 'detalle',
                        'empresa_id' => $empresaLinea,
                        'nombreempresa' => $nombreLinea,
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
                        'nombreempresa' => $empresaId > 0 ? $nombreEmpresa : '',
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

        return $this->enriquecerEnlaces($filas);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function formatearEmpresasTexto(array $filtros): string
    {
        $nombres = [];
        foreach (MayorConceptoListadoFiltros::empresaIds($filtros) as $empresaId) {
            $nombre = $this->empresaRepository->find((int) $empresaId)?->nombre;
            if ($nombre) {
                $nombres[] = $nombre;
            }
        }

        return implode(' · ', $nombres);
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
    private function enriquecerEnlaces(array $filas): array
    {
        if ($filas === []) {
            return $filas;
        }

        $porEmpresa = [];
        foreach ($filas as $idx => $fila) {
            $empresaId = (int) ($fila['empresa_id'] ?? 0);
            if ($empresaId <= 0) {
                continue;
            }
            $porEmpresa[$empresaId][] = $idx;
        }

        foreach ($porEmpresa as $empresaId => $indices) {
            $numerosAsiento = [];
            $codigosCuenta = [];
            foreach ($indices as $idx) {
                $nro = (int) ($filas[$idx]['nro_asiento'] ?? 0);
                if ($nro > 0) {
                    $numerosAsiento[$nro] = true;
                }
                $codigo = (int) ($filas[$idx]['cuenta'] ?? 0);
                if ($codigo > 0) {
                    $codigosCuenta[$codigo] = true;
                }
            }

            $asientosPorNumero = [];
            if ($numerosAsiento !== []) {
                $asientosPorNumero = DB::table('asiento')
                    ->where('empresa_id', $empresaId)
                    ->whereIn('numeroasiento', array_keys($numerosAsiento))
                    ->pluck('id', 'numeroasiento')
                    ->all();
            }

            $cuentasPorCodigo = $this->lookupCuentasPorCodigo($empresaId, array_keys($codigosCuenta));

            foreach ($indices as $idx) {
                $fila = $filas[$idx];
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
