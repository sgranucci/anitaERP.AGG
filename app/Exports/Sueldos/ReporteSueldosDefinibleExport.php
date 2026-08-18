<?php

namespace App\Exports\Sueldos;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReporteSueldosDefinibleExport implements FromView, WithEvents, WithStyles
{
    use Exportable;

    /** @var array<string, mixed> */
    private array $resultado = [];

    private string $titulo = '';

    private string $subtitulo = '';

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaTituloExcel = 1;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    private int $cantidadColumnas = 3;

    /**
     * @param  array<string, mixed>  $resultado
     */
    public function parametros(array $resultado, string $titulo, string $subtitulo = '', ?Collection $filasLogo = null): self
    {
        $this->resultado = $resultado;
        $this->titulo = $titulo;
        $this->subtitulo = $subtitulo;
        $this->cantidadColumnas = 2 + count($resultado['columnas'] ?? []);
        $coleccion = $filasLogo ?? collect([(object) ['nombreempresa' => config('app.empresa')]]);
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccion);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $filasMeta = 2 + ($this->subtitulo !== '' ? 1 : 0);
        $offset = $this->hayFilaLogos ? 1 : 0;
        $this->filaTituloExcel = $offset + 1;
        $this->filaCabecerasExcel = $offset + $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return $this;
    }

    public function view(): View
    {
        return view('exports.sueldos.reporte_definibleindex', [
            'resultado' => $this->resultado,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colUltima = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(max(1, $this->cantidadColumnas));
                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 10;
                    foreach ($this->rutasLogosExcel as $ruta) {
                        if (! is_file($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing;
                        $drawing->setPath($ruta);
                        $drawing->setHeight(48);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetX);
                        $drawing->setWorksheet($sheet);
                        $offsetX += 90;
                    }
                }
                $sheet->mergeCells('A'.$this->filaTituloExcel.':'.$colUltima.$this->filaTituloExcel);
                $sheet->getStyle('A'.$this->filaTituloExcel)->getFont()->setName('Arial')->setSize(16)->setBold(true);
                $sheet->getStyle('A'.$this->filaTituloExcel)->getFont()->getColor()->setRGB('17202A');
                $sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(28);
                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.$colUltima.$this->filaCabecerasExcel)
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('85C1E9');
                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.$colUltima.$this->filaCabecerasExcel)
                    ->getFont()->setName('Arial')->setSize(11)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
                $sheet->getStyle('A1:'.$colUltima.$sheet->getHighestRow())
                    ->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }
}
