<?php

namespace App\Exports\Ventas;

use App\Services\Ventas\GastronomiaDescuentoReporteService;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class GastronomiaDescuentoReporteMultiExport implements WithMultipleSheets
{
    use Exportable;

    /** @var array<string, mixed> */
    private array $filtros = [];

    /** @var array<string, mixed> */
    private array $resultado = [];

    private string $titulo = '';

    private string $subtitulo = '';

    private string $empresaNombre = '';

    public function __construct(
        private readonly GastronomiaDescuentoReporteService $reporteService,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $resultado
     */
    public function parametros(
        array $filtros,
        array $resultado,
        string $titulo,
        string $subtitulo,
        string $empresaNombre,
    ): self {
        $this->filtros = $filtros;
        $this->resultado = $resultado;
        $this->titulo = $titulo;
        $this->subtitulo = $subtitulo;
        $this->empresaNombre = $empresaNombre;

        return $this;
    }

    public function sheets(): array
    {
        $sheets = [];
        $usados = [];
        $periodoTexto = (string) ($this->resultado['periodo_texto'] ?? '');

        foreach ($this->resultado['bloques'] ?? [] as $bloque) {
            $nombreBase = GastronomiaDescuentoReporteExport::sanitizarNombreHoja(
                trim((string) ($bloque['codigo'] ?? '').' '.(string) ($bloque['nombre'] ?? 'Descuento')),
            );
            $nombre = $nombreBase;
            $i = 2;
            while (isset($usados[$nombre])) {
                $suffix = ' ('.$i.')';
                $nombre = mb_substr($nombreBase, 0, max(1, 31 - mb_strlen($suffix))).$suffix;
                $i++;
            }
            $usados[$nombre] = true;

            $export = new GastronomiaDescuentoReporteBloqueExport(
                $bloque,
                $periodoTexto,
                $this->empresaNombre,
            );
            $export->setTitle($nombre);
            $sheets[] = $export;
        }

        $totales = (new GastronomiaDescuentoReporteExport($this->reporteService))
            ->parametros(
                $this->filtros,
                $this->titulo,
                $this->subtitulo,
                $this->empresaNombre,
                true,
                $this->resultado,
            );
        $sheets[] = $totales;

        return $sheets;
    }
}
