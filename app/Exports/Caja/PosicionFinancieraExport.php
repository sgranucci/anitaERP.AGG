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
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PosicionFinancieraExport implements FromView, ShouldAutoSize, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    /** @var list<array{etiqueta: string, valor: float, por_dia?: array<int, float>}> */
    private array $filas = [];

    /** @var list<int> */
    private array $dias = [];

    private mixed $empresa = null;

    private string $periodoTexto = '';

    private bool $csv = false;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 4;

    private int $filaPrimeraDatosExcel = 5;

    private int $filaTituloExcel = 1;

    private int $filasMeta = 3;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  list<array{etiqueta: string, valor: float, por_dia?: array<int, float>}>  $filas
     * @param  list<int>  $dias
     */
    public function parametros(array $filas, array $dias, mixed $empresa, string $periodoTexto, bool $csv = false): self
    {
        $this->filas = $filas;
        $this->dias = $dias;
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
        $this->filasMeta = 3;
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaTituloExcel = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $this->filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.caja.posicion_financieraindex', [
            'filas' => $this->filas,
            'dias' => $this->dias,
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
        $anchos = ['A' => 36];
        $ultima = 1 + count($this->dias) + 1;
        for ($i = 2; $i <= $ultima; $i++) {
            $anchos[$this->colLetra($i)] = 12;
        }

        return $anchos;
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
                $ultimaCol = $this->colLetra(1 + count($this->dias) + 1);

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

                for ($i = 0; $i < $this->filasMeta; $i++) {
                    $filaMeta = $this->filaTituloExcel + $i;
                    $sheet->mergeCells('A'.$filaMeta.':'.$ultimaCol.$filaMeta);
                }

                $sheet->getStyle('A'.$this->filaTituloExcel)->applyFromArray([
                    'font' => ['name' => 'Arial', 'size' => 16, 'bold' => true, 'color' => ['rgb' => '17202A']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(28);

                for ($i = 1; $i < $this->filasMeta; $i++) {
                    $sheet->getStyle('A'.($this->filaTituloExcel + $i))->applyFromArray([
                        'font' => ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => ['rgb' => '444444']],
                    ]);
                }

                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.$ultimaCol.$this->filaCabecerasExcel)->applyFromArray([
                    'font' => ['name' => 'Arial', 'size' => 11, 'bold' => true, 'color' => ['rgb' => '17202A']],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '85C1E9'],
                    ],
                ]);

                $ultimaFila = $sheet->getHighestRow();
                if ($ultimaFila >= $this->filaPrimeraDatosExcel) {
                    $sheet->getStyle('B'.$this->filaPrimeraDatosExcel.':'.$ultimaCol.$ultimaFila)
                        ->getNumberFormat()
                        ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                }

                $sheet->freezePane('B'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    private function colLetra(int $index): string
    {
        $letra = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letra = chr(65 + $mod).$letra;
            $index = intdiv($index - 1, 26);
        }

        return $letra;
    }
}
