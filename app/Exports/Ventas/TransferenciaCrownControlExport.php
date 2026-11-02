<?php

declare(strict_types=1);

namespace App\Exports\Ventas;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
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

class TransferenciaCrownControlExport implements FromView, WithColumnWidths, WithEvents, WithStyles
{
    use Exportable;

    /** @var array<string, mixed> */
    private array $filtros = [];

    /** @var array<string, mixed> */
    private array $resultado = [];

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 4;

    private int $filaPrimeraDatosExcel = 5;

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $resultado
     */
    public function parametros(array $filtros, array $resultado): self
    {
        $this->filtros = $filtros;
        $this->resultado = $resultado;

        return $this;
    }

    public function view(): View
    {
        $filas = collect($this->resultado['filas'] ?? [])->map(static function (array $fila) {
            $fila['nombreempresa'] = 'Emprendimientos Crown SA';

            return (object) $fila;
        });
        $logos = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($filas);
        $this->hayFilaLogos = $logos !== [];
        $filasMeta = 3; // título + generado + subtítulo
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaCabecerasExcel = $offsetLogo + $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.ventas.transferencia_controlindex', [
            'resultado' => $this->resultado,
            'filtros' => $this->filtros,
            'hayFilaLogos' => $this->hayFilaLogos,
            'subtitulo' => $this->subtitulo(),
        ]);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,
            'B' => 32,
            'C' => 28,
            'D' => 55,
            'E' => 14,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $ultima = 'E';
                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $rutas = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(
                        collect($this->resultado['filas'] ?? [])->map(static fn ($f) => (object) array_merge(
                            is_array($f) ? $f : [],
                            ['nombreempresa' => 'Emprendimientos Crown SA']
                        ))
                    );
                    $x = 5;
                    foreach ($rutas as $ruta) {
                        if (! is_string($ruta) || $ruta === '' || ! is_file($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing();
                        $drawing->setPath($ruta);
                        $drawing->setHeight(48);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($x);
                        $drawing->setWorksheet($sheet);
                        $x += 90;
                    }
                }

                $filaTitulo = $this->hayFilaLogos ? 2 : 1;
                foreach (range($filaTitulo, $this->filaCabecerasExcel - 1) as $fila) {
                    $sheet->mergeCells('A'.$fila.':'.$ultima.$fila);
                }
                $sheet->getStyle('A'.$filaTitulo)->getFont()->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getRowDimension($filaTitulo)->setRowHeight(28);

                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.$ultima.$this->filaCabecerasExcel)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '85C1E9'],
                    ],
                    'font' => [
                        'name' => 'Arial',
                        'size' => 11,
                        'bold' => true,
                        'color' => ['rgb' => '17202A'],
                    ],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    private function subtitulo(): string
    {
        $partes = [];
        $partes[] = 'Desde '.($this->filtros['desde'] ?? '').' hasta '.($this->filtros['hasta'] ?? '');
        $partes[] = 'Alcance: '.($this->filtros['alcance'] ?? 'todo');
        $partes[] = 'Origen: '.($this->filtros['origen'] ?? 'todos');

        return implode(' · ', $partes);
    }
}
