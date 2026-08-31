<?php

namespace App\Exports\Contable;

use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Export\ExcelFormatoNumero;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
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

class CierreRendicionMaquinaVentaListadoExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    private const COL_ULTIMA = 'Q';

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

        return view('contable.cierre_rendicion_maquina.venta_listado_export', [
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
        foreach ($resultado['filas'] ?? [] as $fila) {
            $filas[] = $fila;
        }
        if (! empty($resultado['totales']) && ($resultado['filas'] ?? []) !== []) {
            $filas[] = $resultado['totales'];
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
        // Montos ~14-16 dígitos con separadores; ##### = ancho insuficiente.
        return [
            'A' => 12,  // Fecha
            'B' => 18,  // Maquinas
            'C' => 18,  // Total On Line
            'D' => 18,  // Diferencia
            'E' => 16,  // Ef.+euros en $
            'F' => 16,  // Efectivo
            'G' => 14,  // Tarj. Visa
            'H' => 14,  // Tarj. Master
            'I' => 14,  // MEP
            'J' => 18,  // Total coin
            'K' => 12,  // Euros
            'L' => 12,  // Cot.Euro
            'M' => 16,  // Euros en $
            'N' => 12,  // Dolares
            'O' => 12,  // Cot.Dolar
            'P' => 16,  // Dolares en $
            'Q' => 18,  // Caja trans. $
        ];
    }

    public function columnFormats(): array
    {
        $fmt = '#,##0.00';
        $fmtCot = '#,##0.0000';
        $cols = [];
        foreach (range('B', 'Q') as $col) {
            $cols[$col] = in_array($col, ['L', 'O'], true) ? $fmtCot : $fmt;
        }

        return $cols;
    }

    public function title(): string
    {
        return 'Venta maquinas';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);

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
                        $drawing->setOffsetY(3);
                        $drawing->setWorksheet($sheet);
                        $offsetX += 90;
                    }
                }

                $filaTitulo = $this->filaTituloExcel;
                $sheet->mergeCells('A'.$filaTitulo.':'.self::COL_ULTIMA.$filaTitulo);
                $sheet->getStyle('A'.$filaTitulo)->getFont()->setBold(true)->setSize(16)->setName('Arial');
                $sheet->getStyle('A'.$filaTitulo)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getRowDimension($filaTitulo)->setRowHeight(28);

                $filaMeta = $filaTitulo + 1;
                $sheet->mergeCells('A'.$filaMeta.':'.self::COL_ULTIMA.$filaMeta);
                $sheet->getStyle('A'.$filaMeta)->getFont()->setBold(true)->setSize(10)->setName('Arial');

                // Reaplicar anchos (ShouldAutoSize a veces los pisa).
                foreach ($this->columnWidths() as $col => $width) {
                    $sheet->getColumnDimension($col)->setAutoSize(false);
                    $sheet->getColumnDimension($col)->setWidth($width);
                }
            },
        ];
    }
}
