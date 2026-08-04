<?php

namespace App\Services\Contable;

use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Contable\MayorConcepto\MayorConceptoMonedaConverter;
use App\Support\Contable\MayorConceptoListadoFiltros;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaComprobanteEnricher;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaOrdencompraEnricher;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaProcesador;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaProveedorEnricher;
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
        private readonly MayorPlanoCuentaProveedorEnricher $proveedorEnricher,
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

            if ((float) ($seccion['saldo_inicial'] ?? 0) !== 0.0 || ($seccion['cantidad_lineas'] ?? 0) === 0) {
                $filas[] = [
                    'tipo_fila' => 'saldo_inicial',
                    'cuenta' => $cuenta,
                    'cuenta_codigo' => $seccion['cuenta_codigo'] ?? '',
                    'cuenta_nombre' => $seccion['cuenta_nombre'] ?? '',
                    'saldo_ejercicio' => (float) ($seccion['saldo_ejercicio_inicial'] ?? $seccion['saldo_inicial'] ?? 0),
                    'nombreempresa' => $nombreEmpresa,
                ];
            }

            foreach ($seccion['lineas'] ?? [] as $ln) {
                $filas[] = $ln;
            }

            if ($conTotales && (($seccion['total_debe'] ?? 0) > 0 || ($seccion['total_haber'] ?? 0) > 0)) {
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
        $filas = $this->proveedorEnricher->enriquecer($filas);

        if ($filtros !== []) {
            $filas = MayorPlanoCuentaListadoFiltros::aplicarFiltroTexto(
                $filas,
                (string) ($filtros['filtro_texto'] ?? ''),
            );
        }

        return $filas;
    }

    /**
     * Si hay ordencompra_id (p. ej. desde asiento) pero falta nro_oc, completa el número para el link.
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
            if ($ocId > 0 && (int) ($fila['nro_oc'] ?? 0) <= 0) {
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
            if ($ocId <= 0 || (int) ($fila['nro_oc'] ?? 0) > 0) {
                continue;
            }
            $nro = (int) ($mapa[$ocId] ?? 0);
            if ($nro > 0) {
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

        $numerosAsiento = array_values(array_unique(array_filter(array_map(
            fn (array $f) => (int) ($f['nro_asiento'] ?? 0),
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
            $filas[$idx]['asiento_id'] = ($empresaId > 0 && $nro > 0)
                ? (int) ($asientosPorEmpresaNumero[$empresaId.'|'.$nro] ?? 0)
                : 0;
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
