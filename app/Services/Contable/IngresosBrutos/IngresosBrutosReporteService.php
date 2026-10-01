<?php

declare(strict_types=1);

namespace App\Services\Contable\IngresosBrutos;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Provincia;
use App\Repositories\Contable\Iibb_Presentacion_ConfigRepositoryInterface;
use App\Support\Contable\IngresosBrutos\IngresosBrutosFormatoAgipSupport;
use App\Support\Contable\IngresosBrutos\IngresosBrutosFormatoArbaSupport;
use App\Support\Contable\IngresosBrutos\IngresosBrutosListadoFiltros;
use Illuminate\Support\Facades\Cache;

final class IngresosBrutosReporteService
{
    private const CACHE_TTL = 2700;

    public function __construct(
        private readonly Iibb_Presentacion_ConfigRepositoryInterface $configRepository,
        private readonly IngresosBrutosRetencionesDatosService $retencionesDatosService,
        private readonly IngresosBrutosPercepcionesDatosService $percepcionesDatosService,
        private readonly IngresosBrutosConciliacionContableService $conciliacionService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generarOCache(array $filtros): array
    {
        $cached = $this->leerCache($filtros);
        if ($cached !== null) {
            return $this->hidratarResultadoCacheado($cached, $filtros);
        }

        $resultado = $this->generar($filtros);
        $this->guardarCache($filtros, $resultado);

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>|null
     */
    public function leerCache(array $filtros): ?array
    {
        if (! IngresosBrutosListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return null;
        }
        $pack = Cache::get(IngresosBrutosListadoFiltros::claveCacheResultado($filtros));
        if (! is_array($pack) || ($pack['firma'] ?? '') !== IngresosBrutosListadoFiltros::firma($filtros)) {
            return null;
        }
        if (! isset($pack['registros'], $pack['totales'], $pack['conciliacion'])) {
            return null;
        }

        return $pack;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $resultado
     */
    public function guardarCache(array $filtros, array $resultado): void
    {
        if (! IngresosBrutosListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return;
        }
        // No cachear vacío: evita que un fallo puntual (timeout / columnas) deje la UI en 0.
        if ((int) ($resultado['totales']['registros'] ?? 0) <= 0 && empty($resultado['mensaje_config'])) {
            return;
        }
        Cache::put(
            IngresosBrutosListadoFiltros::claveCacheResultado($filtros),
            [
                'firma' => IngresosBrutosListadoFiltros::firma($filtros),
                'registros' => $resultado['registros'] ?? [],
                'totales' => $resultado['totales'] ?? [],
                'conciliacion' => $resultado['conciliacion'] ?? [],
                'nombre_archivo' => $resultado['nombre_archivo'] ?? '',
                'nombre_archivo_nc' => $resultado['nombre_archivo_nc'] ?? '',
                'mensaje_config' => $resultado['mensaje_config'] ?? null,
                'advertencia_datos' => $resultado['advertencia_datos'] ?? null,
            ],
            self::CACHE_TTL,
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generar(array $filtros): array
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '-1');

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $provinciaId = (int) ($filtros['provincia_id'] ?? 0);
        $tipo = (string) ($filtros['tipo'] ?? IngresosBrutosListadoFiltros::TIPO_RETENCIONES);
        $fechaDesde = (string) ($filtros['fecha_desde'] ?? '');
        $fechaHasta = (string) ($filtros['fecha_hasta'] ?? '');

        $tipoConfig = IngresosBrutosListadoFiltros::tipoConfig($tipo);
        $config = $this->configRepository->findActivoPorProvinciaTipo($provinciaId, $tipoConfig);
        if ($config === null) {
            return [
                'registros' => [],
                'totales' => ['registros' => 0, 'importe' => 0.0, 'base_calculo' => 0.0],
                'conciliacion' => ['habilitada' => false, 'items' => []],
                'archivo_arba' => '',
                'archivo_nc' => '',
                'nombre_archivo' => 'iibb.txt',
                'nombre_archivo_nc' => '',
                'mensaje_config' => 'No hay configuración activa para la provincia y tipo seleccionados. Cargue Configuración IIBB.',
                'advertencia_datos' => null,
                'desde_cache' => false,
            ];
        }

        $provincia = $config->provincia ?? Provincia::query()->find($provinciaId);
        if ($provincia === null) {
            return [
                'registros' => [],
                'totales' => ['registros' => 0, 'importe' => 0.0, 'base_calculo' => 0.0],
                'conciliacion' => ['habilitada' => false, 'items' => []],
                'archivo_arba' => '',
                'archivo_nc' => '',
                'nombre_archivo' => 'iibb.txt',
                'nombre_archivo_nc' => '',
                'mensaje_config' => 'Provincia no encontrada.',
                'advertencia_datos' => null,
                'desde_cache' => false,
            ];
        }

        $advertenciaDatos = null;
        if ($tipoConfig === IngresosBrutosListadoFiltros::TIPO_PERCEPCIONES) {
            $registros = $this->percepcionesDatosService->generar($empresaId, $fechaDesde, $fechaHasta, $config, $provincia);
        } else {
            $registros = $this->retencionesDatosService->generar($empresaId, $fechaDesde, $fechaHasta, $config, $provincia);
            $advertenciaDatos = $this->retencionesDatosService->ultimaAdvertencia();
        }

        usort($registros, static function (array $a, array $b): int {
            return strcmp((string) ($a['fecha_retencion'] ?? ''), (string) ($b['fecha_retencion'] ?? ''))
                ?: ((int) ($a['nro_cert'] ?? $a['nro_comp'] ?? 0)) <=> ((int) ($b['nro_cert'] ?? $b['nro_comp'] ?? 0));
        });

        $totales = [
            'registros' => count($registros),
            'importe' => round(array_sum(array_map(static fn (array $r) => (float) ($r['importe'] ?? 0), $registros)), 2),
            'base_calculo' => round(array_sum(array_map(static fn (array $r) => (float) ($r['base_calculo'] ?? 0), $registros)), 2),
        ];

        $conciliacion = $this->conciliacionService->conciliar($filtros, $registros, $config);

        // IIBB vacío + mayor con saldo: síntoma típico de fallo puntual del bridge (retibrmov).
        if (
            $advertenciaDatos === null
            && $totales['registros'] === 0
            && $tipoConfig === IngresosBrutosListadoFiltros::TIPO_RETENCIONES
        ) {
            $totalMayor = 0.0;
            foreach ($conciliacion['items'] ?? [] as $item) {
                $totalMayor += abs((float) ($item['total_mayor'] ?? 0));
            }
            if ($totalMayor > 0.05) {
                $advertenciaDatos = 'Anita (retibrmov) no devolvió retenciones pero el mayor contable tiene movimiento'
                    .' en el período. Reintente la consulta; si sigue vacío, revise el bridge Anita / tabla retibrmov.';
            }
        }

        $esRetencion = IngresosBrutosListadoFiltros::esRetencion($tipo);
        $empresa = Empresa::query()->find($empresaId);
        $cuitAgente = IngresosBrutosFormatoArbaSupport::normalizarCuit((string) ($empresa?->nroinscripcion ?? ''));
        $periodo = (string) ($filtros['periodo'] ?? date('Ym'));
        $archivos = $this->armarArchivos($registros, $tipo, $esRetencion);
        if ($archivos['cantidad_nc'] > 0) {
            $avisoNc = 'Hay '.$archivos['cantidad_nc'].' nota'
                .($archivos['cantidad_nc'] === 1 ? '' : 's').' de crédito. AGIP las importa en un archivo aparte.'
                .' El comprobante de origen no viene en el puente de ventas: se informó el número de la propia nota.'
                .' Si e-ARCIBA lo rechaza, hay que completar la factura aplicada.';
            $advertenciaDatos = $advertenciaDatos === null
                ? $avisoNc
                : $advertenciaDatos.' '.$avisoNc;
        }

        if (IngresosBrutosListadoFiltros::esPresentacionAgip($tipo)) {
            $nombre = IngresosBrutosFormatoAgipSupport::nombreArchivo($cuitAgente, $periodo, $esRetencion);
            $nombreNc = IngresosBrutosFormatoAgipSupport::nombreArchivo($cuitAgente, $periodo, $esRetencion, true);
        } else {
            $actividad = (int) ($config->codigo_actividad_arba
                ?? ($tipoConfig === IngresosBrutosListadoFiltros::TIPO_PERCEPCIONES
                    ? IngresosBrutosFormatoArbaSupport::ACTIVIDAD_PERCEPCIONES
                    : IngresosBrutosFormatoArbaSupport::ACTIVIDAD_RETENCIONES));
            $quincena = IngresosBrutosListadoFiltros::quincenaLote((int) ($filtros['liquidacion'] ?? 0));
            $lote = $this->siguienteLote($empresaId, $periodo, $actividad, $quincena);
            $nombre = IngresosBrutosFormatoArbaSupport::nombreLote($cuitAgente, $periodo, $quincena, $actividad, $lote);
            $nombreNc = '';
        }

        return [
            'registros' => $registros,
            'totales' => $totales,
            'config' => $config,
            'conciliacion' => $conciliacion,
            'archivo_arba' => $archivos['principal'],
            'archivo_nc' => $archivos['notas_credito'],
            'nombre_archivo' => $nombre,
            'nombre_archivo_nc' => $nombreNc,
            'mensaje_config' => null,
            'advertencia_datos' => $advertenciaDatos,
            'desde_cache' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $pack
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function hidratarResultadoCacheado(array $pack, array $filtros): array
    {
        $registros = $pack['registros'] ?? [];
        $tipo = (string) ($filtros['tipo'] ?? IngresosBrutosListadoFiltros::TIPO_RETENCIONES);
        $esRetencion = IngresosBrutosListadoFiltros::esRetencion($tipo);
        $archivos = $this->armarArchivos($registros, $tipo, $esRetencion);

        return [
            'registros' => $registros,
            'totales' => $pack['totales'] ?? [],
            'config' => null,
            'conciliacion' => $pack['conciliacion'] ?? [],
            'archivo_arba' => $archivos['principal'],
            'archivo_nc' => $archivos['notas_credito'],
            'nombre_archivo' => (string) ($pack['nombre_archivo'] ?? 'iibb.txt'),
            'nombre_archivo_nc' => (string) ($pack['nombre_archivo_nc'] ?? ''),
            'mensaje_config' => $pack['mensaje_config'] ?? null,
            'advertencia_datos' => $pack['advertencia_datos'] ?? null,
            'desde_cache' => true,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $registros
     * @return array{principal: string, notas_credito: string, cantidad_nc: int}
     */
    private function armarArchivos(array $registros, string $tipo, bool $esRetencion): array
    {
        if (IngresosBrutosListadoFiltros::esPresentacionAgip($tipo)) {
            return IngresosBrutosFormatoAgipSupport::generar($registros, $esRetencion);
        }

        return [
            'principal' => IngresosBrutosFormatoArbaSupport::generarArchivo(
                $registros,
                IngresosBrutosListadoFiltros::tipoConfig($tipo),
            ),
            'notas_credito' => '',
            'cantidad_nc' => 0,
        ];
    }

    private function siguienteLote(int $empresaId, string $periodo, int $actividad, int $quincena): int
    {
        $key = generaKey(sprintf('iibb_arba_lote_%d_%s_%d_%d', $empresaId, $periodo, $actividad, $quincena));
        $n = (int) Cache::get($key, 0) + 1;
        Cache::forever($key, $n);

        return $n;
    }
}
