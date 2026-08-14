<?php

namespace App\Exports\Ventas;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

final class GastronomiaInformeGerenteExport implements FromView, WithColumnWidths, WithEvents, WithTitle
{
    use Exportable;

    /** @var array<string, mixed> */
    private array $informe = [];

    private string $tituloReporte = 'Informe gerente gastronomía';

    private string $subtitulo = '';

    private string $empresaNombre = '';

    private bool $esCsv = false;

    private bool $hayFilaLogos = false;

    private int $filaTitulo = 1;

    private string $colUltima = 'H';

    /** @var list<string> */
    private array $rutasLogos = [];

    /**
     * @param  array<string, mixed>  $informe
     */
    public function parametros(
        array $informe,
        string $titulo,
        string $subtitulo,
        string $empresaNombre,
        bool $esCsv = false,
    ): self {
        $this->informe = $informe;
        $this->tituloReporte = $titulo;
        $this->subtitulo = $subtitulo;
        $this->empresaNombre = trim($empresaNombre);
        $this->esCsv = $esCsv;

        return $this;
    }

    public function view(): View
    {
        $coleccionLogo = $this->empresaNombre !== ''
            ? collect([(object) ['nombreempresa' => $this->empresaNombre]])
            : collect();
        $this->rutasLogos = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccionLogo);
        $this->hayFilaLogos = count($this->rutasLogos) > 0;
        $this->filaTitulo = $this->hayFilaLogos ? 2 : 1;

        return view('exports.ventas.gastronomia_informe_gerenteindex', [
            'informe' => $this->informe,
            'titulo' => $this->tituloReporte,
            'subtitulo' => $this->subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'esExcel' => ! $this->esCsv,
        ]);
    }

    public function title(): string
    {
        return 'Informe gerente';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 18,
            'C' => 40,
            'D' => 12,
            'E' => 14,
            'F' => 14,
            'G' => 14,
            'H' => 12,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colUltima = $this->colUltima;

                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 8;
                    foreach ($this->rutasLogos as $ruta) {
                        if (! is_string($ruta) || $ruta === '' || ! is_file($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing;
                        $drawing->setPath($ruta);
                        $drawing->setHeight(48);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetX);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                        $offsetX += 120;
                    }
                }

                $filaTitulo = $this->filaTitulo;
                $sheet->mergeCells('A'.$filaTitulo.':'.$colUltima.$filaTitulo);
                $sheet->getStyle('A'.$filaTitulo)->getFont()->setName('Arial')->setSize(16)->setBold(true);
                $sheet->getStyle('A'.$filaTitulo)->getFont()->getColor()->setRGB('17202A');
                $sheet->getRowDimension($filaTitulo)->setRowHeight(28);

                $filaGenerado = $filaTitulo + 1;
                $sheet->mergeCells('A'.$filaGenerado.':'.$colUltima.$filaGenerado);
                $sheet->getStyle('A'.$filaGenerado)->getFont()->setName('Arial')->setSize(10)->setBold(true);
                $sheet->getStyle('A'.$filaGenerado)->getFont()->getColor()->setRGB('444444');

                if (trim($this->subtitulo) !== '') {
                    $filaSub = $filaGenerado + 1;
                    $sheet->mergeCells('A'.$filaSub.':'.$colUltima.$filaSub);
                    $sheet->getStyle('A'.$filaSub)->getFont()->setName('Arial')->setSize(10)->setBold(true);
                    $sheet->getStyle('A'.$filaSub)->getFont()->getColor()->setRGB('444444');
                    $sheet->getStyle('A'.$filaSub)->getAlignment()->setWrapText(true);
                    $sheet->getRowDimension($filaSub)->setRowHeight(36);
                }

                $highestRow = (int) $sheet->getHighestRow();
                $cabecerasTabla = ['#', 'PV', 'Código', 'SKU', 'Proveedor', 'Turno'];
                for ($row = $filaTitulo; $row <= $highestRow; $row++) {
                    $a = trim((string) $sheet->getCell('A'.$row)->getValue());
                    if (! in_array($a, $cabecerasTabla, true)) {
                        continue;
                    }
                    $sheet->getStyle('A'.$row.':'.$colUltima.$row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('85C1E9');
                    $sheet->getStyle('A'.$row.':'.$colUltima.$row)->getFont()
                        ->setName('Arial')->setSize(11)->setBold(true);
                    $sheet->getStyle('A'.$row.':'.$colUltima.$row)->getFont()->getColor()->setRGB('17202A');
                }

                $sheet->getStyle('A1:'.$colUltima.$highestRow)
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->freezePane('A'.($filaTitulo + 4));
            },
        ];
    }
}
