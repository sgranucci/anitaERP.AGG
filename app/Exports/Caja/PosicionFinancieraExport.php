<?php

namespace App\Exports\Caja;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PosicionFinancieraExport implements FromView, ShouldAutoSize, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    /** @var list<array{etiqueta: string, valor: float}> */
    private array $filas = [];

    private mixed $empresa = null;

    private string $periodoTexto = '';

    private bool $csv = false;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaTituloExcel = 1;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  list<array{etiqueta: string, valor: float}>  $filas
     */
    public function parametros(array $filas, mixed $empresa, string $periodoTexto, bool $csv = false): self
    {
        $this->filas = $filas;
        $this->empresa = $empresa;
        $this->periodoTexto = $periodoTexto;
        $this->csv = $csv;

        return $this;
    }

    public function view(): View
    {
        $coleccion = new Collection($this->filas);
        if ($this->empresa !== null) {
            $coleccion = $coleccion->map(function ($fila) {
                $fila = (object) $fila;
                $fila->nombreempresa = (string) ($this->empresa->nombre ?? '');

                return $fila;
            });
        }

        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccion);
        $this->hayFilaLogos = ! $this->csv && count($this->rutasLogosExcel) > 0;
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $this->filaCabecerasExcel = $this->hayFilaLogos ? 3 : 2;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.caja.posicion_financieraindex', [
            'filas' => $this->filas,
            'empresa' => $this->empresa,
            'periodo_texto' => $this->periodoTexto,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function title(): string
    {
        return 'Posición financiera';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 42,
            'B' => 18,
        ];
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
                $ultimaCol = 'B';

                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 0;
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
                        $offsetX += 120;
                    }
                }

                $sheet->mergeCells('A'.$this->filaTituloExcel.':'.$ultimaCol.$this->filaTituloExcel);
                $sheet->getStyle('A'.$this->filaTituloExcel)->applyFromArray([
                    'font' => ['name' => 'Arial', 'size' => 16, 'bold' => true, 'color' => ['rgb' => '17202A']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(28);

                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.$ultimaCol.$this->filaCabecerasExcel)->applyFromArray([
                    'font' => ['name' => 'Arial', 'size' => 11, 'bold' => true, 'color' => ['rgb' => '17202A']],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '85C1E9'],
                    ],
                ]);

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }
}
