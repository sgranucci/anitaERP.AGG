<?php

namespace App\Exports\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Ventas\TiendanubeStockSubida;
use App\Models\Ventas\TiendanubeStockSubidaLinea;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Ventas\Tiendanube\TiendanubeConfiguracionSupport;
use App\Support\Ventas\Tiendanube\TiendanubeTiendasSupport;
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
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TiendanubeStockSubidaLineaExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'I';

    private ?TiendanubeStockSubida $subida = null;

    private bool $listo = false;

    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filaSubtituloExcel = 2;

    private int $filaCabecerasExcel = 3;

    private int $filaPrimeraDatosExcel = 4;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function deSubida(TiendanubeStockSubida $subida): self
    {
        $this->subida = $subida;
        $this->listo = true;

        return $this;
    }

    public function view(): View
    {
        $pack = $this->listo && $this->subida !== null
            ? self::armar($this->subida)
            : [
                'lineas' => collect(),
                'titulo' => 'Previsualización stock y precios Tiendanube',
                'subtitulo' => '',
                'rutasLogos' => [],
            ];

        $this->rutasLogosExcel = $pack['rutasLogos'];
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
        $this->filaSubtituloExcel = $this->filaTituloExcel + 1;
        $this->filaCabecerasExcel = $this->filaSubtituloExcel + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.ventas.tiendanube_stock_subidaindex', [
            'lineas' => $pack['lineas'],
            'titulo' => $pack['titulo'],
            'subtitulo' => $pack['subtitulo'],
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
        ]);
    }

    /**
     * @return array{lineas: Collection, titulo: string, subtitulo: string, rutasLogos: list<string>, logosCabecera: list<array{nombre: string, uri: string, mime: string}>}
     */
    public static function armar(TiendanubeStockSubida $subida): array
    {
        $lineas = TiendanubeStockSubidaLinea::query()
            ->where('subida_id', $subida->id)
            ->orderBy('id')
            ->get();

        $empresaId = TiendanubeConfiguracionSupport::empresaId($subida->store_id);
        $nombreEmpresa = (string) (Empresa::query()->whereKey($empresaId)->value('nombre') ?? '');
        $marca = collect([(object) ['nombreempresa' => $nombreEmpresa]]);

        return [
            'lineas' => $lineas,
            'titulo' => 'Previsualización stock y precios Tiendanube',
            'subtitulo' => self::subtitulo($subida),
            'rutasLogos' => EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($marca),
            'logosCabecera' => EmpresaLogoArchivo::logosCabeceraDesdeColeccion($marca),
        ];
    }

    public static function subtitulo(TiendanubeStockSubida $subida): string
    {
        $cuando = optional($subida->inicio_at)->format('d/m/Y H:i');
        $etiquetaOk = $subida->origen === TiendanubeStockSubida::ORIGEN_SIMULACION ? 'A enviar' : 'OK';

        return implode(' · ', array_filter([
            TiendanubeTiendasSupport::nombre($subida->store_id),
            TiendanubeStockSubida::etiquetaOrigen($subida->origen),
            $cuando !== '' ? $cuando : null,
            'Marketplace '.$subida->marketplace_codigo,
            $etiquetaOk.' '.(int) $subida->variantes_ok,
            'Error '.(int) $subida->variantes_error,
            'Omitidas '.(int) $subida->variantes_omitidas,
            trim((string) $subida->mensaje) !== '' ? trim((string) $subida->mensaje) : null,
        ], fn ($parte) => $parte !== null && $parte !== ''));
    }

    public function columnFormats(): array
    {
        if (! $this->listo) {
            return [];
        }

        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => '#,##0',
            'F' => '#,##0.00',
            'G' => '#,##0.00',
            'H' => NumberFormat::FORMAT_TEXT,
            'I' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        if (! $this->listo) {
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
        if (! $this->listo) {
            return [];
        }

        return [
            'A' => 16,
            'B' => 22,
            'C' => 14,
            'D' => 10,
            'E' => 12,
            'F' => 14,
            'G' => 16,
            'H' => 14,
            'I' => 48,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                if (! $this->listo) {
                    return;
                }

                $sheet = $event->sheet->getDelegate();

                if ($this->hayFilaLogos && count($this->rutasLogosExcel) > 0) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetXp = 6;
                    $saltoXp = 160;
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
                        $drawing->setOffsetX($offsetXp + $idx * $saltoXp);
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
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $filaSub = $this->filaSubtituloExcel;
                $sheet->mergeCells('A'.$filaSub.':'.self::COL_ULTIMA.$filaSub);
                $sheet->getRowDimension($filaSub)->setRowHeight(32);
                $sheet->getStyle('A'.$filaSub)->getAlignment()->setWrapText(true);
                $sheet->getStyle('I'.$this->filaPrimeraDatosExcel.':I'.$sheet->getHighestRow())->getAlignment()->setWrapText(true);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Tiendanube stock';
    }
}
