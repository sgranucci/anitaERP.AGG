<?php

namespace App\Exports\Caja;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class WaitryCierreJornadaExport implements FromView, ShouldAutoSize
{
    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $resumen
     */
    public function __construct(
        private array $filas,
        private array $resumen,
        private string $titulo,
    ) {
    }

    public function view(): View
    {
        return view('caja.waitry_cierre_jornada.listado', [
            'filas' => $this->filas,
            'resumen' => $this->resumen,
            'titulo' => $this->titulo,
            'empresaNombre' => '',
            'payload' => null,
        ]);
    }
}
