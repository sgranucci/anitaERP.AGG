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
    private const COL_ULTIMA = 'M';

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
     * @param  array<string, mixed>  $resultado
     * @return list<array<string, mixed>>
     */
    public static function aplanarFilas(array $resultado): array
    {
        $filas = [];
        foreach ($resultado['dias'] ?? [] as $dia) {
            $pvs = $dia['puntos_venta'] ?? [];
            if ($pvs === []) {
                $filas[] = self::filaDesdeDia($dia, '—', 0, [
                    'total_cobrado' => 0.0,
                    'total_invitaciones' => 0.0,
                    'total_facturacion' => (float) ($dia['total_rendiciones_facturacion'] ?? 0),
                    'total_notas_credito' => (float) ($dia['total_rendiciones_notas_credito'] ?? 0),
                    'total_ventas_brutas' => (float) ($dia['total_rendiciones_ventas_brutas'] ?? 0),
                    'total_asientos_debe' => (float) ($dia['total_asientos_debe'] ?? 0),
                ], true);
                continue;
            }
            foreach ($pvs as $pv) {
                $codigo = (string) ($pv['pv_codigo'] ?? '');
                $nombre = (string) ($pv['pv_nombre'] ?? '');
                $label = $codigo;
                if ($nombre !== '' && $nombre !== $codigo) {
                    $label .= ' — '.$nombre;
                }
                $filas[] = [
                    'tipo' => 'pv',
                    'fecha_jornada_fmt' => $dia['fecha_jornada_fmt'] ?? '',
                    'estado' => $dia['estado'] ?? '',
                    'pv_label' => $label,
                    'cantidad' => (int) ($pv['cantidad'] ?? 0),
                    'total_cobrado' => (float) ($pv['total_cobrado'] ?? 0),
                    'total_invitaciones' => (float) ($pv['total_invitaciones'] ?? 0),
                    'total_facturacion' => (float) ($pv['total_facturacion'] ?? 0),
                    'total_notas_credito' => (float) ($pv['total_notas_credito'] ?? 0),
                    'total_ventas_brutas' => (float) ($pv['total_ventas_brutas'] ?? 0),
                    'total_asientos_debe' => (float) ($pv['total_asientos_debe'] ?? 0),
                    'total_flash_estac' => null,
                    'diferencia_flash' => null,
                    'diferencia_venta_asientos' => null,
                ];
            }
            $filas[] = self::filaDesdeDia($dia, 'TOTAL JORNADA', (int) ($dia['cantidad_rendiciones'] ?? 0), [
                'total_cobrado' => (float) ($dia['total_rendiciones_cobrado'] ?? 0),
                'total_invitaciones' => (float) ($dia['total_rendiciones_invitaciones'] ?? 0),
                'total_facturacion' => (float) ($dia['total_rendiciones_facturacion'] ?? 0),
                'total_notas_credito' => (float) ($dia['total_rendiciones_notas_credito'] ?? 0),
                'total_ventas_brutas' => (float) ($dia['total_rendiciones_ventas_brutas'] ?? 0),
                'total_asientos_debe' => (float) ($dia['total_asientos_debe'] ?? 0),
            ], true, 'total_dia');
        }

        return $filas;
    }

    /**
     * @param  array<string, mixed>  $dia
     * @param  array<string, float>  $montos
     * @return array<string, mixed>
     */
    private static function filaDesdeDia(
        array $dia,
        string $pvLabel,
        int $cantidad,
        array $montos,
        bool $conFlash,
        string $tipo = 'dia_vacio',
    ): array {
        return [
            'tipo' => $tipo,
            'fecha_jornada_fmt' => $dia['fecha_jornada_fmt'] ?? '',
            'estado' => $dia['estado'] ?? '',
            'pv_label' => $pvLabel,
            'cantidad' => $cantidad,
            'total_cobrado' => $montos['total_cobrado'],
            'total_invitaciones' => $montos['total_invitaciones'],
            'total_facturacion' => $montos['total_facturacion'],
            'total_notas_credito' => $montos['total_notas_credito'],
            'total_ventas_brutas' => $montos['total_ventas_brutas'],
            'total_asientos_debe' => $montos['total_asientos_debe'],
            'total_flash_estac' => $conFlash ? (float) ($dia['total_flash_estac'] ?? 0) : null,
            'diferencia_flash' => $conFlash ? (float) ($dia['diferencia'] ?? 0) : null,
            'diferencia_venta_asientos' => $conFlash ? (float) ($dia['diferencia_venta_total_asientos'] ?? 0) : null,
        ];
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
            'C' => 22,
            'D' => 7,
            'E' => 11,
            'F' => 10,
            'G' => 11,
            'H' => 9,
            'I' => 11,
            'J' => 12,
            'K' => 11,
            'L' => 12,
            'M' => 13,
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
