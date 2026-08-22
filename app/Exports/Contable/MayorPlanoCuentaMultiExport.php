<?php

namespace App\Exports\Contable;

use App\Services\Contable\MayorPlanoCuentaReporteService;
use App\Support\Contable\MayorPlanoCuentaListadoFiltros;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Excel del mayor plano con una solapa por cuenta o por centro de costo.
 */
class MayorPlanoCuentaMultiExport implements WithMultipleSheets
{
    use Exportable;

    /** @var array<string, mixed> */
    private array $filtros = [];

    /** @var array<string, mixed>|null */
    private ?array $resultado = null;

    public function __construct(
        private readonly MayorPlanoCuentaReporteService $reporteService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>|null  $resultado
     */
    public function parametros(array $filtros, ?array $resultado = null): self
    {
        $this->filtros = $filtros;
        $this->resultado = $resultado;

        return $this;
    }

    public function sheets(): array
    {
        $resultado = $this->resultado ?? $this->reporteService->generarDesdeFiltros($this->filtros);
        $dimension = MayorPlanoCuentaListadoFiltros::dimensionExcelSolapas($this->filtros) ?? 'cuenta';
        $bloques = $this->reporteService->partirResultadoParaSolapasExcel($resultado, $dimension);

        if ($bloques === []) {
            return [
                (new MayorPlanoCuentaExport($this->reporteService))
                    ->parametros($this->filtros, $resultado),
            ];
        }

        $sheets = [];
        foreach ($bloques as $bloque) {
            $sheets[] = (new MayorPlanoCuentaExport($this->reporteService))
                ->parametros($this->filtros, $bloque['resultado'])
                ->setTitle((string) ($bloque['titulo'] ?? 'Mayor'));
        }

        return $sheets;
    }
}
