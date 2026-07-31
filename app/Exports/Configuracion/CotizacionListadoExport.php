<?php

namespace App\Exports\Configuracion;

use App\Queries\Configuracion\CotizacionQueryInterface;
use App\Support\Configuracion\CotizacionListadoColumnas;
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

class CotizacionListadoExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private CotizacionQueryInterface $cotizacionQuery;

    /** @var array<string, mixed>|string|null */
    private $filtros;

    private bool $flDesdeIndex = false;

    private bool $hayFilaLogos = false;

    /** Primera fila del thead (nombre moneda). */
    private int $filaCabecerasExcel = 2;

    /** Segunda fila del thead (Compra/Venta). */
    private int $filaCabecerasSubExcel = 3;

    private int $filaPrimeraDatosExcel = 4;

    private int $filaTituloExcel = 1;

    private string $colUltima = 'L';

    private int $cantidadMonedas = 0;

    /** @var Collection<int, object> */
    private Collection $monedasColumnas;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(CotizacionQueryInterface $cotizacionQuery)
    {
        $this->cotizacionQuery = $cotizacionQuery;
        $this->monedasColumnas = collect();
    }

    public function view(): View
    {
        $this->monedasColumnas = CotizacionListadoColumnas::monedasParaColumnas();
        $this->cantidadMonedas = $this->monedasColumnas->count();
        $this->colUltima = CotizacionListadoColumnas::letraUltimaColumna($this->cantidadMonedas);

        if ($this->flDesdeIndex) {
            $datas = $this->cotizacionQuery->leeCotizacion($this->filtros, false);
            self::enriquecerNombreEmpresa($datas);

            $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($datas);
            $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
            $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
            // thead: 2 filas (moneda + Compra/Venta)
            $this->filaCabecerasExcel = $this->hayFilaLogos ? 3 : 2;
            $this->filaCabecerasSubExcel = $this->filaCabecerasExcel + 1;
            $this->filaPrimeraDatosExcel = $this->filaCabecerasSubExcel + 1;

            return view('exports.configuracion.cotizacionindex', [
                'datas' => $datas,
                'monedasColumnas' => $this->monedasColumnas,
                'totalColumnas' => CotizacionListadoColumnas::totalColumnasDatos($this->cantidadMonedas),
                'reservarFilaLogoExcel' => $this->hayFilaLogos,
            ]);
        }

        $this->hayFilaLogos = false;
        $this->filaTituloExcel = 1;
        $this->filaCabecerasExcel = 2;
        $this->filaCabecerasSubExcel = 3;
        $this->filaPrimeraDatosExcel = 4;
        $this->rutasLogosExcel = [];

        return view('exports.configuracion.cotizacionindex', [
            'datas' => collect(),
            'monedasColumnas' => $this->monedasColumnas,
            'totalColumnas' => CotizacionListadoColumnas::totalColumnasDatos($this->cantidadMonedas),
            'reservarFilaLogoExcel' => false,
        ]);
    }

    public function columnFormats(): array
    {
        if (! $this->flDesdeIndex) {
            return [];
        }

        $cols = [];
        $total = Coordinate::columnIndexFromString($this->colUltima);
        for ($i = 1; $i <= $total; $i++) {
            $cols[Coordinate::stringFromColumnIndex($i)] = NumberFormat::FORMAT_TEXT;
        }

        return $cols;
    }

    public function styles(Worksheet $sheet)
    {
        // Estilos de cabecera se aplican en AfterSheet (dos filas + merges).
        return [];
    }

    public function columnWidths(): array
    {
        if (! $this->flDesdeIndex) {
            return [];
        }

        $widths = [
            'A' => 8,
            'B' => 12,
        ];

        // Ancho por moneda: alcanza para el nombre (merge de 2 cols) y valores tipo 1.465,0000
        $col = 3;
        foreach ($this->monedasColumnas as $moneda) {
            $nombre = trim((string) ($moneda->nombre ?? ''));
            // Cada pierna Compra/Venta: mínimo 11; el merge del nombre usa el doble
            $anchoPar = max(22, (int) ceil(mb_strlen($nombre) * 1.15) + 2);
            $anchoCol = max(11, (int) ceil($anchoPar / 2));
            $letraCompra = Coordinate::stringFromColumnIndex($col);
            $letraVenta = Coordinate::stringFromColumnIndex($col + 1);
            $widths[$letraCompra] = $anchoCol;
            $widths[$letraVenta] = $anchoCol;
            $col += 2;
        }

        return $widths;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                if (! $this->flDesdeIndex) {
                    return;
                }

                $sheet = $event->sheet->getDelegate();
                $colUltima = $this->colUltima;
                $filaCab = $this->filaCabecerasExcel;
                $filaSub = $this->filaCabecerasSubExcel;

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
                $sheet->mergeCells('A'.$filaTit.':'.$colUltima.$filaTit);
                $sheet->getRowDimension($filaTit)->setRowHeight(30);
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

                // ID / Fecha: una sola celda vertical en las 2 filas de cabecera
                $sheet->mergeCells('A'.$filaCab.':A'.$filaSub);
                $sheet->mergeCells('B'.$filaCab.':B'.$filaSub);

                // Nombre de moneda sobre Compra+Venta
                $col = 3;
                for ($i = 0; $i < $this->cantidadMonedas; $i++) {
                    $c1 = Coordinate::stringFromColumnIndex($col);
                    $c2 = Coordinate::stringFromColumnIndex($col + 1);
                    $sheet->mergeCells($c1.$filaCab.':'.$c2.$filaCab);
                    $col += 2;
                }

                $estiloCabecera = [
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
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ];
                $sheet->getStyle('A'.$filaCab.':'.$colUltima.$filaSub)->applyFromArray($estiloCabecera);
                $sheet->getRowDimension($filaCab)->setRowHeight(22);
                $sheet->getRowDimension($filaSub)->setRowHeight(18);

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);

                $primera = $this->filaPrimeraDatosExcel;
                $ultimaFila = max($primera, $sheet->getHighestRow());
                if ($ultimaFila >= $primera) {
                    $sheet->getStyle('C'.$primera.':'.$colUltima.$ultimaFila)
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
            },
        ];
    }

    public function title(): string
    {
        return 'Cotizaciones';
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     */
    public function parametros($filtros): self
    {
        $this->filtros = $filtros;
        $this->flDesdeIndex = true;

        return $this;
    }

    /**
     * @param  \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection  $datas
     */
    private static function enriquecerNombreEmpresa($datas): void
    {
        $nombreEmpresa = (string) config('app.empresa');
        foreach ($datas as $row) {
            $row->nombreempresa = $nombreEmpresa;
        }
    }
}
