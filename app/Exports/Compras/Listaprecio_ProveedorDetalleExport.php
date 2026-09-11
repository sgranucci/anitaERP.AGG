<?php

namespace App\Exports\Compras;

use App\Models\Compras\Listaprecio_Proveedor;
use App\Models\Configuracion\Empresa;
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
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class Listaprecio_ProveedorDetalleExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'F';

    private const FORMAT_PRECIO = '#,##0.0000';

    private bool $hayFilaLogos = false;

    private int $filasMetaEncabezado = 5;

    private int $filaInicioMeta = 1;

    private int $filaCabecerasExcel = 7;

    private int $filaPrimeraDatosExcel = 8;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(private Listaprecio_Proveedor $lista) {}

    public function view(): View
    {
        $letterhead = self::letterhead($this->lista);
        $this->lista->nombreempresa = $letterhead['nombre'];
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(collect([$this->lista]));
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filasMetaEncabezado = 2 + 3 + (self::tieneObservaciones($this->lista) ? 1 : 0);
        $this->filaInicioMeta = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $this->filasMetaEncabezado + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.compras.listaprecio_proveedordetalle', [
            'lista' => $this->lista,
            'letterhead' => $letterhead,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => self::FORMAT_PRECIO,
            'D' => NumberFormat::FORMAT_NUMBER_00,
            'E' => NumberFormat::FORMAT_TEXT,
            'F' => NumberFormat::FORMAT_TEXT,
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
                    'fillType' => Fill::FILL_SOLID,
                    'color' => ['rgb' => '85C1E9'],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18,
            'B' => 48,
            'C' => 14,
            'D' => 12,
            'E' => 22,
            'F' => 16,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $ultimaFila = max($this->filaCabecerasExcel, (int) $sheet->getHighestRow());

                if ($this->hayFilaLogos && count($this->rutasLogosExcel) > 0) {
                    $sheet->getRowDimension(1)->setRowHeight(52);
                    $offsetXp = 6;
                    $saltoXp = 180;
                    foreach ($this->rutasLogosExcel as $idx => $ruta) {
                        if (! is_string($ruta) || ! is_readable($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing;
                        $drawing->setName('Logo');
                        $drawing->setDescription('Logo empresa');
                        $drawing->setPath($ruta);
                        $drawing->setResizeProportional(true);
                        $drawing->setHeight(42);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetXp + $idx * $saltoXp);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }

                $filaTit = $this->filaInicioMeta;
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

                $filaEmpresa = $filaTit + 1;
                $sheet->mergeCells('A'.$filaEmpresa.':'.self::COL_ULTIMA.$filaEmpresa);
                $sheet->getStyle('A'.$filaEmpresa.':'.self::COL_ULTIMA.$filaEmpresa)->applyFromArray([
                    'font' => [
                        'size' => 10,
                        'name' => 'Arial',
                        'color' => ['rgb' => '444444'],
                    ],
                ]);

                $primeraFilaDatosCab = $filaEmpresa + 1;
                $ultimaFilaLabels = $primeraFilaDatosCab + 2;
                for ($fila = $primeraFilaDatosCab; $fila <= $ultimaFilaLabels; $fila++) {
                    foreach (['A', 'C', 'E'] as $colLbl) {
                        $sheet->getStyle($colLbl.$fila)->applyFromArray([
                            'font' => [
                                'bold' => true,
                                'size' => 9,
                                'name' => 'Arial',
                                'color' => ['rgb' => '17202A'],
                            ],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'color' => ['rgb' => 'E8E8E8'],
                            ],
                        ]);
                    }
                }

                $rangoTabla = 'A'.$this->filaCabecerasExcel.':'.self::COL_ULTIMA.$ultimaFila;
                $sheet->getStyle($rangoTabla)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'CCCCCC'],
                        ],
                    ],
                    'font' => [
                        'name' => 'Arial',
                        'size' => 9,
                    ],
                ]);

                if ($ultimaFila >= $this->filaPrimeraDatosExcel) {
                    $sheet->getStyle('C'.$this->filaPrimeraDatosExcel.':D'.$ultimaFila)
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle('B'.$this->filaPrimeraDatosExcel.':B'.$ultimaFila)
                        ->getAlignment()
                        ->setWrapText(true);

                    for ($fila = $this->filaPrimeraDatosExcel; $fila <= $ultimaFila; $fila++) {
                        if (($fila - $this->filaPrimeraDatosExcel) % 2 === 1) {
                            $sheet->getStyle('A'.$fila.':'.self::COL_ULTIMA.$fila)->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'color' => ['rgb' => 'F5F5F5'],
                                ],
                            ]);
                        }
                    }
                }

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);

                $page = $sheet->getPageSetup();
                $page->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
                $page->setPaperSize(PageSetup::PAPERSIZE_LEGAL);
                $page->setFitToPage(true);
                $page->setFitToWidth(1);
                $page->setFitToHeight(0);
                $sheet->getPageMargins()->setTop(0.5);
                $sheet->getPageMargins()->setBottom(0.5);
                $sheet->getPageMargins()->setLeft(0.4);
                $sheet->getPageMargins()->setRight(0.4);
                $sheet->getHeaderFooter()->setOddFooter(
                    '&LLista de precios '.$this->lista->id.'&CPágina &P / &N&R'.date('d/m/Y H:i')
                );
            },
        ];
    }

    public function title(): string
    {
        return 'Precios lista '.$this->lista->id;
    }

    /**
     * @return array{nombre: string, cuit: string, domicilio: string}
     */
    public static function letterhead(Listaprecio_Proveedor $lista): array
    {
        $empresa = optional($lista->proveedores)->empresas;
        $nombre = trim((string) ($empresa->nombre ?? ''));
        $cuit = trim((string) ($empresa->nroinscripcion ?? ''));
        $domicilio = trim((string) ($empresa->domicilio ?? ''));

        if ($nombre === '') {
            $unica = Empresa::query()->orderBy('id')->first();
            if ($unica) {
                $nombre = trim((string) ($unica->nombre ?? ''));
                $cuit = trim((string) ($unica->nroinscripcion ?? ''));
                $domicilio = trim((string) ($unica->domicilio ?? ''));
            }
        }

        if ($nombre === '') {
            $nombre = trim((string) config('app.empresa'));
        }

        return [
            'nombre' => $nombre,
            'cuit' => $cuit,
            'domicilio' => $domicilio,
        ];
    }

    public static function subtitulo(Listaprecio_Proveedor $lista): string
    {
        $letterhead = self::letterhead($lista);
        $partes = array_filter([
            $letterhead['nombre'],
            $letterhead['cuit'] !== '' ? 'CUIT '.$letterhead['cuit'] : '',
            $letterhead['domicilio'],
        ], fn ($p) => $p !== '');

        return implode(' · ', $partes);
    }

    public static function nombreArchivo(Listaprecio_Proveedor $lista, string $extension): string
    {
        $ext = strtolower(ltrim($extension, '.'));

        return 'lista_precio_proveedor_'.$lista->id.'.'.$ext;
    }

    public static function tieneObservaciones(Listaprecio_Proveedor $lista): bool
    {
        return trim((string) ($lista->observaciones ?? '')) !== '';
    }
}
