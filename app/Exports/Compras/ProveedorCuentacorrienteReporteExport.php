<?php

declare(strict_types=1);

namespace App\Exports\Compras;

use App\Support\Compras\ProveedorCuentacorrienteReporteFiltros;
use App\Support\Configuracion\EmpresaLogoArchivo;
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
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProveedorCuentacorrienteReporteExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'L';

    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filasMeta = 2;

    private bool $listadoCompacto = false;

    private string $colUltima = self::COL_ULTIMA;

    private int $filaTotalGeneralExcel = 0;

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
        $modoDeuda = ($this->filtros['modo'] ?? '') !== ProveedorCuentacorrienteReporteFiltros::MODO_FICHA;
        $this->listadoCompacto = $modoDeuda && ! empty($this->filtros['solo_totales']);
        if ($this->listadoCompacto) {
            $this->filas = array_values(array_filter(
                $this->filas,
                static fn (array $fila): bool => ($fila['tipo'] ?? '') !== 'header_proveedor'
            ));
            $this->colUltima = 'B';
        } elseif (($this->filtros['modo'] ?? '') === ProveedorCuentacorrienteReporteFiltros::MODO_FICHA) {
            $cantSaldo = max(1, count($this->resultado['columnas_saldo'] ?? []));
            $this->colUltima = self::letraColumna(9 + $cantSaldo);
        }

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
        $this->filaTotalGeneralExcel = $this->listadoCompacto
            ? $this->filaPrimeraDatosExcel + count($this->filas)
            : 0;
    }

    public function view(): View
    {
        return view('exports.compras.proveedor_cuentacorriente_reporteindex', [
            'filas' => $this->filas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'resultado' => $this->resultado,
            'filtros' => $this->filtros,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'modoDeuda' => ($this->filtros['modo'] ?? '') !== ProveedorCuentacorrienteReporteFiltros::MODO_FICHA,
            'para_excel' => true,
            'mostrarLinks' => false,
        ]);
    }

    public function columnFormats(): array
    {
        if ($this->listadoCompacto) {
            return [
                'A' => NumberFormat::FORMAT_TEXT,
                'B' => '#,##0.00',
            ];
        }

        $formatos = [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'G' => NumberFormat::FORMAT_TEXT,
        ];
        $hasta = ($this->filtros['modo'] ?? '') === ProveedorCuentacorrienteReporteFiltros::MODO_FICHA
            ? 9 + max(1, count($this->resultado['columnas_saldo'] ?? []))
            : 11;
        for ($indice = 8; $indice <= $hasta; $indice++) {
            $formatos[self::letraColumna($indice)] = '#,##0.00';
        }

        return $formatos;
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
        if ($this->listadoCompacto) {
            return [
                'A' => 42,
                'B' => 18,
            ];
        }

        $anchos = [
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
            'K' => 16,
            'L' => 16,
        ];
        if (($this->filtros['modo'] ?? '') === ProveedorCuentacorrienteReporteFiltros::MODO_FICHA) {
            $hasta = 9 + max(1, count($this->resultado['columnas_saldo'] ?? []));
            for ($indice = 13; $indice <= $hasta; $indice++) {
                $anchos[self::letraColumna($indice)] = 16;
            }
        }

        return $anchos;
    }

    private static function letraColumna(int $indice): string
    {
        $letra = '';
        $n = $indice;
        while ($n > 0) {
            $n--;
            $letra = chr(65 + ($n % 26)).$letra;
            $n = intdiv($n, 26);
        }

        return $letra;
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
                $colUltima = $this->colUltima;

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
                if ($this->listadoCompacto) {
                    foreach ($this->filas as $idx => $fila) {
                        if (($fila['tipo'] ?? '') !== 'total_proveedor') {
                            continue;
                        }
                        $excelRow = $this->filaPrimeraDatosExcel + (int) $idx;
                        $sheet->setCellValueExplicit(
                            'B'.$excelRow,
                            (float) ($fila['saldo_pendiente'] ?? 0),
                            DataType::TYPE_NUMERIC
                        );
                        $sheet->getStyle('B'.$excelRow)->getNumberFormat()->setFormatCode('#,##0.00');
                        $sheet->getStyle('B'.$excelRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }
                    $totalRow = $this->filaTotalGeneralExcel;
                    $sheet->setCellValueExplicit(
                        'B'.$totalRow,
                        (float) ($this->resultado['totales']['pendiente'] ?? 0),
                        DataType::TYPE_NUMERIC
                    );
                    $sheet->getStyle('A'.$totalRow.':B'.$totalRow)->applyFromArray([
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
                    ]);
                    $sheet->getStyle('B'.$totalRow)->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle('B'.$totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                } else {
                    foreach ($this->filas as $idx => $fila) {
                        if (($fila['tipo'] ?? '') !== 'total_proveedor') {
                            continue;
                        }
                        $excelRow = $this->filaPrimeraDatosExcel + (int) $idx;
                        $sheet->getStyle('A'.$excelRow.':'.$colUltima.$excelRow)->applyFromArray($estiloTotalCorte);
                    }
                }

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }
}
