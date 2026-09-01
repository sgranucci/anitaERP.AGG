<?php

declare(strict_types=1);

namespace App\Exports\Contable;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CanonEntidadesListadoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithStyles, WithTitle
{
    use Exportable;

    /**
     * @param  list<object>  $filasParaLogo
     * @param  array<string, mixed>  $resultado
     */
    public function __construct(
        private array $filasParaLogo,
        private array $resultado,
        private string $titulo,
        private string $subtitulo = '',
        private bool $esCsv = false,
    ) {
    }

    public function view(): View
    {
        return view('exports.contable.canon_entidades', [
            'resultado' => $this->resultado,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
        ]);
    }

    public function title(): string
    {
        return 'Canon entidades';
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        $fmt = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1;

        return [
            'B' => $fmt,
            'C' => $fmt,
            'D' => $fmt,
            'E' => $fmt,
            'F' => $fmt,
            'G' => $fmt,
            'H' => $fmt,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]],
        ];
    }
}
