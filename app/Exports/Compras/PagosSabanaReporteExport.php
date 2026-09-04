<?php

namespace App\Exports\Compras;

use App\Support\Compras\PagosSabanaColumnasSupport;
use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PagosSabanaReporteExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const FORMAT_IMPORTE = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2;

    private const CLAVES_TEXTO = [
        'proveedor_codigo', 'tip', 'numero_op', 'tipo_medio', 'comprobantes',
        'ch_prop_emi', 'banco', 'ch_terc_ent', 'doc_prop_emit', 'doc_terc_entr',
        'centros_costo', 'ordenes_compra', 'detalle', 'empresa',
    ];

    private bool $hayFilaLogos = false;

    private int $filasMetaEncabezado = 2;

    private int $filaInicioMeta = 1;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<array<string, mixed>>  $columnas
     * @param  array<string, mixed>  $totales
     */
    public function __construct(
        private array $filas,
        private array $columnas,
        private string $titulo,
        private string $subtitulo = '',
        private array $totales = [],
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

        return view('exports.compras.pagos_sabanaindex', [
            'filas' => $this->filas,
            'columnas' => $this->columnas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'totales' => $this->totales,
            'total_lineas' => $this->contarLineasDetalle(),
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'para_excel' => true,
        ]);
    }

    public function columnFormats(): array
    {
        $formatos = [];

        foreach ($this->columnas as $indice => $columna) {
            $letra = Coordinate::stringFromColumnIndex($indice + 1);
            $clave = (string) $columna['clave'];

            $formatos[$letra] = match (true) {
                in_array($clave, self::CLAVES_TEXTO, true) => NumberFormat::FORMAT_TEXT,
                $columna['tipo'] === PagosSabanaColumnasSupport::TIPO_IMPORTE => self::FORMAT_IMPORTE,
                $columna['tipo'] === PagosSabanaColumnasSupport::TIPO_ENTERO => NumberFormat::FORMAT_NUMBER,
                default => NumberFormat::FORMAT_TEXT,
            };
        }

        return $formatos;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            $this->filaCabecerasExcel => [
                'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'size' => 11, 'name' => 'Arial'],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '85C1E9']],
            ],
        ];
    }

    public function columnWidths(): array
    {
        $anchos = [];
        foreach ($this->columnas as $indice => $columna) {
            $letra = Coordinate::stringFromColumnIndex($indice + 1);
            $clave = (string) $columna['clave'];
            $anchos[$letra] = match ($clave) {
                'proveedor_nombre', 'detalle', 'comprobantes', 'centros_costo' => 28,
                'banco', 'ordenes_compra' => 18,
                'total_pago', 'transferencia', 'efectivo', 'ch_propios' => 14,
                default => 12,
            };
        }

        return $anchos;
    }

    public function title(): string
    {
        return 'Pagos';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colUltima = Coordinate::stringFromColumnIndex(max(1, count($this->columnas)));

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

                for ($i = 0; $i < $this->filasMetaEncabezado; $i++) {
                    $fila = $this->filaInicioMeta + $i;
                    $sheet->mergeCells('A'.$fila.':'.$colUltima.$fila);
                }

                $sheet->getStyle('A'.$this->filaInicioMeta)->getFont()
                    ->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getRowDimension($this->filaInicioMeta)->setRowHeight(28);

                for ($i = 1; $i < $this->filasMetaEncabezado; $i++) {
                    $fila = $this->filaInicioMeta + $i;
                    $sheet->getStyle('A'.$fila)->getFont()
                        ->setName('Arial')->setSize(10)->setBold(true)->getColor()->setRGB('444444');
                    $sheet->getStyle('A'.$fila)->getAlignment()->setWrapText(true);
                    if ($i === 2 && trim($this->subtitulo) !== '') {
                        $sheet->getRowDimension($fila)->setRowHeight(42);
                    }
                }

                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.$colUltima.$this->filaCabecerasExcel)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'size' => 11, 'name' => 'Arial'],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '85C1E9']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    private function contarFilasMetaEncabezado(): int
    {
        $filasMeta = 2;
        if (trim($this->subtitulo) !== '') {
            $filasMeta++;
        }
        if ($this->totales !== []) {
            $filasMeta++;
        }

        return $filasMeta;
    }

    private function contarLineasDetalle(): int
    {
        return count(array_filter(
            $this->filas,
            static fn (array $f) => ($f['tipo_fila'] ?? '') !== 'header_empresa'
        ));
    }

    private function coleccionParaLogos(): Collection
    {
        return collect($this->filas)
            ->filter(static fn (array $f) => ($f['tipo_fila'] ?? '') !== 'header_empresa')
            ->map(static fn (array $f) => (object) ['nombreempresa' => $f['nombreempresa'] ?? '']);
    }
}
