<?php

namespace App\Exports\Stock;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PickingPedidoFerliExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithTitle
{
    use Exportable;

    private bool $hayFilaLogos = false;

    private int $filasMetaEncabezado = 2;

    private int $filaInicioMeta = 1;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaSubtituloExcel = 0;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<string>  $lineasMeta
     */
    public function __construct(
        private array $filas,
        private string $titulo = 'PICKING',
        private array $lineasMeta = [],
        private bool $conFoto = true,
    ) {}

    public function view(): View
    {
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($this->coleccionParaLogos());
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filasMetaEncabezado = $this->contarFilasMetaEncabezado();
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaInicioMeta = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $this->filasMetaEncabezado + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
        $this->filaSubtituloExcel = $this->lineasMeta !== []
            ? $this->filaInicioMeta + 2
            : 0;

        $desde = (int) config('consprod.DESDE_MEDIDA');
        $hasta = (int) config('consprod.HASTA_MEDIDA');

        return view('exports.stock.picking_pedido.picking_fragola', [
            'filas' => $this->filas,
            'titulo' => $this->titulo,
            'lineasMeta' => $this->lineasMeta,
            'desdeMedida' => $desde,
            'hastaMedida' => $hasta,
            'conFoto' => $this->conFoto,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'totalColumnas' => $this->totalColumnas(),
        ]);
    }

    public function title(): string
    {
        return 'PICKING';
    }

    public function columnWidths(): array
    {
        $widths = [];
        $col = 'A';
        if ($this->conFoto) {
            $widths[$col] = 14;
            $col++;
        }
        $widths[$col++] = 14; // Linea
        $widths[$col++] = 12; // Art
        $widths[$col++] = 28; // Descripcion
        $widths[$col++] = 22; // Cliente
        $widths[$col++] = 10; // Pedido

        $desde = (int) config('consprod.DESDE_MEDIDA');
        $hasta = (int) config('consprod.HASTA_MEDIDA');
        for ($i = $desde; $i <= $hasta; $i++) {
            $widths[$col] = 5;
            $col++;
        }
        // T, QM, TT, Precio, SITUACION, NUMERO OT, deposito, Observacion, Bultos
        foreach ([6, 6, 6, 10, 18, 18, 12, 28, 10] as $w) {
            $widths[$col] = $w;
            $col++;
        }

        return $widths;
    }

    public function registerEvents(): array
    {
        $conFoto = $this->conFoto;
        $filas = $this->filas;

        return [
            AfterSheet::class => function (AfterSheet $event) use ($conFoto, $filas) {
                /** @var Worksheet $sheet */
                $sheet = $event->sheet->getDelegate();
                $colUltima = Coordinate::stringFromColumnIndex($this->totalColumnas());

                if ($this->hayFilaLogos && $this->rutasLogosExcel !== []) {
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

                $filaFinMeta = $this->filaInicioMeta + $this->filasMetaEncabezado - 1;
                for ($fila = $this->filaInicioMeta; $fila <= $filaFinMeta; $fila++) {
                    $sheet->mergeCells('A'.$fila.':'.$colUltima.$fila);
                }

                $filaTit = $this->filaInicioMeta;
                $sheet->getRowDimension($filaTit)->setRowHeight(28);
                $sheet->getStyle('A'.$filaTit.':'.$colUltima.$filaTit)->applyFromArray([
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

                for ($fila = $filaTit + 1; $fila <= $filaFinMeta; $fila++) {
                    $altura = ($this->filaSubtituloExcel > 0 && $fila === $this->filaSubtituloExcel) ? 22 : 20;
                    $sheet->getRowDimension($fila)->setRowHeight($altura);
                    $sheet->getStyle('A'.$fila.':'.$colUltima.$fila)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'size' => 10,
                            'name' => 'Arial',
                            'color' => ['rgb' => '444444'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_LEFT,
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => true,
                        ],
                    ]);
                }

                $filaCab = $this->filaCabecerasExcel;
                $sheet->getStyle('A'.$filaCab.':'.$colUltima.$filaCab)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('85C1E9');
                $sheet->getStyle('A'.$filaCab.':'.$colUltima.$filaCab)->getFont()
                    ->setName('Arial')->setBold(true)->setSize(11)->getColor()->setRGB('17202A');
                $sheet->getStyle('A'.$filaCab.':'.$colUltima.$filaCab)->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);

                $this->escribirFilasDatos($sheet);

                $colObs = Coordinate::stringFromColumnIndex($this->indiceColumnaObservacion());
                $ultimaFila = max($sheet->getHighestRow(), $this->filaPrimeraDatosExcel);
                $sheet->getStyle($colObs.$this->filaPrimeraDatosExcel.':'.$colObs.$ultimaFila)
                    ->getAlignment()
                    ->setWrapText(true);

                if (! $conFoto) {
                    return;
                }

                $filaDatos = $this->filaPrimeraDatosExcel;
                foreach ($filas as $idx => $fila) {
                    $excelRow = $filaDatos + $idx;
                    $path = $fila['foto_path'] ?? null;
                    if (! is_string($path) || $path === '' || ! is_file($path)) {
                        continue;
                    }
                    $sheet->getRowDimension($excelRow)->setRowHeight(78);
                    try {
                        $drawing = new Drawing;
                        $drawing->setName('foto-'.$excelRow);
                        $drawing->setDescription((string) ($fila['sku'] ?? ''));
                        $drawing->setPath($path);
                        $drawing->setHeight(70);
                        $drawing->setCoordinates('A'.$excelRow);
                        $drawing->setOffsetX(4);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    } catch (\Throwable $e) {
                        // sin foto si el archivo no es imagen válida
                    }
                }
            },
        ];
    }

    public function columnFormats(): array
    {
        $colObs = Coordinate::stringFromColumnIndex($this->indiceColumnaObservacion());
        $colSku = Coordinate::stringFromColumnIndex($this->conFoto ? 3 : 2);
        $colPedido = Coordinate::stringFromColumnIndex($this->conFoto ? 6 : 5);

        return [
            $colSku => NumberFormat::FORMAT_TEXT,
            $colPedido => NumberFormat::FORMAT_TEXT,
            $colObs => NumberFormat::FORMAT_TEXT,
        ];
    }

    private function escribirFilasDatos(Worksheet $sheet): void
    {
        $desde = (int) config('consprod.DESDE_MEDIDA');
        $hasta = (int) config('consprod.HASTA_MEDIDA');

        foreach ($this->filas as $idx => $fila) {
            $excelRow = $this->filaPrimeraDatosExcel + $idx;
            $c = 1;
            if ($this->conFoto) {
                $c++;
            }

            $this->celdaTexto($sheet, $c++, $excelRow, (string) ($fila['nombrelinea'] ?? ''));
            $this->celdaTexto($sheet, $c++, $excelRow, (string) ($fila['sku'] ?? ''));
            $this->celdaTexto($sheet, $c++, $excelRow, (string) ($fila['descripcion'] ?? ''));
            $this->celdaTexto($sheet, $c++, $excelRow, (string) ($fila['cliente'] ?? ''));
            $this->celdaTexto($sheet, $c++, $excelRow, (string) ($fila['pedido_codigo'] ?? ''));

            $medidas = $fila['medidas'] ?? [];
            for ($i = $desde; $i <= $hasta; $i++) {
                $cant = $medidas[(string) $i] ?? ($medidas[$i] ?? null);
                $cell = Coordinate::stringFromColumnIndex($c++).$excelRow;
                if ($cant !== null && (float) $cant != 0.0) {
                    $sheet->setCellValue($cell, (float) $cant);
                } else {
                    $sheet->setCellValueExplicit($cell, '', DataType::TYPE_STRING);
                }
            }

            $t = (float) ($fila['total'] ?? 0);
            $tamModulo = (float) ($fila['cantidadmodulo'] ?? 0);
            $qm = ($tamModulo > 0 && $t > 0) ? round($t / $tamModulo, 2) : 1.0;
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($c++).$excelRow, $t);
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($c++).$excelRow, $qm);
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($c++).$excelRow, $t);
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($c++).$excelRow, (float) ($fila['precio'] ?? 0));
            $this->celdaTexto($sheet, $c++, $excelRow, (string) ($fila['situacion'] ?? 'ENTREGA INMEDIATA'));
            $this->celdaTexto($sheet, $c++, $excelRow, (string) ($fila['numero_ot'] ?? ''));
            $this->celdaTexto($sheet, $c++, $excelRow, (string) ($fila['deposito'] ?? ''));
            $this->celdaTexto($sheet, $c++, $excelRow, trim((string) ($fila['observacion'] ?? '')));
            $this->celdaTexto($sheet, $c++, $excelRow, '');
        }
    }

    private function celdaTexto(Worksheet $sheet, int $columna, int $fila, string $valor): void
    {
        $sheet->setCellValueExplicit(
            Coordinate::stringFromColumnIndex($columna).$fila,
            $valor,
            DataType::TYPE_STRING
        );
    }

    private function totalColumnas(): int
    {
        $desde = (int) config('consprod.DESDE_MEDIDA');
        $hasta = (int) config('consprod.HASTA_MEDIDA');
        $colsMedidas = ($hasta - $desde) + 1;

        // Foto? + Linea Art Desc Cliente Pedido + medidas + T QM TT Precio SITUACION NUMERO OT deposito Observacion Bultos
        return ($this->conFoto ? 1 : 0) + 5 + $colsMedidas + 9;
    }

    private function indiceColumnaObservacion(): int
    {
        return $this->totalColumnas() - 1;
    }

    private function contarFilasMetaEncabezado(): int
    {
        return 2 + count($this->lineasMeta);
    }

    private function coleccionParaLogos(): Collection
    {
        $nombres = collect($this->filas)
            ->map(fn (array $f) => trim((string) ($f['nombreempresa'] ?? '')))
            ->filter();

        if ($nombres->isEmpty()) {
            return collect([(object) ['nombreempresa' => (string) config('app.empresa')]]);
        }

        return $nombres->unique()->values()->map(fn (string $n) => (object) ['nombreempresa' => $n]);
    }
}
