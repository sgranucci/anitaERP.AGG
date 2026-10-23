<?php

namespace App\Exports\Stock;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Style;

class ContaduriaInventarioHojaExport implements FromView, WithColumnWidths, WithEvents, WithTitle
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function __construct(
        private readonly string $tituloHoja,
        private readonly array $datos,
    ) {}

    public function view(): View
    {
        return view('exports.stock.contaduria_inventario_hoja', [
            'datos' => $this->datos,
        ]);
    }

    public function title(): string
    {
        return $this->tituloHoja;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18,
            'B' => 42,
            'C' => 8,
            'D' => 14,
            'E' => 18,
            'F' => 22,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->mergeCells('A1:F1');
                $sheet->mergeCells('A2:F2');
                $sheet->mergeCells('A3:F3');
                $sheet->getStyle('A1:F1')->getFont()->setBold(true)->setName('Arial')->setSize(14);
                $sheet->getStyle('A2:F3')->getAlignment()->setWrapText(true);
                $sheet->getRowDimension(2)->setRowHeight(32);
                $sheet->getRowDimension(3)->setRowHeight(32);
                $sheet->getStyle('A4:F4')->applyFromArray([
                    'font' => ['bold' => true, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'color' => ['rgb' => '85C1E9'],
                    ],
                ]);
                $sheet->freezePane('A5');

                $fila = 5;
                foreach ($this->datos['filas'] ?? [] as $linea) {
                    $sheet->setCellValueExplicit(
                        'A'.$fila,
                        (string) ($linea['sku'] ?? ''),
                        DataType::TYPE_STRING,
                    );
                    $cantidad = self::cantidad($linea['saldo'] ?? 0);
                    $sheet->setCellValueExplicit(
                        'D'.$fila,
                        self::cantidadEsEntera($cantidad) ? (int) round($cantidad) : $cantidad,
                        DataType::TYPE_NUMERIC,
                    );
                    $sheet->setCellValueExplicit(
                        'E'.$fila,
                        round((float) ($linea['precio'] ?? 0), 2),
                        DataType::TYPE_NUMERIC,
                    );
                    $sheet->setCellValueExplicit(
                        'F'.$fila,
                        round((float) ($linea['valor'] ?? 0), 2),
                        DataType::TYPE_NUMERIC,
                    );
                    $fila++;
                }

                $filaFin = (int) $sheet->getHighestRow();
                if ($filaFin < 5) {
                    return;
                }
                $sheet->setCellValueExplicit(
                    'F'.$filaFin,
                    round((float) ($this->datos['total_valor'] ?? 0), 2),
                    DataType::TYPE_NUMERIC,
                );

                $ultimaDato = max(4, $fila - 1);
                if ($ultimaDato >= 5) {
                    $sheet->duplicateStyle(self::estiloNumero(NumberFormat::FORMAT_TEXT), 'A5:A'.$ultimaDato);
                    $sheet->duplicateStyle(self::estiloNumero(NumberFormat::FORMAT_GENERAL), 'D5:D'.$ultimaDato);
                    $sheet->duplicateStyle(self::estiloNumero('#,##0.00'), 'E5:F'.$ultimaDato);
                }
                $sheet->duplicateStyle(self::estiloNumero('#,##0.00'), 'F'.$filaFin);
                $sheet->getStyle('A'.$filaFin.':F'.$filaFin)->getFont()->setBold(true);
            },
        ];
    }

    private static function estiloNumero(string $formato): Style
    {
        $estilo = new Style();
        $estilo->getNumberFormat()->setFormatCode($formato);

        return $estilo;
    }

    public static function cantidadVisible(mixed $cantidad): string
    {
        $cantidad = self::cantidad($cantidad);
        if (self::cantidadEsEntera($cantidad)) {
            return (string) (int) round($cantidad);
        }

        return rtrim(rtrim(number_format($cantidad, 4, '.', ''), '0'), '.');
    }

    private static function cantidad(mixed $cantidad): float
    {
        $cantidad = round((float) $cantidad, 4);
        if (self::cantidadEsEntera($cantidad)) {
            return (float) (int) round($cantidad);
        }

        return $cantidad;
    }

    private static function cantidadEsEntera(float $cantidad): bool
    {
        return abs($cantidad - round($cantidad)) < 0.0000001;
    }
}
