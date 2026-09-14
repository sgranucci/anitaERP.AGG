<?php

namespace App\Exports\Caja;

use App\Support\Caja\ChequeDepositoConciliacionSupport;
use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ChequeDepositoConciliacionExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'L';

    /** @var array<string, mixed> */
    private array $filtros = [];

    private bool $flDesdeIndex = false;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaTituloExcel = 1;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function parametros(array $filtros): self
    {
        $this->filtros = $filtros;
        $this->flDesdeIndex = true;

        return $this;
    }

    public function view(): View
    {
        $filas = collect();
        if ($this->flDesdeIndex) {
            $resumen = ChequeDepositoConciliacionSupport::resumir($this->filtros);
            $filas = collect($resumen['filas'] ?? []);
        }

        $coleccionLogos = $filas->map(static function (array $f) {
            return (object) ['nombreempresa' => $f['nombreempresa'] ?? $f['empresa'] ?? ''];
        });

        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccionLogos);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $this->filaCabecerasExcel = $this->hayFilaLogos ? 3 : 2;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.caja.chequedepositoconciliacionindex', [
            'filas' => $filas,
            'filtros' => $this->filtros,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function title(): string
    {
        return 'Conciliación depósito';
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'H' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8, 'B' => 14, 'C' => 10, 'D' => 12, 'E' => 12, 'F' => 12,
            'G' => 12, 'H' => 8, 'I' => 18, 'J' => 22, 'K' => 18, 'L' => 12,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            $this->filaCabecerasExcel => [
                'font' => ['bold' => true, 'color' => ['rgb' => '17202A']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '85C1E9'],
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
                    $col = 'A';
                    foreach ($this->rutasLogosExcel as $ruta) {
                        if (! is_readable($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing();
                        $drawing->setPath($ruta);
                        $drawing->setHeight(36);
                        $drawing->setCoordinates($col.'1');
                        $drawing->setWorksheet($sheet);
                        $col++;
                    }
                }
                $tituloFila = $this->filaTituloExcel;
                $sheet->mergeCells('A'.$tituloFila.':'.self::COL_ULTIMA.$tituloFila);
                $sheet->setCellValue('A'.$tituloFila, 'Conciliación depósitos CHT');
                $sheet->getStyle('A'.$tituloFila)->getFont()->setBold(true)->setSize(14);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }
}
