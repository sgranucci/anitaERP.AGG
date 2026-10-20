<?php

namespace App\Exports\Seguridad;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class VisitasOcCruceReporteExport implements FromView, ShouldAutoSize, WithTitle
{
    use Exportable;

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public function __construct(
        private array $filas,
        private string $titulo,
        private string $subtitulo = '',
    ) {}

    public function view(): View
    {
        return view('exports.seguridad.visitas_oc_cruce_reporte', [
            'filas' => $this->filas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
        ]);
    }

    public function title(): string
    {
        return 'Visitas x OC';
    }
}
