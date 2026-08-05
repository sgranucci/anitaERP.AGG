<?php

namespace App\Exports\Caja;

use App\Repositories\Caja\CotizacionTesoreriaRepositoryInterface;
use App\Support\Caja\CotizacionTesoreriaMonedasSupport;
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
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CotizacionTesoreriaListadoExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private CotizacionTesoreriaRepositoryInterface $repository;

    /** @var array<string, mixed>|string|null */
    private $filtros;

    private bool $flDesdeIndex = false;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 2;

    private int $filaCabecerasSubExcel = 3;

    private int $filaPrimeraDatosExcel = 4;

    private int $filaTituloExcel = 1;

    private string $colUltima = 'R';

    /** @var Collection<int, object> */
    private Collection $monedasColumnas;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(CotizacionTesoreriaRepositoryInterface $repository)
    {
        $this->repository = $repository;
        $this->monedasColumnas = collect();
    }

    public function view(): View
    {
        $this->monedasColumnas = CotizacionTesoreriaMonedasSupport::monedasParaColumnas();
        $this->colUltima = CotizacionTesoreriaMonedasSupport::letraUltimaColumna();

        if ($this->flDesdeIndex) {
            $datas = $this->repository->leeCotizacionTesoreria($this->filtros, false);
            self::enriquecerNombreEmpresa($datas);

            $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($datas);
            $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
            $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
            $this->filaCabecerasExcel = $this->hayFilaLogos ? 3 : 2;
            $this->filaCabecerasSubExcel = $this->filaCabecerasExcel + 1;
            $this->filaPrimeraDatosExcel = $this->filaCabecerasSubExcel + 1;

            return view('exports.caja.cotizacion_tesoreriaindex', [
                'datas' => $datas,
                'monedasColumnas' => $this->monedasColumnas,
                'totalColumnas' => CotizacionTesoreriaMonedasSupport::totalColumnasDatos(),
                'reservarFilaLogoExcel' => $this->hayFilaLogos,
            ]);
        }

        $this->hayFilaLogos = false;
        $this->filaTituloExcel = 1;
        $this->filaCabecerasExcel = 2;
        $this->filaCabecerasSubExcel = 3;
        $this->filaPrimeraDatosExcel = 4;
        $this->rutasLogosExcel = [];

        return view('exports.caja.cotizacion_tesoreriaindex', [
            'datas' => collect(),
            'monedasColumnas' => $this->monedasColumnas,
            'totalColumnas' => CotizacionTesoreriaMonedasSupport::totalColumnasDatos(),
            'reservarFilaLogoExcel' => false,
        ]);
    }

    public function columnFormats(): array
    {
        if (! $this->flDesdeIndex) {
            return [];
        }

        $cols = [];
        foreach (range('A', $this->colUltima) as $c) {
            $cols[$c] = NumberFormat::FORMAT_TEXT;
        }

        return $cols;
    }

    public function styles(Worksheet $sheet)
    {
        if (! $this->flDesdeIndex) {
            return [];
        }

        $estilo = [
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
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];

        return [
            $this->filaCabecerasExcel => $estilo,
            $this->filaCabecerasSubExcel => $estilo,
        ];
    }

    public function columnWidths(): array
    {
        if (! $this->flDesdeIndex) {
            return [];
        }

        $widths = ['A' => 8, 'B' => 18, 'C' => 12];
        $colIndex = 4;
        foreach ($this->monedasColumnas as $_) {
            $letraCompra = $this->indexToLetter($colIndex++);
            $letraVenta = $this->indexToLetter($colIndex++);
            $widths[$letraCompra] = 11;
            $widths[$letraVenta] = 11;
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
                $sheet->mergeCells('A'.$filaTit.':'.$this->colUltima.$filaTit);
                $sheet->getRowDimension($filaTit)->setRowHeight(30);
                $sheet->getStyle('A'.$filaTit.':'.$this->colUltima.$filaTit)->applyFromArray([
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

                // Fusionar encabezados de moneda (fila principal)
                $colIndex = 3;
                foreach ($this->monedasColumnas as $_) {
                    $letraIni = $this->indexToLetter($colIndex);
                    $letraFin = $this->indexToLetter($colIndex + 1);
                    $sheet->mergeCells($letraIni.$this->filaCabecerasExcel.':'.$letraFin.$this->filaCabecerasExcel);
                    $colIndex += 2;
                }
                $sheet->mergeCells('A'.$this->filaCabecerasExcel.':A'.$this->filaCabecerasSubExcel);
                $sheet->mergeCells('B'.$this->filaCabecerasExcel.':B'.$this->filaCabecerasSubExcel);

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Cotización tesorería';
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

    private function indexToLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Caja\CotizacionTesoreria>|\Illuminate\Database\Eloquent\Collection<int, \App\Models\Caja\CotizacionTesoreria>  $datas
     */
    private static function enriquecerNombreEmpresa($datas): void
    {
        $fallback = (string) config('app.empresa');
        foreach ($datas as $row) {
            $nombre = trim((string) ($row->nombreempresa ?? ''));
            if ($nombre === '') {
                $nombre = trim((string) ($row->empresas?->nombre ?? ''));
            }
            $row->nombreempresa = $nombre !== '' ? $nombre : $fallback;
        }
    }
}
