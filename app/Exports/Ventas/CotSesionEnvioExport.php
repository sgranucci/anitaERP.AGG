<?php

namespace App\Exports\Ventas;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class CotSesionEnvioExport implements FromView, ShouldAutoSize, WithColumnWidths, WithEvents
{
    use Exportable;

    private const COL_ULTIMA = 'K';

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 4;

    private int $filaPrimeraDatosExcel = 5;

    private int $filaTituloExcel = 1;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /** @param  Collection<int, mixed>|array<int, mixed>  $filas */
    public function __construct(
        private Collection|array $filas,
        private string $titulo,
        private string $subtitulo,
    ) {}

    public function view(): View
    {
        $coleccion = collect($this->filas);
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccion);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;

        $filasMeta = 2; // título + generado
        if (trim($this->subtitulo) !== '') {
            $filasMeta++;
        }
        $filasMeta++; // contador registros

        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaTituloExcel = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.ventas.cot_historicoindex', [
            'filas' => $this->filas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 6,
            'C' => 8,
            'D' => 12,
            'E' => 12,
            'F' => 18,
            'G' => 28,
            'H' => 16,
            'I' => 22,
            'J' => 8,
            'K' => 36,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
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
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                        $offsetX += 130;
                    }
                }

                for ($f = $this->filaTituloExcel; $f < $this->filaCabecerasExcel; $f++) {
                    $sheet->mergeCells('A'.$f.':'.$colUltima.$f);
                }

                $sheet->getStyle('A'.$this->filaTituloExcel)->getFont()
                    ->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(28);

                for ($f = $this->filaTituloExcel + 1; $f < $this->filaCabecerasExcel; $f++) {
                    $sheet->getStyle('A'.$f)->getFont()
                        ->setName('Arial')->setSize(10)->setBold(true)->getColor()->setRGB('444444');
                    $sheet->getStyle('A'.$f)->getAlignment()->setWrapText(true);
                }

                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.$colUltima.$this->filaCabecerasExcel)
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('85C1E9');
                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.$colUltima.$this->filaCabecerasExcel)
                    ->getFont()->setName('Arial')->setSize(11)->setBold(true)->getColor()->setRGB('17202A');

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }
}
