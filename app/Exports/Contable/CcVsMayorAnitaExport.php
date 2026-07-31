<?php

declare(strict_types=1);

namespace App\Exports\Contable;

use App\Services\Contable\CcVsMayorAnitaReporteService;
use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CcVsMayorAnitaExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles
{
    use Exportable;

    /** @var array<string, mixed> */
    private array $resultado = [];

    /** @var array<string, mixed> */
    private array $filtros = [];

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 3;

    private int $filaPrimeraDatosExcel = 4;

    /** @var list<string> */
    private array $rutasLogos = [];

    /**
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $filtros
     */
    public function parametros(array $resultado, array $filtros): self
    {
        $this->resultado = $resultado;
        $this->filtros = $filtros;

        return $this;
    }

    public function view(): View
    {
        $filas = collect($this->resultado['filas'] ?? [])->map(static function (array $f) {
            $f['nombreempresa'] = config('app.empresa');

            return (object) $f;
        });
        $this->rutasLogos = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($filas);
        $this->hayFilaLogos = $this->rutasLogos !== [];

        $filasMeta = 3; // título + generado + subtítulo
        $offset = $this->hayFilaLogos ? 1 : 0;
        $this->filaCabecerasExcel = $offset + $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.contable.cc_vs_mayor_anitaindex', [
            'filas' => $this->resultado['filas'] ?? [],
            'resumen' => $this->resultado['resumen'] ?? [],
            'filtros' => $this->filtros,
            'titulo' => CcVsMayorAnitaReporteService::titulo($this->filtros),
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12, 'B' => 28, 'C' => 10, 'D' => 8, 'E' => 10,
            'F' => 12, 'G' => 14, 'H' => 14, 'I' => 14, 'J' => 12,
            'K' => 12, 'L' => 14, 'M' => 40,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'G' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'H' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'I' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'J' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
            'K' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            $this->filaCabecerasExcel => [
                'font' => ['bold' => true, 'name' => 'Arial', 'size' => 11, 'color' => ['rgb' => '17202A']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '85C1E9']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $colUltima = 'M';
                $offset = $this->hayFilaLogos ? 1 : 0;
                $filaTitulo = $offset + 1;

                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $x = 5;
                    foreach ($this->rutasLogos as $ruta) {
                        if (! is_file($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing();
                        $drawing->setPath($ruta);
                        $drawing->setHeight(48);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($x);
                        $drawing->setWorksheet($sheet);
                        $x += 120;
                    }
                }

                foreach (range($filaTitulo, $filaTitulo + 2) as $fila) {
                    $sheet->mergeCells('A'.$fila.':'.$colUltima.$fila);
                }
                $sheet->getStyle('A'.$filaTitulo)->getFont()->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getRowDimension($filaTitulo)->setRowHeight(28);
                $sheet->getStyle('A'.$filaTitulo.':A'.($filaTitulo + 2))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }
}
