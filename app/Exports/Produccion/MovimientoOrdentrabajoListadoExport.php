<?php

namespace App\Exports\Produccion;

use App\Services\Produccion\MovimientoOrdentrabajoService;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class MovimientoOrdentrabajoListadoExport implements FromView, WithColumnWidths, WithEvents, WithTitle
{
    use Exportable;

    private MovimientoOrdentrabajoService $service;

    /** @var array<string, mixed> */
    private array $filtros = [];

    /** @var list<string> */
    private array $columnasVisibles = [];

    /** @var array<string, string> */
    private array $etiquetas = [];

    private string $subtitulo = '';

    private int $filaCabecerasExcel = 5;

    private int $filaPrimeraDatosExcel = 6;

    private string $colUltima = 'A';

    public function __construct(MovimientoOrdentrabajoService $service)
    {
        $this->service = $service;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<string>  $columnasVisibles
     * @param  array<string, string>  $etiquetas
     */
    public function parametros(array $filtros, array $columnasVisibles, array $etiquetas, string $subtitulo): self
    {
        $this->filtros = $filtros;
        $this->columnasVisibles = $columnasVisibles;
        $this->etiquetas = $etiquetas;
        $this->subtitulo = $subtitulo;
        $n = max(1, count($columnasVisibles));
        $this->colUltima = Coordinate::stringFromColumnIndex($n);
        $filasMeta = trim($subtitulo) !== '' ? 4 : 3;
        $this->filaCabecerasExcel = $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return $this;
    }

    public function view(): View
    {
        $datas = $this->service->leeListado($this->filtros, false);

        return view('exports.produccion.movimientoordentrabajoindex', [
            'datas' => $datas,
            'columnasVisibles' => $this->columnasVisibles,
            'etiquetasColumnas' => $this->etiquetas,
            'subtitulo' => $this->subtitulo,
            'totalFilas' => $datas->count(),
        ]);
    }

    public function title(): string
    {
        return 'Movimientos de OT';
    }

    public function columnWidths(): array
    {
        $anchos = [];
        foreach ($this->columnasVisibles as $i => $key) {
            $letra = Coordinate::stringFromColumnIndex($i + 1);
            $anchos[$letra] = match ($key) {
                'id', 'codigo', 'estado' => 14,
                'fecha' => 16,
                'empleado', 'tarea' => 28,
                default => 22,
            };
        }

        return $anchos;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $ultima = $this->colUltima;
                $filasMeta = $this->filaCabecerasExcel - 1;
                for ($fila = 1; $fila <= $filasMeta; $fila++) {
                    $sheet->mergeCells('A'.$fila.':'.$ultima.$fila);
                }
                $sheet->getStyle('A1')->getFont()->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getRowDimension(1)->setRowHeight(28);
                if ($filasMeta > 1) {
                    $sheet->getStyle('A2:A'.$filasMeta)->getFont()->setName('Arial')->setSize(10)->setBold(true)->getColor()->setRGB('444444');
                }
                $cab = $this->filaCabecerasExcel;
                $sheet->getStyle('A'.$cab.':'.$ultima.$cab)->applyFromArray([
                    'font' => [
                        'name' => 'Arial',
                        'bold' => true,
                        'color' => ['rgb' => '17202A'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '85C1E9'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }
}
