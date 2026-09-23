<?php

namespace App\Exports\Caja;

use App\Support\Caja\ChequeDepositoHistorialFiltros;
use App\Support\Caja\ChequeDepositoHistorialSupport;
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
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ChequeDepositoHistorialExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'I';

    /** @var array<string, mixed> */
    private array $filtros = [];

    private bool $flDesdeIndex = false;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaTituloExcel = 1;

    /** @var list<int> */
    private array $filasImporteExcel = [];

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
        $resumen = ['grupos' => [], 'total_cheques' => 0];
        if ($this->flDesdeIndex) {
            $resumen = ChequeDepositoHistorialSupport::resumir($this->filtros);
        }
        $grupos = collect($resumen['grupos'] ?? []);

        $coleccionLogos = $grupos->map(static function (array $f) {
            return (object) ['nombreempresa' => $f['nombreempresa'] ?? $f['empresa'] ?? ''];
        });

        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccionLogos);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;

        $subtitulo = ChequeDepositoHistorialFiltros::subtitulo($this->filtros);
        $filasMeta = 2; // título + generado
        if ($subtitulo !== '') {
            $filasMeta++;
        }
        $filasMeta++; // contador

        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaTituloExcel = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        $this->filasImporteExcel = [];
        $fila = $this->filaPrimeraDatosExcel;
        foreach ($grupos as $_) {
            $this->filasImporteExcel[] = $fila;
            $fila++;
        }

        return view('exports.caja.chequedepositohistorialindex', [
            'grupos' => $grupos,
            'filtros' => $this->filtros,
            'subtitulo' => $subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'totalGrupos' => $grupos->count(),
            'totalCheques' => (int) ($resumen['total_cheques'] ?? $grupos->sum('cantidad')),
        ]);
    }

    public function title(): string
    {
        return 'Historial depósitos';
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'F' => '#,##0.00',
            'G' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12, 'B' => 14, 'C' => 28, 'D' => 22, 'E' => 10,
            'F' => 14, 'G' => 14, 'H' => 14, 'I' => 28,
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
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);

                foreach ($this->filasImporteExcel as $fila) {
                    $cell = 'F'.$fila;
                    $raw = $sheet->getCell($cell)->getValue();
                    if ($raw === null || $raw === '') {
                        continue;
                    }
                    $num = (float) str_replace(',', '.', preg_replace('/[^\d.,\-]/', '', (string) $raw));
                    $sheet->setCellValueExplicit($cell, $num, DataType::TYPE_NUMERIC);
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
            },
        ];
    }
}
