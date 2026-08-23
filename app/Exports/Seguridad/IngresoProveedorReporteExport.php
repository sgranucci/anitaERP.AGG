<?php

namespace App\Exports\Seguridad;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class IngresoProveedorReporteExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public function __construct(
        private array $filas,
        private string $titulo,
        private string $subtitulo = '',
        private string $modo = 'kpi',
    ) {
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($this->filas);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $this->filaCabecerasExcel = $this->hayFilaLogos ? 3 : 2;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
    }

    public function view(): View
    {
        $vista = match ($this->modo) {
            'planta' => 'exports.seguridad.ingreso_planta_reporte',
            'abono' => 'exports.seguridad.ingreso_abono_reporte',
            default => 'exports.seguridad.ingreso_kpi_reporte',
        };

        return view($vista, [
            'filas' => $this->filas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function columnFormats(): array
    {
        $cols = [];
        foreach ($this->letrasHasta($this->columnaUltima()) as $c) {
            $cols[$c] = NumberFormat::FORMAT_TEXT;
        }

        return $cols;
    }

    private function columnaUltima(): string
    {
        return match ($this->modo) {
            'planta' => 'S',
            'abono' => 'I',
            default => 'AA',
        };
    }

    /** @return list<string> */
    private function letrasHasta(string $ultima): array
    {
        $out = [];
        $c = 'A';
        while (true) {
            $out[] = $c;
            if ($c === $ultima) {
                break;
            }
            $c++;
        }

        return $out;
    }

    public function styles(Worksheet $sheet)
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
        $ultima = $this->columnaUltima();

        return [
            AfterSheet::class => function (AfterSheet $event) use ($ultima) {
                $sheet = $event->sheet->getDelegate();
                if ($this->hayFilaLogos && count($this->rutasLogosExcel) > 0) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetXp = 6;
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
                        $drawing->setOffsetX($offsetXp + $idx * 160);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }
                $filaTit = $this->filaTituloExcel;
                $sheet->mergeCells('A'.$filaTit.':'.$ultima.$filaTit);
                $sheet->getRowDimension($filaTit)->setRowHeight(28);
                $sheet->getStyle('A'.$filaTit.':'.$ultima.$filaTit)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return match ($this->modo) {
            'planta' => 'Ingresos planta',
            'abono' => 'Abono sin ingresos',
            default => 'Tickets ingresos',
        };
    }
}
