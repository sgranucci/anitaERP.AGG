<?php

namespace App\Exports\Contable;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ConciliacionBancariaAgingExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'F';

    /** @var array<string, mixed> */
    private array $resultado = [];

    /** @var array<string, mixed> */
    private array $meta = [];

    private bool $hayFilaLogos = false;

    private int $filaInicioMeta = 1;

    private int $filaCabecerasExcel = 5;

    private int $filaPrimeraDatosExcel = 6;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $meta
     */
    public function parametros(array $resultado, array $meta): self
    {
        $this->resultado = $resultado;
        $this->meta = $meta;

        $paraLogos = collect([(object) ['nombreempresa' => (string) ($meta['empresa_nombre'] ?? '')]]);
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($paraLogos);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;

        $filasMeta = 3; // título + generado + subtítulo
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaInicioMeta = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return $this;
    }

    public function view(): View
    {
        return view('exports.contable.conciliacion_bancaria_agingindex', [
            'resultado' => $this->resultado,
            'meta' => $this->meta,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function title(): string
    {
        return 'Aging conciliación';
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_NUMBER,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'F' => '#,##0.00',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 12,
            'C' => 8,
            'D' => 10,
            'E' => 48,
            'F' => 14,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            $this->filaCabecerasExcel => [
                'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'size' => 11, 'name' => 'Arial'],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['rgb' => '85C1E9'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $x = 5;
                    foreach ($this->rutasLogosExcel as $ruta) {
                        if (! is_readable($ruta)) {
                            continue;
                        }
                        $d = new Drawing;
                        $d->setPath($ruta);
                        $d->setHeight(48);
                        $d->setCoordinates('A1');
                        $d->setOffsetX($x);
                        $d->setWorksheet($sheet);
                        $x += 90;
                    }
                }
                foreach (range($this->filaInicioMeta, $this->filaCabecerasExcel - 1) as $fila) {
                    $sheet->mergeCells('A'.$fila.':'.self::COL_ULTIMA.$fila);
                }
                $sheet->getStyle('A'.$this->filaInicioMeta)->getFont()->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getRowDimension($this->filaInicioMeta)->setRowHeight(28);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);

                $filas = $this->resultado['filas'] ?? [];
                $colImporte = 'F';
                foreach (array_values($filas) as $i => $fila) {
                    $excelRow = $this->filaPrimeraDatosExcel + $i;
                    $sheet->setCellValueExplicit(
                        $colImporte.$excelRow,
                        round((float) ($fila['importe'] ?? 0), 2),
                        DataType::TYPE_NUMERIC
                    );
                    $sheet->getStyle($colImporte.$excelRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle($colImporte.$excelRow)->getNumberFormat()->setFormatCode('#,##0.00');
                }
            },
        ];
    }
}
