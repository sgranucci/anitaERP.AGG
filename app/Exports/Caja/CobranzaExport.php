<?php

namespace App\Exports\Caja;

use App\Queries\Caja\CobranzaQueryInterface;
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
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CobranzaExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'J';

    private CobranzaQueryInterface $cobranzaQuery;

    private ?string $busqueda = null;

    private ?string $fechaDesde = null;

    private ?string $fechaHasta = null;

    private string $subtitulo = '';

    private string $origen = '';

    private bool $flDesdeIndex = false;

    private bool $hayFilaLogos = false;

    private int $filasMetaEncabezado = 3;

    private int $filaInicioMeta = 1;

    private int $filaCabecerasExcel = 4;

    private int $filaPrimeraDatosExcel = 5;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(CobranzaQueryInterface $cobranzaQuery)
    {
        $this->cobranzaQuery = $cobranzaQuery;
    }

    public function parametros(?string $busqueda, ?string $fechaDesde, ?string $fechaHasta, string $subtitulo, string $origen = ''): self
    {
        $this->busqueda = $busqueda;
        $this->fechaDesde = $fechaDesde;
        $this->fechaHasta = $fechaHasta;
        $this->subtitulo = $subtitulo;
        $this->origen = $origen;
        $this->flDesdeIndex = true;

        return $this;
    }

    public function view(): View
    {
        $cobranza = $this->flDesdeIndex
            ? $this->cobranzaQuery->leeCobranza($this->busqueda, 0, false, $this->fechaDesde, $this->fechaHasta, null, $this->origen)
            : collect();

        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($cobranza);
        $this->hayFilaLogos = $this->rutasLogosExcel !== [];
        $this->filasMetaEncabezado = trim($this->subtitulo) !== '' ? 3 : 2;
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaInicioMeta = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $this->filasMetaEncabezado + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.caja.cobranzaindex', [
            'cobranza' => $cobranza,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'subtitulo' => $this->subtitulo,
        ]);
    }

    public function columnFormats(): array
    {
        if (! $this->flDesdeIndex) {
            return [];
        }

        $cols = [];
        foreach (range('A', 'H') as $c) {
            $cols[$c] = NumberFormat::FORMAT_TEXT;
        }
        $cols['I'] = '#,##0.00';
        $cols['J'] = '#,##0';

        return $cols;
    }

    public function styles(Worksheet $sheet)
    {
        if (! $this->flDesdeIndex) {
            return [];
        }

        return [
            $this->filaCabecerasExcel => [
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

    public function columnWidths(): array
    {
        return [
            'A' => 10,
            'B' => 22,
            'C' => 14,
            'D' => 14,
            'E' => 32,
            'F' => 36,
            'G' => 14,
            'H' => 10,
            'I' => 16,
            'J' => 14,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                if (! $this->flDesdeIndex) {
                    return;
                }

                $sheet = $event->sheet->getDelegate();
                $colUltima = self::COL_ULTIMA;

                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 5;
                    foreach ($this->rutasLogosExcel as $ruta) {
                        if (! is_file($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing;
                        $drawing->setPath($ruta);
                        $drawing->setHeight(48);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetX);
                        $drawing->setOffsetY(3);
                        $drawing->setWorksheet($sheet);
                        $offsetX += 90;
                    }
                }

                for ($i = 0; $i < $this->filasMetaEncabezado; $i++) {
                    $fila = $this->filaInicioMeta + $i;
                    $sheet->mergeCells('A'.$fila.':'.$colUltima.$fila);
                }

                $sheet->getStyle('A'.$this->filaInicioMeta)->getFont()
                    ->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getRowDimension($this->filaInicioMeta)->setRowHeight(28);

                for ($i = 1; $i < $this->filasMetaEncabezado; $i++) {
                    $fila = $this->filaInicioMeta + $i;
                    $sheet->getStyle('A'.$fila)->getFont()
                        ->setName('Arial')->setSize(10)->setBold(true)->getColor()->setRGB('444444');
                    $sheet->getStyle('A'.$fila)->getAlignment()->setWrapText(true);
                }
                if (trim($this->subtitulo) !== '') {
                    $sheet->getRowDimension($this->filaInicioMeta + 2)->setRowHeight(36);
                }

                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.$colUltima.$this->filaCabecerasExcel)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'size' => 11, 'name' => 'Arial'],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '85C1E9']],
                ]);

                $ultimaFila = (int) $sheet->getHighestRow();
                if ($ultimaFila >= $this->filaPrimeraDatosExcel) {
                    $sheet->getStyle('I'.$this->filaPrimeraDatosExcel.':I'.$ultimaFila)
                        ->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle('J'.$this->filaPrimeraDatosExcel.':J'.$ultimaFila)
                        ->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle('I'.$this->filaPrimeraDatosExcel.':J'.$ultimaFila)
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle('F'.$this->filaPrimeraDatosExcel.':F'.$ultimaFila)
                        ->getAlignment()->setWrapText(true);

                    for ($fila = $this->filaPrimeraDatosExcel; $fila <= $ultimaFila; $fila++) {
                        foreach (['I', 'J'] as $col) {
                            $raw = $sheet->getCell($col.$fila)->getValue();
                            if ($raw === null || $raw === '') {
                                continue;
                            }
                            $sheet->getCell($col.$fila)->setValueExplicit((float) $raw, DataType::TYPE_NUMERIC);
                        }
                    }
                }

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Cobranzas';
    }
}
