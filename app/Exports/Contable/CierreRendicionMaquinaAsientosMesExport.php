<?php

namespace App\Exports\Contable;

use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Export\ExcelFormatoNumero;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CierreRendicionMaquinaAsientosMesExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
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
        private bool $esCsv = false,
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

        return view('contable.cierre_rendicion_maquina.asientos_mes_listado', [
            'resultado' => $this->resultado,
            'esExcel' => true,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'filas' => self::aplanarFilas($this->resultado),
            'formatoNumero' => $this->formatoNumeroEfectivo(),
        ]);
    }

    private function formatoNumeroEfectivo(): string
    {
        $global = ExcelFormatoNumero::preferenciaGlobal();

        return $this->esCsv ? ExcelFormatoNumero::paraCsv($global) : $global;
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return list<array<string, mixed>>
     */
    public static function aplanarFilas(array $resultado): array
    {
        $filas = [];
        foreach ($resultado['filas'] ?? [] as $asiento) {
            $movimientos = $asiento['movimientos'] ?? [];
            if ($movimientos === []) {
                $filas[] = [
                    'fecha_fmt' => $asiento['fecha_fmt'] ?? '',
                    'jornada_fmt' => $asiento['jornada_fmt'] ?? '',
                    'numero' => $asiento['numero'] ?? '',
                    'tipo' => $asiento['tipo'] ?? '',
                    'origen' => $asiento['origen'] ?? '',
                    'observacion' => $asiento['observacion'] ?? '',
                    'cuenta_codigo' => '',
                    'cuenta_nombre' => '',
                    'centrocosto' => '',
                    'debe' => (float) ($asiento['total_debe'] ?? 0),
                    'haber' => (float) ($asiento['total_haber'] ?? 0),
                    'es_cabecera' => true,
                ];
                continue;
            }

            $primero = true;
            foreach ($movimientos as $mov) {
                $filas[] = [
                    'fecha_fmt' => $primero ? ($asiento['fecha_fmt'] ?? '') : '',
                    'jornada_fmt' => $primero ? ($asiento['jornada_fmt'] ?? '') : '',
                    'numero' => $primero ? ($asiento['numero'] ?? '') : '',
                    'tipo' => $primero ? ($asiento['tipo'] ?? '') : '',
                    'origen' => $primero ? ($asiento['origen'] ?? '') : '',
                    'observacion' => $primero ? ($asiento['observacion'] ?? '') : '',
                    'cuenta_codigo' => $mov['cuenta_codigo'] ?? '',
                    'cuenta_nombre' => $mov['cuenta_nombre'] ?? '',
                    'centrocosto' => $mov['centrocosto'] ?? '',
                    'debe' => (float) ($mov['debe'] ?? 0),
                    'haber' => (float) ($mov['haber'] ?? 0),
                    'es_cabecera' => $primero,
                ];
                $primero = false;
            }
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
            'B' => 12,
            'C' => 10,
            'D' => 10,
            'E' => 12,
            'F' => 36,
            'G' => 12,
            'H' => 28,
            'I' => 16,
            'J' => 14,
            'K' => 14,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        $codigo = ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2);

        return [
            'J' => $codigo,
            'K' => $codigo,
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
                    foreach ($this->rutasLogosExcel as $ruta) {
                        if (! is_string($ruta) || ! is_readable($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing();
                        $drawing->setPath($ruta);
                        $drawing->setHeight(48);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetXp);
                        $drawing->setWorksheet($sheet);
                        $offsetXp += 90;
                    }
                }

                $col = self::COL_ULTIMA;
                $sheet->mergeCells('A'.$this->filaTituloExcel.':'.$col.$this->filaTituloExcel);
                $sheet->getStyle('A'.$this->filaTituloExcel)->getFont()->setName('Arial')->setSize(14)->setBold(true);
                $sheet->getStyle('A'.$this->filaTituloExcel)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $rangoCab = 'A'.$this->filaCabecerasExcel.':'.$col.$this->filaCabecerasExcel;
                $sheet->getStyle($rangoCab)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('85C1E9');
                $sheet->getStyle($rangoCab)->getFont()->setBold(true)->getColor()->setRGB('17202A');
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Asientos cierre máquinas';
    }
}
