<?php

namespace App\Exports\Ventas;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class CotSesionEnvioExport implements FromView, ShouldAutoSize, WithColumnWidths
{
    /** @param  Collection<int, mixed>|array<int, mixed>  $filas */
    public function __construct(
        private Collection|array $filas,
        private string $titulo,
        private string $subtitulo,
    ) {}

    public function view(): View
    {
        return view('exports.ventas.cot_historicoindex', [
            'filas' => $this->filas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
        ]);
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 6,
            'C' => 8,
            'D' => 12,
            'E' => 12,
            'F' => 18,
            'G' => 28,
            'H' => 16,
            'I' => 22,
            'J' => 40,
        ];
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_TEXT,
            'I' => NumberFormat::FORMAT_TEXT,
        ];
    }
}
