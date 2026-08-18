<?php

namespace App\Exports\Sueldos;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReporteSueldosDefinibleParidadExport implements FromView, WithColumnWidths, WithEvents, WithStyles
{
    use Exportable;

    /** @var Collection<int, object> */
    private Collection $filas;

    private string $titulo = '';

    private string $subtitulo = '';

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 4;

    private int $filaPrimeraDatosExcel = 5;

    /** @var list<string> */
    private array $rutasLogos = [];

    /**
     * @param  Collection<int, object>  $filas
     */
    public function parametros(Collection $filas, string $titulo, string $subtitulo = ''): self
    {
        $this->filas = $filas;
        $this->titulo = $titulo;
        $this->subtitulo = $subtitulo;
        $this->rutasLogos = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(
            collect([(object) ['nombreempresa' => config('app.empresa')]])
        );
        $this->hayFilaLogos = $this->rutasLogos !== [];
        $offset = $this->hayFilaLogos ? 1 : 0;
        $this->filaCabecerasExcel = $offset + 3;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return $this;
    }

    public function view(): View
    {
        return view('exports.sueldos.reporte_definible_paridadindex', [
            'filas' => $this->filas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10, 'B' => 40, 'C' => 16, 'D' => 16, 'E' => 16, 'F' => 14, 'G' => 14,
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
                foreach (range($filaTitulo, $filaTitulo + 1) as $fila) {
                    $sheet->mergeCells('A'.$fila.':G'.$fila);
                }
                $sheet->getStyle('A'.$filaTitulo)->getFont()->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getStyle('A'.$filaTitulo.':A'.($filaTitulo + 1))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }
}
