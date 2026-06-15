<?php

namespace App\Services\Contable;

use App\Support\Contable\MayorConcepto\MayorConceptoComparacionSupport;

/**
 * Orquesta generación ERP + comparación contra CSV Anita y mayor plano.
 */
final class MayorConceptoComparacionService
{
    public function __construct(
        private readonly MayorConceptoReporteService $reporteService,
        private readonly MayorConceptoComparacionSupport $comparacionSupport,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   resultado_erp: array<string, mixed>,
     *   informe: array<string, mixed>,
     *   archivos: array<string, string>|null
     * }
     */
    public function ejecutar(
        array $filtros,
        ?string $rutaCsvAnita = null,
        float $tolerancia = 0.05,
        ?string $directorioSalida = null,
        ?string $rutaCsvMayorPlano = null,
    ): array {
        $resultado = $this->reporteService->generarDesdeFiltros($filtros);
        $lineasErp = $this->reporteService->aplanarFilas($resultado);
        $csvAnita = $this->comparacionSupport->leerCsvAnita($rutaCsvAnita);
        $mayorPlanoAnita = $this->comparacionSupport->leerCsvMayorPlanoAnita($rutaCsvMayorPlano);

        $informe = $this->comparacionSupport->comparar($lineasErp, $resultado, $csvAnita, $tolerancia, $mayorPlanoAnita);

        $archivos = null;
        if ($directorioSalida !== null && $directorioSalida !== '') {
            $empresaId = (int) ($filtros['empresa_id'] ?? 0);
            $mes = (int) ($filtros['mes'] ?? 0);
            $anio = (int) ($filtros['anio'] ?? 0);
            $prefijo = sprintf(
                'mayor_concepto_diff_e%d_%04d%02d_%s',
                $empresaId,
                $anio,
                $mes,
                date('Ymd_His'),
            );
            $archivos = $this->comparacionSupport->exportarArchivos($informe, $directorioSalida, $prefijo);
        }

        return [
            'resultado_erp' => $resultado,
            'informe' => $informe,
            'archivos' => $archivos,
        ];
    }
}
