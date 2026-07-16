<?php

declare(strict_types=1);

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

class CierreTurnoGastronomiaContableConciliacionExport implements FromView, ShouldAutoSize, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    private const COL_ULTIMA = 'K';

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

        return view('contable.cierre_turno_gastronomia.conciliacion_listado', [
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
        $out = [];
        foreach ($resultado['dias'] ?? [] as $dia) {
            $out[] = [
                'fecha_jornada' => $dia['fecha_jornada_fmt'] ?? '',
                'fecha_flash' => $dia['fecha_flash_fmt'] ?? '',
                'estado' => $dia['estado'] ?? '',
                'cantidad_cierres' => (int) ($dia['cantidad_cierres'] ?? 0),
                'total_facturacion' => (float) ($dia['total_facturacion'] ?? 0),
                'total_flash_ayb' => (float) ($dia['total_flash_ayb'] ?? 0),
                'total_asientos_debe' => (float) ($dia['total_asientos_debe'] ?? 0),
                'total_mayor_neto' => (float) ($dia['total_mayor_neto'] ?? 0),
                'diferencia_flash' => (float) ($dia['diferencia_flash'] ?? 0),
                'diferencia_asientos' => (float) ($dia['diferencia_asientos'] ?? 0),
                'diferencia_mayor' => (float) ($dia['diferencia_mayor'] ?? 0),
            ];
        }

        return $out;
    }

    public function title(): string
    {
        return 'Conciliación gastro';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12,
            'B' => 12,
            'C' => 8,
            'D' => 10,
            'E' => 14,
            'F' => 14,
            'G' => 14,
            'H' => 14,
            'I' => 12,
            'J' => 12,
            'K' => 12,
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
                $col = self::COL_ULTIMA;

                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 5;
                    foreach ($this->rutasLogosExcel as $ruta) {
                        if (! is_file($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing();
                        $drawing->setPath($ruta);
                        $drawing->setHeight(48);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetX);
                        $drawing->setWorksheet($sheet);
                        $offsetX += 90;
                    }
                }

                $sheet->mergeCells('A'.$this->filaTituloExcel.':'.$col.$this->filaTituloExcel);
                $sheet->getStyle('A'.$this->filaTituloExcel)->getFont()->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(28);

                $rangoCab = 'A'.$this->filaCabecerasExcel.':'.$col.$this->filaCabecerasExcel;
                $sheet->getStyle($rangoCab)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('85C1E9');
                $sheet->getStyle($rangoCab)->getFont()->setName('Arial')->setSize(11)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getStyle($rangoCab)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }
}
