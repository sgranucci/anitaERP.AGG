<?php

namespace App\Exports\Solicitudpago;

use App\Support\Export\ExcelFormatoNumero;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SolicitudpagoFechaPagoImpagasExport implements FromView, WithColumnFormatting, WithColumnWidths, WithStyles, WithTitle
{
    /**
     * @param  Collection<int, object>  $filas
     * @param  array{registros: int, monto: float}  $totales
     */
    public function __construct(
        private Collection $filas,
        private array $totales,
        private string $subtitulo,
    ) {
    }

    public function view(): View
    {
        return view('exports.solicitudpago.fecha_pago_impagas', [
            'filas' => $this->filas,
            'totales' => $this->totales,
            'subtitulo' => $this->subtitulo,
            'esExcel' => true,
            'formatoNumero' => ExcelFormatoNumero::preferenciaGlobal(),
        ]);
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'J' => NumberFormat::FORMAT_TEXT,
            'L' => NumberFormat::FORMAT_TEXT,
            'M' => ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2),
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 22,
            'B' => 10,
            'C' => 12,
            'D' => 12,
            'E' => 14,
            'F' => 14,
            'G' => 18,
            'H' => 28,
            'I' => 16,
            'J' => 10,
            'K' => 28,
            'L' => 6,
            'M' => 16,
            'N' => 12,
            'O' => 10,
            'P' => 28,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            6 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '17202A'],
                    'size' => 11,
                    'name' => 'Arial',
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['rgb' => '85C1E9'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Impagas por fecha de pago';
    }
}
