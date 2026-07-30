<?php

declare(strict_types=1);

namespace App\Services\Contable\Suss;

use App\Models\Configuracion\Empresa;
use App\Repositories\Contable\Suss_Presentacion_ConfigRepositoryInterface;
use App\Support\Contable\Suss\SussFormatoF2004Support;
use App\Support\Contable\Suss\SussListadoFiltros;
use Illuminate\Support\Facades\Cache;

final class SussReporteService
{
    private const CACHE_TTL = 2700;

    public function __construct(
        private readonly Suss_Presentacion_ConfigRepositoryInterface $configRepository,
        private readonly SussRetencionesDatosService $retencionesDatosService,
        private readonly SussConciliacionContableService $conciliacionService,
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
        if (! SussListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return null;
        }
        $pack = Cache::get(SussListadoFiltros::claveCacheResultado($filtros));
        if (! is_array($pack) || ($pack['firma'] ?? '') !== SussListadoFiltros::firma($filtros)) {
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
        if (! SussListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return;
        }
        if ((int) ($resultado['totales']['registros'] ?? 0) <= 0 && empty($resultado['mensaje_config'])) {
            return;
        }
        Cache::put(
            SussListadoFiltros::claveCacheResultado($filtros),
            [
                'firma' => SussListadoFiltros::firma($filtros),
                'registros' => $resultado['registros'] ?? [],
                'totales' => $resultado['totales'] ?? [],
                'conciliacion' => $resultado['conciliacion'] ?? [],
                'nombre_archivo' => $resultado['nombre_archivo'] ?? '',
                'mensaje_config' => $resultado['mensaje_config'] ?? null,
                'cuit_agente' => $resultado['cuit_agente'] ?? '',
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
        $fechaDesde = (string) ($filtros['fecha_desde'] ?? '');
        $fechaHasta = (string) ($filtros['fecha_hasta'] ?? '');

        $config = $this->configRepository->findActivo();
        if ($config === null) {
            return [
                'registros' => [],
                'totales' => ['registros' => 0, 'importe' => 0.0, 'base_calculo' => 0.0],
                'conciliacion' => ['habilitada' => false, 'items' => []],
                'archivo_f2004' => '',
                'nombre_archivo' => 'F2004.txt',
                'cuit_agente' => '',
                'mensaje_config' => 'No hay configuración activa de SUSS. Cargue Configuración desde el botón de esta pantalla.',
                'desde_cache' => false,
            ];
        }

        $registros = $this->retencionesDatosService->generar($empresaId, $fechaDesde, $fechaHasta, $config);

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

        $empresa = Empresa::query()->find($empresaId);
        $cuitAgente = SussFormatoF2004Support::normalizarCuit((string) ($empresa?->nroinscripcion ?? ''));
        $periodo = (string) ($filtros['periodo'] ?? date('Ym'));
        $archivo = SussFormatoF2004Support::generarArchivo($registros, $cuitAgente);
        $nombre = SussFormatoF2004Support::nombreArchivo($cuitAgente, $periodo);

        return [
            'registros' => $registros,
            'totales' => $totales,
            'config' => $config,
            'conciliacion' => $conciliacion,
            'archivo_f2004' => $archivo,
            'nombre_archivo' => $nombre,
            'cuit_agente' => $cuitAgente,
            'mensaje_config' => null,
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
        $cuitAgente = (string) ($pack['cuit_agente'] ?? '');
        if ($cuitAgente === '') {
            $empresa = Empresa::query()->find((int) ($filtros['empresa_id'] ?? 0));
            $cuitAgente = SussFormatoF2004Support::normalizarCuit((string) ($empresa?->nroinscripcion ?? ''));
        }

        return [
            'registros' => $registros,
            'totales' => $pack['totales'] ?? [],
            'config' => null,
            'conciliacion' => $pack['conciliacion'] ?? [],
            'archivo_f2004' => SussFormatoF2004Support::generarArchivo($registros, $cuitAgente),
            'nombre_archivo' => (string) ($pack['nombre_archivo'] ?? 'F2004.txt'),
            'cuit_agente' => $cuitAgente,
            'mensaje_config' => $pack['mensaje_config'] ?? null,
            'desde_cache' => true,
        ];
    }
}
