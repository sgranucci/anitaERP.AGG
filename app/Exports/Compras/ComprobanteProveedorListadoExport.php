<?php

namespace App\Exports\Compras;

use App\Repositories\Compras\Comprobante_ProveedorRepositoryInterface;
use App\Support\Compras\ComprobanteProveedorListadoColumnas;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Export\ExcelFormatoNumero;
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

class ComprobanteProveedorListadoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private ?array $filtros = null;

    /** @var list<string> */
    private array $columnasVisibles = [];

    /** @var array<string, string> */
    private array $etiquetas = [];

    private bool $flDesdeIndex = false;

    private bool $esCsv = false;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 3;

    private int $filaPrimeraDatosExcel = 4;

    private int $filaTituloExcel = 1;

    private int $filaSubtituloExcel = 2;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(
        private readonly Comprobante_ProveedorRepositoryInterface $comprobanteRepository,
    ) {}

    public function view(): View
    {
        if ($this->flDesdeIndex) {
            $datas = $this->comprobanteRepository->leeComprobanteProveedor($this->filtros ?? [], false);
            self::enriquecerNombreEmpresa($datas);

            $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($datas);
            $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
            $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
            $this->filaSubtituloExcel = $this->filaTituloExcel + 1;
            $this->filaCabecerasExcel = $this->filaSubtituloExcel + 1;
            $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

            return view('exports.compras.comprobante_proveedorindex', [
                'datas' => $datas,
                'filtros' => $this->filtros ?? [],
                'esExcel' => true,
                'reservarFilaLogoExcel' => $this->hayFilaLogos,
                'formatoNumero' => $this->formatoNumeroEfectivo(),
                'columnasVisibles' => $this->columnasExport(),
                'etiquetasColumnas' => $this->etiquetas,
            ]);
        }

        $this->hayFilaLogos = false;
        $this->filaTituloExcel = 1;
        $this->filaSubtituloExcel = 2;
        $this->filaCabecerasExcel = 3;
        $this->filaPrimeraDatosExcel = 4;
        $this->rutasLogosExcel = [];

        return view('exports.compras.comprobante_proveedorindex', [
            'datas' => collect(),
            'esExcel' => true,
            'reservarFilaLogoExcel' => false,
            'formatoNumero' => $this->formatoNumeroEfectivo(),
        ]);
    }

    public function columnFormats(): array
    {
        if (! $this->flDesdeIndex) {
            return [];
        }

        $formatos = [];
        $catalogo = ComprobanteProveedorListadoColumnas::catalogoActivo();
        foreach ($this->columnasExport() as $indice => $key) {
            $letra = self::letraColumna($indice);
            $type = (string) ($catalogo[$key]['type'] ?? 'texto');
            if ($type === 'decimal') {
                $formatos[$letra] = ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2);
            } elseif (in_array($type, ['entero', 'texto'], true) && in_array($key, ['id', 'cuit', 'numeroasiento', 'numero_ie'], true)) {
                $formatos[$letra] = NumberFormat::FORMAT_TEXT;
            }
        }

        return $formatos;
    }

    public function styles(Worksheet $sheet)
    {
        if (! $this->flDesdeIndex) {
            return [];
        }

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
        if (! $this->flDesdeIndex) {
            return [];
        }

        $anchos = [];
        $catalogo = ComprobanteProveedorListadoColumnas::catalogoActivo();
        foreach ($this->columnasExport() as $indice => $key) {
            $type = (string) ($catalogo[$key]['type'] ?? 'texto');
            $anchos[self::letraColumna($indice)] = match ($key) {
                'id' => 8,
                'proveedor', 'leyenda' => 28,
                'empresa', 'origen', 'modo_carga' => 22,
                default => $type === 'decimal' ? 14 : 16,
            };
        }

        return $anchos;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                if (! $this->flDesdeIndex) {
                    return;
                }

                $sheet = $event->sheet->getDelegate();
                $ult = self::letraColumna(max(0, count($this->columnasExport()) - 1));

                if ($this->hayFilaLogos && count($this->rutasLogosExcel) > 0) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 6;
                    foreach ($this->rutasLogosExcel as $ruta) {
                        if (! is_string($ruta) || ! is_readable($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing();
                        $drawing->setName('Logo');
                        $drawing->setDescription('Logo empresa');
                        $drawing->setPath($ruta);
                        $drawing->setResizeProportional(true);
                        $drawing->setHeight(46);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetX);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                        $offsetX += 160;
                    }
                }

                $filaTit = $this->filaTituloExcel;
                $sheet->mergeCells('A'.$filaTit.':'.$ult.$filaTit);
                $sheet->getRowDimension($filaTit)->setRowHeight(28);
                $sheet->getStyle('A'.$filaTit)->getFont()->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');

                $filaSub = $this->filaSubtituloExcel;
                $sheet->mergeCells('A'.$filaSub.':'.$ult.$filaSub);
                $sheet->getStyle('A'.$filaSub)->getFont()->setName('Arial')->setSize(10)->setBold(true)->getColor()->setRGB('444444');

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Comprobantes proveedor';
    }

    /**
     * @param  array|string|null  $filtros
     */
    /**
     * @param  array|string|null  $filtros
     * @param  list<string>|null  $columnas
     * @param  array<string, string>|null  $etiquetas
     */
    public function parametros($filtros, bool $esCsv = false, ?array $columnas = null, ?array $etiquetas = null): self
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = array_merge(\App\Support\Compras\ComprobanteProveedorListadoFiltros::filtrosVacios(), [
                'valor' => $texto,
                'busqueda' => $texto,
                'empresa_scope' => 'todas',
            ]);
        }

        $this->filtros = is_array($filtros) ? $filtros : [];
        $this->esCsv = $esCsv;
        $this->flDesdeIndex = true;
        $this->columnasVisibles = is_array($columnas) ? array_values($columnas) : [];
        $this->etiquetas = is_array($etiquetas) ? $etiquetas : [];

        return $this;
    }

    /**
     * @return list<string>
     */
    private function columnasExport(): array
    {
        $catalogo = ComprobanteProveedorListadoColumnas::catalogoActivo();
        $columnas = array_values(array_filter(
            $this->columnasVisibles,
            static fn ($key) => isset($catalogo[$key]) && ! empty($catalogo[$key]['export'])
        ));

        return $columnas !== [] ? $columnas : ComprobanteProveedorListadoColumnas::defaultsVisibles();
    }

    private static function letraColumna(int $indice): string
    {
        $n = $indice + 1;
        $s = '';
        while ($n > 0) {
            $m = ($n - 1) % 26;
            $s = chr(65 + $m).$s;
            $n = intdiv($n - 1, 26);
        }

        return $s;
    }

    private function formatoNumeroEfectivo(): string
    {
        $global = ExcelFormatoNumero::preferenciaGlobal();

        return $this->esCsv ? ExcelFormatoNumero::paraCsv($global) : $global;
    }

    /** @param \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection $datas */
    private static function enriquecerNombreEmpresa($datas): void
    {
        foreach ($datas as $row) {
            $row->nombreempresa = $row->empresas->nombre ?? '';
        }
    }
}
