<?php

namespace App\Exports\Configuracion;

use App\Repositories\Configuracion\ArbolaprobacionRepositoryInterface;
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
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ArbolaprobacionListadoExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'N';

    private ArbolaprobacionRepositoryInterface $repository;

    /** @var array<string, mixed>|null */
    private $filtros;

    private bool $flDesdeIndex = false;

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filaTituloExcel = 1;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct(ArbolaprobacionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function view(): View
    {
        if ($this->flDesdeIndex) {
            $datas = $this->repository->leeArbolaprobacion($this->filtros ?? []);
            $filas = self::aplanarFilas($datas);

            $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(
                collect($filas)->map(fn ($f) => (object) ['nombreempresa' => $f['empresa']])
            );
            $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
            $this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
            $this->filaCabecerasExcel = $this->hayFilaLogos ? 3 : 2;
            $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

            return view('exports.configuracion.arbolaprobacion_index', [
                'filas' => $filas,
                'reservarFilaLogoExcel' => $this->hayFilaLogos,
            ]);
        }

        $this->hayFilaLogos = false;
        $this->filaTituloExcel = 1;
        $this->filaCabecerasExcel = 2;
        $this->filaPrimeraDatosExcel = 3;
        $this->rutasLogosExcel = [];

        return view('exports.configuracion.arbolaprobacion_index', [
            'filas' => [],
            'reservarFilaLogoExcel' => false,
        ]);
    }

    /**
     * Una fila por nivel (detalle del circuito); árboles sin niveles: una fila cabecera.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Configuracion\Arbolaprobacion>|\Illuminate\Database\Eloquent\Collection  $datas
     * @return list<array<string, string|int|float|null>>
     */
    public static function aplanarFilas($datas): array
    {
        $filas = [];
        foreach ($datas as $arbol) {
            $empresa = (string) (optional($arbol->empresas)->nombre ?? '');
            $cabecera = [
                'id' => $arbol->id,
                'nombre' => (string) ($arbol->nombre ?? ''),
                'empresa' => $empresa,
                'tipo' => (string) ($arbol->tipoarbol ?? ''),
                'estado' => (string) ($arbol->estado ?? ''),
                'recordatorio' => (string) ($arbol->recordatorio ?? ''),
            ];

            $niveles = $arbol->arbolaprobacion_niveles ?? collect();
            if ($niveles->isEmpty()) {
                $filas[] = array_merge($cabecera, [
                    'nivel' => '',
                    'rama' => '',
                    'centrocosto' => '',
                    'firmante' => '',
                    'desde_monto' => '',
                    'hasta_monto' => '',
                    'moneda' => '',
                    'estado_doc' => '',
                ]);

                continue;
            }

            foreach ($niveles as $nivel) {
                $cc = optional($nivel->centrocosto_ids);
                $filas[] = array_merge($cabecera, [
                    'nivel' => $nivel->nivel,
                    'rama' => (string) ($nivel->rama ?? ''),
                    'centrocosto' => trim(($cc->codigo ?? '').' '.($cc->nombre ?? '')),
                    'firmante' => (string) (optional($nivel->usuarios)->nombre ?: 'auto'),
                    'desde_monto' => $nivel->desdemonto,
                    'hasta_monto' => $nivel->hastamonto,
                    'moneda' => (string) (optional($nivel->moneda_ids)->nombre ?? ''),
                    'estado_doc' => (string) ($nivel->documento_estado_al_aprobar ?? ''),
                ]);
            }
        }

        return $filas;
    }

    public function columnFormats(): array
    {
        if (! $this->flDesdeIndex) {
            return [];
        }

        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'F' => NumberFormat::FORMAT_TEXT,
            'G' => NumberFormat::FORMAT_TEXT,
            'H' => NumberFormat::FORMAT_TEXT,
            'I' => NumberFormat::FORMAT_TEXT,
            'J' => NumberFormat::FORMAT_TEXT,
            'K' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'L' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'M' => NumberFormat::FORMAT_TEXT,
            'N' => NumberFormat::FORMAT_TEXT,
        ];
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

        return [
            'A' => 8,
            'B' => 28,
            'C' => 22,
            'D' => 18,
            'E' => 12,
            'F' => 10,
            'G' => 8,
            'H' => 8,
            'I' => 18,
            'J' => 22,
            'K' => 12,
            'L' => 12,
            'M' => 10,
            'N' => 16,
        ];
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
                $sheet->mergeCells('A'.$filaTit.':'.self::COL_ULTIMA.$filaTit);
                $sheet->getRowDimension($filaTit)->setRowHeight(30);
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

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Árboles aprobación';
    }

    /**
     * @param  array<string, mixed>|null  $filtros
     */
    public function parametros($filtros): self
    {
        $this->filtros = $filtros;
        $this->flDesdeIndex = true;

        return $this;
    }
}
