<?php

declare(strict_types=1);

namespace App\Exports\Ventas;

use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Ventas\ClienteCuentacorrienteReporteFiltros;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ClienteCuentacorrienteReporteExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'L';

    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filasMeta = 2;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $filtros
     */
    public function __construct(
        private array $filas,
        private string $titulo,
        private string $subtitulo = '',
        private array $resultado = [],
        private array $filtros = [],
    ) {
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(
            collect($this->filas)->map(fn ($f) => (object) ['nombreempresa' => $f['nombreempresa'] ?? ''])
        );
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;

        $this->filasMeta = 2; // título + generado
        if (trim($this->subtitulo) !== '') {
            $this->filasMeta++;
        }
        $stats = $this->resultado['stats'] ?? [];
        if (! empty($stats)) {
            $this->filasMeta++;
        }

        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaTituloExcel = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $this->filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
    }

    public function view(): View
    {
        return view('exports.ventas.cliente_cuentacorriente_reporteindex', [
            'filas' => $this->filas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'resultado' => $this->resultado,
            'filtros' => $this->filtros,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'modoDeuda' => ($this->filtros['modo'] ?? '') !== ClienteCuentacorrienteReporteFiltros::MODO_FICHA,
            'para_excel' => true,
            'mostrarLinks' => false,
        ]);
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'G' => NumberFormat::FORMAT_TEXT,
            'H' => '#,##0.00',
            'I' => '#,##0.00',
            'J' => '#,##0.00',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            $this->filaCabecerasExcel => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => '17202A'],
                    'size' => 11,
                    'name' => 'Arial',
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'color' => ['rgb' => '85C1E9'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10,
            'B' => 28,
            'C' => 14,
            'D' => 11,
            'E' => 11,
            'F' => 28,
            'G' => 14,
            'H' => 16,
            'I' => 16,
            'J' => 16,
            'K' => 4,
            'L' => 4,
        ];
    }

    public function title(): string
    {
        return 'Cuenta corriente';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colUltima = self::COL_ULTIMA;

                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 0;
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
                        $offsetX += 130;
                    }
                }

                $filaInicioMeta = $this->hayFilaLogos ? 2 : 1;
                for ($i = 0; $i < $this->filasMeta; $i++) {
                    $fila = $filaInicioMeta + $i;
                    $sheet->mergeCells('A'.$fila.':'.$colUltima.$fila);
                    if ($i === 0) {
                        $sheet->getStyle('A'.$fila)->getFont()->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');
                        $sheet->getRowDimension($fila)->setRowHeight(28);
                    } else {
                        $sheet->getStyle('A'.$fila)->getFont()->setName('Arial')->setSize(10)->setBold(true)->getColor()->setRGB('444444');
                        $sheet->getStyle('A'.$fila)->getAlignment()->setWrapText(true);
                        $sheet->getRowDimension($fila)->setRowHeight($i === 1 && $this->subtitulo !== '' ? 42 : 18);
                    }
                    $sheet->getStyle('A'.$fila)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                }

                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.$colUltima.$this->filaCabecerasExcel)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => '17202A'],
                        'size' => 11,
                        'name' => 'Arial',
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['rgb' => '85C1E9'],
                    ],
                ]);

                $estiloTotalCorte = [
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => '1B4F72'],
                        'size' => 11,
                        'name' => 'Arial',
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['rgb' => 'F9E79F'],
                    ],
                ];
                $estiloTotalGeneral = [
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => '1B4F72'],
                        'size' => 11,
                        'name' => 'Arial',
                    ],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'color' => ['rgb' => 'AED6F1'],
                    ],
                ];
                foreach ($this->filas as $idx => $fila) {
                    $tipo = (string) ($fila['tipo'] ?? '');
                    if ($tipo !== 'total_cliente' && $tipo !== 'total_general') {
                        continue;
                    }
                    $excelRow = $this->filaPrimeraDatosExcel + (int) $idx;
                    $sheet->getStyle('A'.$excelRow.':'.$colUltima.$excelRow)->applyFromArray(
                        $tipo === 'total_general' ? $estiloTotalGeneral : $estiloTotalCorte
                    );
                }

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }
}
