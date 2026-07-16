<?php

namespace App\Exports\Contable;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CierreRendicionEstacionamientoConciliacionFlashExport implements FromView, ShouldAutoSize, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    private const COL_ULTIMA = 'J';

    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filaCabecerasExcel = 3;

    private int $filaPrimeraDatosExcel = 4;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  array<string, mixed>  $resultado
     */
    public function __construct(
        private array $resultado,
    ) {
    }

    public function view(): View
    {
        $paraLogos = collect([(object) [
            'nombreempresa' => (string) ($this->resultado['empresa_nombre'] ?? ''),
        ]]);
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($paraLogos);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $this->filaCabecerasExcel = $this->hayFilaLogos ? 4 : 3;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('contable.cierre_rendicion_estacionamiento.conciliacion_flash_listado', [
            'resultado' => $this->resultado,
            'esExcel' => true,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'filas' => self::aplanarFilas($this->resultado),
        ]);
    }

    /**
     * Una fila por jornada (mismo criterio que la conciliacion gastronomia Contable).
     *
     * @param  array<string, mixed>  $resultado
     * @return list<array<string, mixed>>
     */
    public static function aplanarFilas(array $resultado): array
    {
        $filas = [];
        foreach ($resultado['dias'] ?? [] as $dia) {
            $filas[] = [
                'fecha_jornada_fmt' => $dia['fecha_jornada_fmt'] ?? '',
                'estado' => $dia['estado'] ?? '',
                'cantidad_rendiciones' => (int) ($dia['cantidad_rendiciones'] ?? 0),
                'total_facturacion' => (float) ($dia['total_rendiciones_facturacion'] ?? 0),
                'total_notas_credito' => (float) ($dia['total_rendiciones_notas_credito'] ?? 0),
                'total_ventas_brutas' => (float) ($dia['total_rendiciones_ventas_brutas'] ?? 0),
                'total_flash_estac' => (float) ($dia['total_flash_estac'] ?? 0),
                'total_asientos_debe' => (float) ($dia['total_asientos_debe'] ?? 0),
                'diferencia_flash' => (float) ($dia['diferencia'] ?? 0),
                'diferencia_venta_asientos' => (float) ($dia['diferencia_venta_total_asientos'] ?? 0),
            ];
        }

        return $filas;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            $this->filaCabecerasExcel => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '17202A'],
                    'size' => 10,
                    'name' => 'Arial',
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['rgb' => '85C1E9'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 8,
            'C' => 8,
            'D' => 12,
            'E' => 10,
            'F' => 12,
            'G' => 12,
            'H' => 12,
            'I' => 13,
            'J' => 14,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
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
                        $drawing->setDescription('Logo empresa');
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
                $sheet->mergeCells('A'.$filaTit.':'.self::COL_ULTIMA.$filaTit);
                $sheet->getRowDimension($filaTit)->setRowHeight(28);
                $sheet->getStyle('A'.$filaTit.':'.self::COL_ULTIMA.$filaTit)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                        'name' => 'Arial',
                        'color' => ['rgb' => '17202A'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $filaSub = $filaTit + 1;
                $sheet->mergeCells('A'.$filaSub.':'.self::COL_ULTIMA.$filaSub);
                $sheet->getStyle('A'.$filaSub)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 10,
                        'name' => 'Arial',
                        'color' => ['rgb' => '444444'],
                    ],
                ]);

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Conciliacion flash estac.';
    }
}
