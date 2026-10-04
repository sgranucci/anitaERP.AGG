<?php

declare(strict_types=1);

namespace App\Exports\Finanzas;

use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Finanzas\FinanzaMovimientoPrecargaListadoFiltros;
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

class FinanzaMovimientoPrecargaListadoExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'L';

    /** @var array<string, mixed> */
    private array $filtros = [];

    private string $subtitulo = '';

    private bool $hayFilaLogos = false;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    private int $filaTituloExcel = 1;

    private int $filaCabecerasExcel = 4;

    private int $filaPrimeraDatosExcel = 5;

    private int $ultimaFilaDatos = 5;

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function parametros(array $filtros, string $subtitulo): self
    {
        $this->filtros = $filtros;
        $this->subtitulo = $subtitulo;

        return $this;
    }

    public function view(): View
    {
        $datas = FinanzaMovimientoPrecargaListadoFiltros::query($this->filtros)->get();
        foreach ($datas as $row) {
            $row->nombreempresa = (string) ($row->empresa->nombre ?? '');
        }
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($datas);
        $this->hayFilaLogos = $this->rutasLogosExcel !== [];

        $filasMeta = 3;
        if (trim($this->subtitulo) !== '') {
            $filasMeta++;
        }
        $offset = $this->hayFilaLogos ? 1 : 0;
        $this->filaTituloExcel = $offset + 1;
        $this->filaCabecerasExcel = $offset + $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
        $this->ultimaFilaDatos = $this->filaCabecerasExcel + max(1, $datas->count());

        return view('exports.finanzas.movimiento_precargaindex', [
            'datas' => $datas,
            'subtitulo' => $this->subtitulo,
            'hayFilaLogos' => $this->hayFilaLogos,
            'totalFilas' => $datas->count(),
        ]);
    }

    public function title(): string
    {
        return 'Precargas';
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'H' => '#,##0.00',
            'I' => '#,##0.0000',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10,
            'B' => 14,
            'C' => 22,
            'D' => 16,
            'E' => 28,
            'F' => 42,
            'G' => 36,
            'H' => 16,
            'I' => 14,
            'J' => 12,
            'K' => 16,
            'L' => 18,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            $this->filaCabecerasExcel => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '17202A'],
                    'size' => 11,
                    'name' => 'Arial',
                ],
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
                    $offsetX = 6;
                    foreach ($this->rutasLogosExcel as $idx => $ruta) {
                        if (! is_string($ruta) || ! is_readable($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing;
                        $drawing->setName('Logo');
                        $drawing->setPath($ruta);
                        $drawing->setResizeProportional(true);
                        $drawing->setHeight(46);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetX + $idx * 160);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }

                $filaTit = $this->filaTituloExcel;
                $sheet->mergeCells('A'.$filaTit.':'.self::COL_ULTIMA.$filaTit);
                $sheet->getRowDimension($filaTit)->setRowHeight(28);
                $sheet->getStyle('A'.$filaTit)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);

                $desde = $this->filaPrimeraDatosExcel;
                $hasta = max($desde, $this->ultimaFilaDatos);
                foreach (['H', 'I'] as $col) {
                    for ($fila = $desde; $fila <= $hasta; $fila++) {
                        $valor = $sheet->getCell($col.$fila)->getValue();
                        if ($valor === null || $valor === '') {
                            continue;
                        }
                        $sheet->setCellValueExplicit($col.$fila, (float) $valor, DataType::TYPE_NUMERIC);
                    }
                    $sheet->getStyle($col.$desde.':'.$col.$hasta)->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
                $sheet->getStyle('H'.$desde.':H'.$hasta)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle('I'.$desde.':I'.$hasta)->getNumberFormat()->setFormatCode('#,##0.0000');
                $cab = $this->filaCabecerasExcel;
                $sheet->getStyle('A'.$cab.':'.self::COL_ULTIMA.$cab)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'size' => 11, 'name' => 'Arial'],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['rgb' => '85C1E9'],
                    ],
                ]);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }
}
