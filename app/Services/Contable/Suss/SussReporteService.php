<?php

declare(strict_types=1);

namespace App\Services\Contable\Suss;

use App\Models\Configuracion\Empresa;
use App\Models\Contable\Suss_Empresa_Config;
use App\Repositories\Contable\Suss_Presentacion_ConfigRepositoryInterface;
use App\Support\Contable\Suss\SussFormatoF2004Support;
use App\Support\Contable\Suss\SussLiquidacionTipoSupport;

final class SussReporteService
{
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
        $registros = SussFormatoF2004Support::aplicarRegimenExportacion(
            $registros,
            Suss_Empresa_Config::exportaRegimen748Como754($empresaId),
        );

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
        $sufijo = match ((int) ($filtros['liquidacion'] ?? 0)) {
            SussLiquidacionTipoSupport::LIQUIDACION_1Q => '1Q',
            2 => '2Q',
            default => '',
        };
        $nombre = SussFormatoF2004Support::nombreArchivo($cuitAgente, $periodo, $sufijo);

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
}
