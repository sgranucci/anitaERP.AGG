<?php

namespace App\Services\Contable;

use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Contable\MayorConcepto\MayorConceptoMonedaConverter;
use App\Support\Contable\MayorConceptoListadoFiltros;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaComprobanteEnricher;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaCentrocostoFiltroSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaEmisorEnricher;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaOrdencompraEnricher;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaProcesador;
use App\Support\Contable\MayorPlanoCuentaListadoFiltros;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as PaginatorImpl;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

class MayorPlanoCuentaReporteService
{
    public function __construct(
        private readonly MayorPlanoCuentaProcesador $procesador,
        private readonly MayorConceptoMonedaConverter $monedaConverter,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly MayorPlanoCuentaOrdencompraEnricher $ordencompraEnricher,
        private readonly MayorPlanoCuentaEmisorEnricher $emisorEnricher,
        private readonly MayorPlanoCuentaComprobanteEnricher $comprobanteEnricher,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generarDesdeFiltros(array $filtros): array
    {
        [$fechaDesde, $fechaHasta] = $this->resolverRangoYmd($filtros);
        $empresaIds = array_values(array_filter(array_map('intval', $filtros['empresa_ids'] ?? []), fn (int $id) => $id > 0));
        $consolidar = (bool) ($filtros['consolidar_empresas'] ?? true);
        $centrocostoFiltro = new MayorPlanoCuentaCentrocostoFiltroSupport(
            $filtros['centrocostos_codigo'] ?? '',
            (string) ($filtros['cc_desde'] ?? ''),
            (string) ($filtros['cc_hasta'] ?? ''),
            $filtros['incluir_sin_cc'] ?? null,
        );

        $parametrosComunes = [
            $fechaDesde,
            $fechaHasta,
            (int) ($filtros['cuenta_desde'] ?? 0),
            (int) ($filtros['cuenta_hasta'] ?? 0),
            (int) ($filtros['moneda_id'] ?? 1),
            (bool) ($filtros['solo_moneda_origen'] ?? false),
            (bool) ($filtros['incluye_subdiario'] ?? true),
            (string) ($filtros['modo_inclusion_asientos'] ?? 'sin_cierre_ni_inflacion'),
            $this->monedaConverter,
            array_values(array_filter(array_map('intval', $filtros['cuentas'] ?? []), fn (int $c) => $c > 0)),
            $centrocostoFiltro,
            (bool) ($filtros['agrupar_por_cc'] ?? false),
            (bool) ($filtros['solo_movimientos_ventas'] ?? false),
        ];

        if ($consolidar || count($empresaIds) <= 1) {
            $resultado = $this->procesador->generar($empresaIds, ...$parametrosComunes);
            $resultado['parametros']['consolidar_empresas'] = $consolidar || count($empresaIds) <= 1;

            return $resultado;
        }

        $bloques = [];
        foreach ($empresaIds as $empresaId) {
            $bloques[] = $this->procesador->generar([(int) $empresaId], ...$parametrosComunes);
        }

        return $this->fusionarResultadosDesconsolidados($bloques, $empresaIds);
    }

    /**
     * @param  list<array<string, mixed>>  $bloques
     * @param  list<int>  $empresaIds
     * @return array<string, mixed>
     */
    private function fusionarResultadosDesconsolidados(array $bloques, array $empresaIds): array
    {
        $secciones = [];
        $totalDebe = 0.0;
        $totalHaber = 0.0;
        $totalLineas = 0;
        $erroresBridge = [];
        $stats = [
            'ctamov_filas' => 0,
            'subdiario_filas' => 0,
            'pago_filas' => 0,
            'pago_leyendas_indexadas' => 0,
        ];

        foreach ($bloques as $idx => $bloque) {
            $empresaId = (int) ($empresaIds[$idx] ?? 0);
            foreach ($bloque['secciones'] ?? [] as $seccion) {
                $seccion['empresa_id'] = $empresaId;
                $secciones[] = $seccion;
            }

            $totalDebe += (float) ($bloque['totales']['debe'] ?? 0);
            $totalHaber += (float) ($bloque['totales']['haber'] ?? 0);
            $totalLineas += (int) ($bloque['totales']['lineas'] ?? 0);

            foreach ($bloque['errores_bridge'] ?? [] as $err) {
                $erroresBridge[] = $err;
            }

            foreach ($bloque['stats'] ?? [] as $clave => $valor) {
                if (is_numeric($valor)) {
                    $stats[$clave] = (int) ($stats[$clave] ?? 0) + (int) $valor;
                }
            }
        }

        $parametrosBase = $bloques[0]['parametros'] ?? [];
        $parametrosBase['empresa_ids'] = $empresaIds;
        $parametrosBase['consolidar_empresas'] = false;

        return [
            'parametros' => $parametrosBase,
            'secciones' => $secciones,
            'totales' => [
                'debe' => round($totalDebe, 2),
                'haber' => round($totalHaber, 2),
                'lineas' => $totalLineas,
                'cuentas' => count($secciones),
            ],
            'errores_bridge' => array_values(array_unique($erroresBridge)),
            'stats' => $stats,
        ];
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return list<array<string, mixed>>
     */
    public function resumenPorCuenta(array $resultado): array
    {
        $resumen = [];
        foreach ($resultado['secciones'] ?? [] as $seccion) {
            if (($seccion['grupos_cc'] ?? []) !== []) {
                foreach ($seccion['grupos_cc'] as $grupoCc) {
                    $resumen[] = [
                        'cuenta' => (int) ($seccion['cuenta'] ?? 0),
                        'cuenta_codigo' => $seccion['cuenta_codigo'] ?? '',
                        'cuenta_nombre' => $seccion['cuenta_nombre'] ?? '',
                        'centrocosto_codigo' => $grupoCc['centrocosto_codigo'] ?? '',
                        'centrocosto_nombre' => $grupoCc['centrocosto_nombre'] ?? '',
                        'saldo_inicial' => (float) ($grupoCc['saldo_inicial'] ?? 0),
                        'total_debe' => (float) ($grupoCc['total_debe'] ?? 0),
                        'total_haber' => (float) ($grupoCc['total_haber'] ?? 0),
                        'cantidad_lineas' => (int) ($grupoCc['cantidad_lineas'] ?? 0),
                    ];
                }
                continue;
            }

            $resumen[] = [
                'cuenta' => (int) ($seccion['cuenta'] ?? 0),
                'cuenta_codigo' => $seccion['cuenta_codigo'] ?? '',
                'cuenta_nombre' => $seccion['cuenta_nombre'] ?? '',
                'saldo_inicial' => (float) ($seccion['saldo_inicial'] ?? 0),
                'total_debe' => (float) ($seccion['total_debe'] ?? 0),
                'total_haber' => (float) ($seccion['total_haber'] ?? 0),
                'cantidad_lineas' => (int) ($seccion['cantidad_lineas'] ?? 0),
            ];
        }

        return $this->enriquecerResumenCuentas(
            $resumen,
            $resultado['parametros']['empresa_ids'] ?? [],
        );
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return list<array<string, mixed>>
     */
    public function resumenPorCentrocosto(array $resultado): array
    {
        $acumulado = [];
        foreach ($resultado['secciones'] ?? [] as $seccion) {
            foreach ($seccion['grupos_cc'] ?? [] as $grupoCc) {
                $codigo = trim((string) ($grupoCc['centrocosto_codigo'] ?? ''));
                $clave = $codigo !== '' ? $codigo : '__SIN_CC__';
                if (! isset($acumulado[$clave])) {
                    $acumulado[$clave] = [
                        'centrocosto_codigo' => $codigo,
                        'centrocosto_nombre' => $grupoCc['centrocosto_nombre'] ?? '',
                        'saldo_inicial' => 0.0,
                        'total_debe' => 0.0,
                        'total_haber' => 0.0,
                        'cantidad_lineas' => 0,
                        'cuentas' => [],
                    ];
                }
                $acumulado[$clave]['saldo_inicial'] += (float) ($grupoCc['saldo_inicial'] ?? 0);
                $acumulado[$clave]['total_debe'] += (float) ($grupoCc['total_debe'] ?? 0);
                $acumulado[$clave]['total_haber'] += (float) ($grupoCc['total_haber'] ?? 0);
                $acumulado[$clave]['cantidad_lineas'] += (int) ($grupoCc['cantidad_lineas'] ?? 0);
                $acumulado[$clave]['cuentas'][(int) ($seccion['cuenta'] ?? 0)] = true;
            }
        }

        $resumen = array_values(array_map(static function (array $row): array {
            $row['cantidad_cuentas'] = count($row['cuentas']);
            unset($row['cuentas']);
            $row['saldo_inicial'] = round((float) $row['saldo_inicial'], 2);
            $row['total_debe'] = round((float) $row['total_debe'], 2);
            $row['total_haber'] = round((float) $row['total_haber'], 2);

            return $row;
        }, $acumulado));
        usort($resumen, static fn (array $a, array $b): int => strnatcasecmp(
            (string) ($a['centrocosto_codigo'] ?? ''),
            (string) ($b['centrocosto_codigo'] ?? ''),
        ));

        return $resumen;
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    public function aplanarFilas(array $resultado, array $filtros = [], bool $conTotales = false): array
    {
        $filas = [];
        $empresaIds = $resultado['parametros']['empresa_ids'] ?? [];
        $consolidar = (bool) ($filtros['consolidar_empresas'] ?? $resultado['parametros']['consolidar_empresas'] ?? true);
        $empresaHeaderActual = 0;

        foreach ($resultado['secciones'] ?? [] as $seccion) {
            $empresaSeccion = (int) ($seccion['empresa_id'] ?? 0);
            if (! $consolidar && $empresaSeccion > 0 && $empresaSeccion !== $empresaHeaderActual) {
                $empresaHeaderActual = $empresaSeccion;
                $filas[] = [
                    'tipo_fila' => 'header_empresa',
                    'empresa_id' => $empresaSeccion,
                    'nombreempresa' => $this->empresaRepository->find($empresaSeccion)?->nombre ?? '',
                ];
            }

            $cuenta = (int) ($seccion['cuenta'] ?? 0);
            $nombreEmpresa = $this->resolverNombreEmpresaFila($empresaIds, $empresaSeccion, $consolidar);

            $filas[] = [
                'tipo_fila' => 'header_cuenta',
                'cuenta' => $cuenta,
                'cuenta_codigo' => $seccion['cuenta_codigo'] ?? '',
                'cuenta_nombre' => $seccion['cuenta_nombre'] ?? '',
                'nombreempresa' => $nombreEmpresa,
            ];

            $gruposCc = $seccion['grupos_cc'] ?? [];
            $soloTotalesVentas = ! empty($filtros['solo_movimientos_ventas']);
            if ($gruposCc !== []) {
                foreach ($gruposCc as $grupoCc) {
                    $filas[] = [
                        'tipo_fila' => 'header_cc',
                        'cuenta' => $cuenta,
                        'cuenta_codigo' => $seccion['cuenta_codigo'] ?? '',
                        'cuenta_nombre' => $seccion['cuenta_nombre'] ?? '',
                        'centrocosto_codigo' => $grupoCc['centrocosto_codigo'] ?? '',
                        'centrocosto_nombre' => $grupoCc['centrocosto_nombre'] ?? '',
                        'nombreempresa' => $nombreEmpresa,
                    ];
                    if (! $soloTotalesVentas
                        && ((float) ($grupoCc['saldo_inicial'] ?? 0) !== 0.0 || ($grupoCc['cantidad_lineas'] ?? 0) === 0)
                    ) {
                        $filas[] = [
                            'tipo_fila' => 'saldo_inicial',
                            'cuenta' => $cuenta,
                            'cuenta_codigo' => $seccion['cuenta_codigo'] ?? '',
                            'cuenta_nombre' => $seccion['cuenta_nombre'] ?? '',
                            'centrocosto_codigo' => $grupoCc['centrocosto_codigo'] ?? '',
                            'centrocosto_nombre' => $grupoCc['centrocosto_nombre'] ?? '',
                            'saldo_ejercicio' => (float) ($grupoCc['saldo_ejercicio_inicial'] ?? $grupoCc['saldo_inicial'] ?? 0),
                            'nombreempresa' => $nombreEmpresa,
                        ];
                    }
                    if (! $soloTotalesVentas) {
                        foreach ($grupoCc['lineas'] ?? [] as $ln) {
                            $filas[] = $ln;
                        }
                    }
                    if (($grupoCc['total_debe'] ?? 0) > 0 || ($grupoCc['total_haber'] ?? 0) > 0) {
                        $filas[] = [
                            'tipo_fila' => 'total_cc',
                            'cuenta' => $cuenta,
                            'centrocosto_codigo' => $grupoCc['centrocosto_codigo'] ?? '',
                            'centrocosto_nombre' => $grupoCc['centrocosto_nombre'] ?? '',
                            'debe' => (float) ($grupoCc['total_debe'] ?? 0),
                            'haber' => (float) ($grupoCc['total_haber'] ?? 0),
                            'nombreempresa' => $nombreEmpresa,
                        ];
                    }
                }
            } elseif (! $soloTotalesVentas
                && ((float) ($seccion['saldo_inicial'] ?? 0) !== 0.0 || ($seccion['cantidad_lineas'] ?? 0) === 0)
            ) {
                $filas[] = [
                    'tipo_fila' => 'saldo_inicial',
                    'cuenta' => $cuenta,
                    'cuenta_codigo' => $seccion['cuenta_codigo'] ?? '',
                    'cuenta_nombre' => $seccion['cuenta_nombre'] ?? '',
                    'saldo_ejercicio' => (float) ($seccion['saldo_ejercicio_inicial'] ?? $seccion['saldo_inicial'] ?? 0),
                    'nombreempresa' => $nombreEmpresa,
                ];
            }

            if ($gruposCc === [] && ! $soloTotalesVentas) {
                foreach ($seccion['lineas'] ?? [] as $ln) {
                    $filas[] = $ln;
                }
            }

            $incluirTotalCuenta = $conTotales || $soloTotalesVentas;
            if ($incluirTotalCuenta && (($seccion['total_debe'] ?? 0) > 0 || ($seccion['total_haber'] ?? 0) > 0)) {
                $filas[] = [
                    'tipo_fila' => 'total_cuenta',
                    'cuenta' => $cuenta,
                    'cuenta_codigo' => $seccion['cuenta_codigo'] ?? '',
                    'cuenta_nombre' => $seccion['cuenta_nombre'] ?? '',
                    'debe' => (float) ($seccion['total_debe'] ?? 0),
                    'haber' => (float) ($seccion['total_haber'] ?? 0),
                    'nombreempresa' => $nombreEmpresa,
                ];
            }
        }

        $filas = $this->enriquecerEnlaces($filas, $empresaIds);
        $filas = $this->ordencompraEnricher->enriquecer($filas);
        $filas = $this->comprobanteEnricher->enriquecer($filas);
        $filas = $this->completarNroOcDesdeIds($filas);
        $filas = $this->emisorEnricher->enriquecer($filas);

        if ($filtros !== []) {
            $filas = MayorPlanoCuentaListadoFiltros::aplicarFiltroTexto(
                $filas,
                (string) ($filtros['filtro_texto'] ?? ''),
            );
        }

        return $filas;
    }

    /**
     * Si hay ordencompra_id (p. ej. desde asiento), alinea nro_oc al número real de esa OC.
     * Con FK de asiento sobrescribe nros espurios (renglón aplicped confundido con OC).
     *
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function completarNroOcDesdeIds(array $filas): array
    {
        $ids = [];
        foreach ($filas as $fila) {
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }
            $ocId = (int) ($fila['ordencompra_id'] ?? 0);
            if ($ocId > 0) {
                $ids[$ocId] = true;
            }
        }

        if ($ids === []) {
            return $filas;
        }

        $mapa = DB::table('ordencompra')
            ->whereIn('id', array_keys($ids))
            ->pluck('numeroordencompra', 'id')
            ->all();

        foreach ($filas as $idx => $fila) {
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }
            $ocId = (int) ($fila['ordencompra_id'] ?? 0);
            if ($ocId <= 0) {
                continue;
            }
            $nro = (int) ($mapa[$ocId] ?? 0);
            if ($nro <= 0) {
                continue;
            }
            $ocAsiento = (int) ($fila['ordencompra_id_asiento'] ?? 0);
            $nroActual = (int) ($fila['nro_oc'] ?? 0);
            // FK del asiento manda; si no hay nro, completar desde el id resuelto.
            if ($ocAsiento === $ocId || $nroActual <= 0) {
                $filas[$idx]['nro_oc'] = $nro;
            }
        }

        return $filas;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
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

        [$desde, $hasta] = MayorPlanoCuentaListadoFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );

        if ($desde === '' || $hasta === '') {
            return '';
        }

        return Carbon::parse($desde)->format('d/m/Y').' — '.Carbon::parse($hasta)->format('d/m/Y');
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function formatearEmpresasTexto(array $filtros): string
    {
        $nombres = [];
        foreach ($filtros['empresa_ids'] ?? [] as $empresaId) {
            $nombre = $this->empresaRepository->find((int) $empresaId)?->nombre;
            if ($nombre) {
                $nombres[] = $nombre;
            }
        }

        return implode(' · ', $nombres);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function formatearInclusionAsientosTexto(array $filtros): string
    {
        return match ($filtros['modo_inclusion_asientos'] ?? 'sin_cierre_ni_inflacion') {
            'todos' => 'Incluye asiento de cierre y aj. x inflación',
            'sin_cierre' => 'No incluye asiento de cierre',
            'sin_inflacion' => 'No incluye asiento de aj. x inflación',
            default => 'No incluye asientos de cierre ni de aj. x inflación',
        };
    }

    /** @param array<string, mixed> $filtros */
    public function formatearCentrocostosTexto(array $filtros): string
    {
        $filtro = new MayorPlanoCuentaCentrocostoFiltroSupport(
            $filtros['centrocostos_codigo'] ?? '',
            (string) ($filtros['cc_desde'] ?? ''),
            (string) ($filtros['cc_hasta'] ?? ''),
            $filtros['incluir_sin_cc'] ?? null,
        );
        $texto = $filtro->metaTexto();

        return ! empty($filtros['agrupar_por_cc']) ? $texto.' · agrupado por CC' : $texto;
    }

    /** @param array<string, mixed> $filtros */
    public function formatearOrigenMovimientosTexto(array $filtros): string
    {
        $partes = [];
        if (! empty($filtros['solo_movimientos_ventas'])) {
            $partes[] = 'Solo movimientos de ventas (totales)';
        }
        if (! empty($filtros['solo_moneda_origen'])) {
            $partes[] = 'Solo moneda origen';
        }
        if (($filtros['incluye_subdiario'] ?? true) === false) {
            $partes[] = 'Sin subdiario';
        }

        return implode(' · ', $partes);
    }

    /**
     * Parte el resultado en un bloque por cuenta o por centro de costo (Excel multi-hoja).
     *
     * @param  array<string, mixed>  $resultado
     * @param  'cuenta'|'centrocosto'  $dimension
     * @return list<array{titulo: string, resultado: array<string, mixed>}>
     */
    public function partirResultadoParaSolapasExcel(array $resultado, string $dimension): array
    {
        if ($dimension === 'centrocosto') {
            return $this->partirResultadoPorCentrocosto($resultado);
        }

        return $this->partirResultadoPorCuenta($resultado);
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return list<array{titulo: string, resultado: array<string, mixed>}>
     */
    private function partirResultadoPorCuenta(array $resultado): array
    {
        $bloques = [];
        $usados = [];

        foreach ($resultado['secciones'] ?? [] as $seccion) {
            $codigo = trim((string) ($seccion['cuenta_codigo'] ?? ''));
            $nombre = trim((string) ($seccion['cuenta_nombre'] ?? ''));
            $tituloBase = $codigo !== '' ? $codigo : ('Cta '.(int) ($seccion['cuenta'] ?? 0));
            if ($nombre !== '') {
                $tituloBase .= ' '.$nombre;
            }
            $titulo = $this->tituloHojaExcelUnico($tituloBase, $usados);

            $mini = $resultado;
            $mini['secciones'] = [$seccion];
            $mini['totales'] = $this->totalesDesdeSecciones([$seccion], $resultado['stats'] ?? []);
            $mini['stats'] = $resultado['stats'] ?? [];

            $bloques[] = [
                'titulo' => $titulo,
                'resultado' => $mini,
            ];
        }

        return $bloques;
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return list<array{titulo: string, resultado: array<string, mixed>}>
     */
    private function partirResultadoPorCentrocosto(array $resultado): array
    {
        /** @var array<string, array{codigo: string, nombre: string, secciones: list<array<string, mixed>>}> $porCc */
        $porCc = [];

        foreach ($resultado['secciones'] ?? [] as $seccion) {
            $gruposCc = $seccion['grupos_cc'] ?? [];
            if ($gruposCc !== []) {
                foreach ($gruposCc as $grupoCc) {
                    $codigo = trim((string) ($grupoCc['centrocosto_codigo'] ?? ''));
                    $clave = $codigo !== '' ? $codigo : '__SIN_CC__';
                    if (! isset($porCc[$clave])) {
                        $porCc[$clave] = [
                            'codigo' => $codigo,
                            'nombre' => (string) ($grupoCc['centrocosto_nombre'] ?? ''),
                            'secciones' => [],
                        ];
                    } elseif ($porCc[$clave]['nombre'] === '' && ($grupoCc['centrocosto_nombre'] ?? '') !== '') {
                        $porCc[$clave]['nombre'] = (string) $grupoCc['centrocosto_nombre'];
                    }

                    $seccionCc = $seccion;
                    $seccionCc['grupos_cc'] = [$grupoCc];
                    $seccionCc['lineas'] = $grupoCc['lineas'] ?? [];
                    $seccionCc['saldo_inicial'] = (float) ($grupoCc['saldo_inicial'] ?? 0);
                    $seccionCc['saldo_ejercicio_inicial'] = (float) ($grupoCc['saldo_ejercicio_inicial'] ?? $grupoCc['saldo_inicial'] ?? 0);
                    $seccionCc['total_debe'] = (float) ($grupoCc['total_debe'] ?? 0);
                    $seccionCc['total_haber'] = (float) ($grupoCc['total_haber'] ?? 0);
                    $seccionCc['cantidad_lineas'] = (int) ($grupoCc['cantidad_lineas'] ?? count($seccionCc['lineas']));
                    $porCc[$clave]['secciones'][] = $seccionCc;
                }

                continue;
            }

            $lineasPorCc = [];
            foreach ($seccion['lineas'] ?? [] as $linea) {
                $codigo = trim((string) ($linea['centrocosto_codigo'] ?? ''));
                $clave = $codigo !== '' ? $codigo : '__SIN_CC__';
                $lineasPorCc[$clave]['codigo'] = $codigo;
                $lineasPorCc[$clave]['nombre'] = (string) ($linea['centrocosto_nombre'] ?? ($lineasPorCc[$clave]['nombre'] ?? ''));
                $lineasPorCc[$clave]['lineas'][] = $linea;
            }

            // Sin movimientos: una solapa “Sin CC” para no perder saldo inicial de la cuenta.
            if ($lineasPorCc === []) {
                $clave = '__SIN_CC__';
                $lineasPorCc[$clave] = [
                    'codigo' => '',
                    'nombre' => '',
                    'lineas' => [],
                ];
            }

            foreach ($lineasPorCc as $clave => $pack) {
                if (! isset($porCc[$clave])) {
                    $porCc[$clave] = [
                        'codigo' => (string) ($pack['codigo'] ?? ''),
                        'nombre' => (string) ($pack['nombre'] ?? ''),
                        'secciones' => [],
                    ];
                } elseif ($porCc[$clave]['nombre'] === '' && ($pack['nombre'] ?? '') !== '') {
                    $porCc[$clave]['nombre'] = (string) $pack['nombre'];
                }

                $lineas = $pack['lineas'] ?? [];
                $totalDebe = 0.0;
                $totalHaber = 0.0;
                foreach ($lineas as $ln) {
                    $totalDebe += (float) ($ln['debe'] ?? 0);
                    $totalHaber += (float) ($ln['haber'] ?? 0);
                }

                $seccionCc = $seccion;
                unset($seccionCc['grupos_cc']);
                $seccionCc['lineas'] = $lineas;
                $seccionCc['total_debe'] = round($totalDebe, 2);
                $seccionCc['total_haber'] = round($totalHaber, 2);
                $seccionCc['cantidad_lineas'] = count($lineas);
                // Saldo inicial de cuenta no se prorratea por CC sin grupos: solo en hoja Sin CC.
                if ($clave !== '__SIN_CC__') {
                    $seccionCc['saldo_inicial'] = 0.0;
                    $seccionCc['saldo_ejercicio_inicial'] = 0.0;
                }
                $porCc[$clave]['secciones'][] = $seccionCc;
            }
        }

        uksort($porCc, static function (string $a, string $b): int {
            if ($a === '__SIN_CC__') {
                return 1;
            }
            if ($b === '__SIN_CC__') {
                return -1;
            }

            return strnatcasecmp($a, $b);
        });

        $bloques = [];
        $usados = [];
        foreach ($porCc as $clave => $pack) {
            $codigo = (string) ($pack['codigo'] ?? '');
            $nombre = trim((string) ($pack['nombre'] ?? ''));
            $tituloBase = $clave === '__SIN_CC__'
                ? 'Sin CC'
                : ($codigo !== '' ? $codigo : 'CC');
            if ($nombre !== '' && $clave !== '__SIN_CC__') {
                $tituloBase .= ' '.$nombre;
            }
            $titulo = $this->tituloHojaExcelUnico($tituloBase, $usados);

            $mini = $resultado;
            $mini['secciones'] = $pack['secciones'];
            $mini['totales'] = $this->totalesDesdeSecciones($pack['secciones'], $resultado['stats'] ?? []);
            $mini['stats'] = $resultado['stats'] ?? [];

            $bloques[] = [
                'titulo' => $titulo,
                'resultado' => $mini,
            ];
        }

        return $bloques;
    }

    /**
     * @param  list<array<string, mixed>>  $secciones
     * @param  array<string, mixed>  $stats
     * @return array{cuentas: int, debe: float, haber: float, lineas: int, stats: array<string, mixed>}
     */
    private function totalesDesdeSecciones(array $secciones, array $stats = []): array
    {
        $debe = 0.0;
        $haber = 0.0;
        $lineas = 0;
        $cuentas = [];

        foreach ($secciones as $seccion) {
            $cuenta = (int) ($seccion['cuenta'] ?? 0);
            if ($cuenta > 0) {
                $cuentas[$cuenta] = true;
            }
            $debe += (float) ($seccion['total_debe'] ?? 0);
            $haber += (float) ($seccion['total_haber'] ?? 0);
            $lineas += (int) ($seccion['cantidad_lineas'] ?? count($seccion['lineas'] ?? []));
        }

        return [
            'cuentas' => count($cuentas),
            'debe' => round($debe, 2),
            'haber' => round($haber, 2),
            'lineas' => $lineas,
            'stats' => $stats,
        ];
    }

    /**
     * @param  array<string, true>  $usados
     */
    private function tituloHojaExcelUnico(string $tituloBase, array &$usados): string
    {
        $base = self::sanitizarNombreHojaExcel($tituloBase);
        $nombre = $base;
        $i = 2;
        while (isset($usados[$nombre])) {
            $suffix = ' ('.$i.')';
            $nombre = mb_substr($base, 0, max(1, 31 - mb_strlen($suffix))).$suffix;
            $i++;
        }
        $usados[$nombre] = true;

        return $nombre;
    }

    public static function sanitizarNombreHojaExcel(string $nombre): string
    {
        $nombre = preg_replace('/[\\\\\\/*\\[\\]:?]/', ' ', $nombre) ?? 'Hoja';
        $nombre = trim(preg_replace('/\s+/', ' ', $nombre) ?? 'Hoja');

        return mb_substr($nombre !== '' ? $nombre : 'Hoja', 0, 31);
    }

    /**
     * @param  list<int>  $empresaIds
     */
    private function resolverNombreEmpresaFila(array $empresaIds, int $empresaSeccion, bool $consolidar): string
    {
        if (count($empresaIds) === 1) {
            return $this->empresaRepository->find((int) $empresaIds[0])?->nombre ?? '';
        }

        if (! $consolidar && $empresaSeccion > 0) {
            return $this->empresaRepository->find($empresaSeccion)?->nombre ?? '';
        }

        return 'Consolidado';
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<int>  $empresaIds
     * @return list<array<string, mixed>>
     */
    private function enriquecerEnlaces(array $filas, array $empresaIds): array
    {
        if ($filas === []) {
            return $filas;
        }

        // Las filas del reader ERP ya traen asiento_id: solo se resuelve por número
        // el tramo leído de Anita.
        $numerosAsiento = array_values(array_unique(array_filter(array_map(
            fn (array $f) => (int) ($f['asiento_id'] ?? 0) > 0 ? 0 : (int) ($f['nro_asiento'] ?? 0),
            $filas,
        ), fn (int $n) => $n > 0)));

        $codigosCuenta = array_values(array_unique(array_filter(array_map(
            fn (array $f) => (int) ($f['cuenta'] ?? 0),
            $filas,
        ), fn (int $n) => $n > 0)));

        $asientosPorEmpresaNumero = [];
        foreach ($empresaIds as $empresaId) {
            if ($numerosAsiento === []) {
                break;
            }
            $mapa = DB::table('asiento')
                ->where('empresa_id', (int) $empresaId)
                ->whereIn('numeroasiento', $numerosAsiento)
                ->pluck('id', 'numeroasiento')
                ->all();
            foreach ($mapa as $numero => $id) {
                $asientosPorEmpresaNumero[(int) $empresaId.'|'.(int) $numero] = (int) $id;
            }
        }

        $cuentasPorEmpresaCodigo = [];
        foreach ($empresaIds as $empresaId) {
            if ($codigosCuenta === []) {
                break;
            }
            $mapa = DB::table('cuentacontable')
                ->where('empresa_id', (int) $empresaId)
                ->whereIn('codigo', $codigosCuenta)
                ->pluck('id', 'codigo')
                ->all();
            foreach ($mapa as $codigo => $id) {
                $cuentasPorEmpresaCodigo[(int) $empresaId.'|'.(int) $codigo] = (int) $id;
            }
        }

        foreach ($filas as $idx => $fila) {
            $tipo = $fila['tipo_fila'] ?? 'detalle';
            $empresaId = (int) ($fila['empresa_id'] ?? ($empresaIds[0] ?? 0));
            $codigo = (int) ($fila['cuenta'] ?? 0);

            if ($tipo !== 'detalle') {
                if ($codigo > 0 && $empresaId > 0) {
                    $filas[$idx]['cuentacontable_id'] = (int) ($cuentasPorEmpresaCodigo[$empresaId.'|'.$codigo] ?? 0);
                }

                continue;
            }

            $nro = (int) ($fila['nro_asiento'] ?? 0);
            $asientoIdErp = (int) ($fila['asiento_id'] ?? 0);
            if ($asientoIdErp <= 0) {
                $asientoIdErp = ($empresaId > 0 && $nro > 0)
                    ? (int) ($asientosPorEmpresaNumero[$empresaId.'|'.$nro] ?? 0)
                    : 0;
            }
            $filas[$idx]['asiento_id'] = $asientoIdErp;
            $filas[$idx]['cuentacontable_id'] = ($empresaId > 0 && $codigo > 0)
                ? (int) ($cuentasPorEmpresaCodigo[$empresaId.'|'.$codigo] ?? 0)
                : 0;
        }

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $resumen
     * @param  list<int>  $empresaIds
     * @return list<array<string, mixed>>
     */
    private function enriquecerResumenCuentas(array $resumen, array $empresaIds): array
    {
        if ($resumen === []) {
            return $resumen;
        }

        $codigosCuenta = array_values(array_unique(array_filter(array_map(
            fn (array $row) => (int) ($row['cuenta'] ?? 0),
            $resumen,
        ), fn (int $c) => $c > 0)));

        $cuentasPorEmpresaCodigo = [];
        foreach ($empresaIds as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0 || $codigosCuenta === []) {
                continue;
            }

            $mapa = DB::table('cuentacontable')
                ->where('empresa_id', $empresaId)
                ->whereIn('codigo', $codigosCuenta)
                ->pluck('id', 'codigo')
                ->all();

            foreach ($mapa as $codigo => $id) {
                $cuentasPorEmpresaCodigo[(int) $empresaId.'|'.(int) $codigo] = (int) $id;
            }
        }

        $empresaRefId = (int) ($empresaIds[0] ?? 0);

        foreach ($resumen as $idx => $row) {
            $codigo = (int) ($row['cuenta'] ?? 0);
            $resumen[$idx]['cuentacontable_id'] = ($empresaRefId > 0 && $codigo > 0)
                ? (int) ($cuentasPorEmpresaCodigo[$empresaRefId.'|'.$codigo] ?? 0)
                : 0;
        }

        return $resumen;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public function paginarFilas(array $filas, int $perPage): LengthAwarePaginator
    {
        $perPage = max(10, min(200, $perPage));
        $coleccion = collect($filas);
        $currentPage = Paginator::resolveCurrentPage();
        $items = $coleccion->slice(($currentPage - 1) * $perPage, $perPage)->values();

        return new PaginatorImpl(
            $items,
            $coleccion->count(),
            $perPage,
            $currentPage,
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{0: int, 1: int}
     */
    private function resolverRangoYmd(array $filtros): array
    {
        if (($filtros['modo_periodo'] ?? 'mes') === 'mes') {
            $mes = (int) ($filtros['mes'] ?? 0);
            $anio = (int) ($filtros['anio'] ?? 0);
            $desde = Carbon::createFromDate($anio, $mes, 1)->startOfDay();
            $hasta = $desde->copy()->endOfMonth();

            return [(int) $desde->format('Ymd'), (int) $hasta->format('Ymd')];
        }

        [$desdeStr, $hastaStr] = MayorConceptoListadoFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );

        return [
            (int) Carbon::parse($desdeStr)->format('Ymd'),
            (int) Carbon::parse($hastaStr)->format('Ymd'),
        ];
    }
}
