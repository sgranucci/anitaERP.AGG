<?php

declare(strict_types=1);

namespace App\Exports\Direccion;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Export genérico desde matriz ya textual (join / snapshot).
 */
final class ConsultaInteligenteMatrizExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithTitle
{
    use Exportable;

    /**
     * @param  list<string>  $columnas
     * @param  array<string, string>  $etiquetas
     * @param  list<array<string, float|string>>  $matriz
     */
    public function __construct(
        private readonly string $titulo,
        private readonly array $columnas,
        private readonly array $etiquetas,
        private readonly array $matriz,
    ) {
    }

    public function view(): View
    {
        return view('exports.direccion.consulta_inteligenteindex', [
            'titulo' => $this->titulo,
            'columnas' => $this->columnas,
            'etiquetas' => $this->etiquetas,
            'matriz' => $this->matriz,
            'reservarFilaLogoExcel' => false,
            'totalFilas' => count($this->matriz),
        ]);
    }

    public function title(): string
    {
        return mb_substr($this->titulo, 0, 31);
    }

    public function columnWidths(): array
    {
        $widths = [];
        $letra = 'A';
        foreach ($this->columnas as $_) {
            $widths[$letra] = 16;
            $letra++;
        }

        return $widths;
    }

    public function columnFormats(): array
    {
        $formats = [];
        $letra = 'A';
        foreach ($this->columnas as $key) {
            $k = mb_strtolower($key);
            if (preg_match('/(monto|importe|total|precio|saldo|debe|haber|neto|iva|costo)/u', $k)) {
                $formats[$letra] = '#,##0.00';
            } elseif ($k === 'id' || str_ends_with($k, '_id')) {
                $formats[$letra] = NumberFormat::FORMAT_TEXT;
            }
            $letra++;
        }

        return $formats;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colUltima = $this->columnaExcel(count($this->columnas));
                $sheet->mergeCells("A1:{$colUltima}1");
                $sheet->mergeCells("A2:{$colUltima}2");
                $sheet->getStyle('A1')->getFont()->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getStyle('A2')->getFont()->setName('Arial')->setSize(10)->setBold(true)->getColor()->setRGB('444444');
                $rangoCab = "A3:{$colUltima}3";
                $sheet->getStyle($rangoCab)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('85C1E9');
                $sheet->getStyle($rangoCab)->getFont()->setName('Arial')->setSize(11)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getStyle($rangoCab)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->freezePane('A4');
            },
        ];
    }

    private function columnaExcel(int $indice1): string
    {
        $n = max(1, $indice1);
        $s = '';
        while ($n > 0) {
            $n--;
            $s = chr(65 + ($n % 26)).$s;
            $n = intdiv($n, 26);
        }

        return $s;
    }
}
