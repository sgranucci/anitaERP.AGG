<?php

namespace App\Exports\Ventas;

use App\Services\Ventas\GastronomiaAnaliticoReporteService;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Ventas\GastronomiaDescuentoReporteExcelLayout;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export FromArray (no FromView): con ~50k filas el HTML→Spreadsheet es el cuello de botella.
 */
class GastronomiaAnaliticoReporteExport implements FromArray, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private const COL_ULTIMA = 'Y';

    private const COL_COUNT = 25;

    /** @var array<string, mixed> */
    private array $filtros = [];

    private string $titulo = 'Reporte analítico gastronomía';

    private string $subtitulo = '';

    private string $empresaNombre = '';

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 4;

    private int $filaPrimeraDatosExcel = 5;

    private int $filaTituloExcel = 2;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /** @var array<string, mixed> */
    private array $resultado = [];

    /** @var list<int> */
    private array $filasHeaderEmpresaExcel = [];

    private int $filaSubtituloExcel = 0;

    private GastronomiaDescuentoReporteExcelLayout $layout;

    public function __construct(
        private readonly GastronomiaAnaliticoReporteService $reporteService,
    ) {
        $this->layout = new GastronomiaDescuentoReporteExcelLayout(false, 2);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>|null  $resultadoPrecalculado
     */
    public function parametros(
        array $filtros,
        string $titulo,
        string $subtitulo,
        string $empresaNombre = '',
        ?array $resultadoPrecalculado = null,
    ): self {
        $this->filtros = $filtros;
        $this->titulo = $titulo;
        $this->subtitulo = $subtitulo;
        $this->empresaNombre = trim($empresaNombre);
        $this->resultado = $resultadoPrecalculado ?? [];

        return $this;
    }

    public function array(): array
    {
        if ($this->resultado === []) {
            $this->resultado = $this->reporteService->generar($this->filtros, false);
        }

        $filas = collect($this->resultado['filas'] ?? []);
        $coleccionLogo = $this->empresaNombre !== ''
            ? collect([(object) ['nombreempresa' => $this->empresaNombre]])
            : $filas
                ->filter(static fn ($f) => ($f->tipo_fila ?? 'detalle') !== 'header_empresa')
                ->map(static fn ($f) => (object) [
                    'nombreempresa' => trim((string) ($f->nombreempresa ?? $f->sala ?? '')),
                ])
                ->filter(static fn ($r) => $r->nombreempresa !== '')
                ->unique('nombreempresa')
                ->values();
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccionLogo);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;

        $filasMeta = GastronomiaDescuentoReporteExcelLayout::contarFilasMeta($this->subtitulo, false, false);
        $this->layout = new GastronomiaDescuentoReporteExcelLayout($this->hayFilaLogos, $filasMeta);
        $this->filaTituloExcel = $this->layout->filaInicioMeta();
        $this->filaCabecerasExcel = $this->layout->filaCabecerasExcel();
        $this->filaPrimeraDatosExcel = $this->layout->filaPrimeraDatosExcel();
        $this->filaSubtituloExcel = trim($this->subtitulo) !== ''
            ? $this->layout->filaInicioMeta() + 2
            : 0;

        $rows = [];
        if ($this->hayFilaLogos) {
            $rows[] = $this->filaLogo();
        }
        $rows[] = $this->celdaUnica($this->titulo);
        $rows[] = $this->celdaUnica('Generado '.date('d/m/Y H:i'));
        if (trim($this->subtitulo) !== '') {
            $rows[] = $this->celdaUnica($this->subtitulo);
        }
        $rows[] = [
            'Id', 'Fecha jornada', 'Fecha real', 'Sala', 'Tipo comprobante', 'Punto venta',
            'Nº comprobante', 'Mozo Id', 'Nombre mozo', 'Legajo mozo', 'Código artículo',
            'Descripción artículo', 'Tipo venta', 'Cantidad', 'Precio unitario', 'Obs. precio 0',
            'Total', 'Costo', 'Tipo descuento', 'Categoría artículo', 'Cliente', 'Año', 'Hora', 'Mes', 'Día',
        ];

        $excelRow = $this->filaPrimeraDatosExcel;
        $this->filasHeaderEmpresaExcel = [];
        foreach ($filas as $f) {
            if (($f->tipo_fila ?? 'detalle') === 'header_empresa') {
                $this->filasHeaderEmpresaExcel[] = $excelRow;
                $rows[] = $this->celdaUnica('Empresa: '.trim((string) ($f->nombreempresa ?? $f->sala ?? '')));
            } else {
                $rows[] = [
                    (string) ($f->id ?? ''),
                    (string) ($f->fecha_jornada_fmt ?? ''),
                    (string) ($f->fecha_real_fmt ?? ''),
                    (string) ($f->sala ?? ''),
                    (string) ($f->tipo_comprobante ?? ''),
                    (string) ($f->punto_venta ?? ''),
                    (string) ($f->numero_comprobante ?? ''),
                    (string) ($f->mozo_id ?? ''),
                    (string) ($f->nombre_mozo ?? ''),
                    (string) ($f->legajo_mozo ?? ''),
                    (string) ($f->codigo_articulo ?? ''),
                    (string) ($f->descripcion_articulo ?? ''),
                    (string) ($f->tipo_venta ?? ''),
                    round((float) ($f->cantidad ?? 0), 4),
                    round((float) ($f->precio_unitario ?? 0), 2),
                    (string) ($f->observacion_precio ?? ''),
                    round((float) ($f->total ?? 0), 2),
                    round((float) ($f->costo ?? 0), 2),
                    (string) ($f->tipo_descuento ?? ''),
                    (string) ($f->categoria_articulo ?? ''),
                    (string) ($f->cliente ?? ''),
                    (string) ($f->anio ?? ''),
                    (string) ($f->hora ?? ''),
                    (string) ($f->mes ?? ''),
                    (string) ($f->dia ?? ''),
                ];
            }
            $excelRow++;
        }

        $tot = $this->resultado['totales'] ?? [];
        if ($tot !== []) {
            $rows[] = array_merge(
                ['Totales ('.(int) ($tot['cantidad_filas'] ?? 0).' filas)'],
                array_fill(0, 12, ''),
                [
                    round((float) ($tot['cantidad_total'] ?? 0), 4),
                    '',
                    '',
                    round((float) ($tot['total_importe'] ?? 0), 2),
                    round((float) ($tot['costo_total'] ?? 0), 2),
                ],
                array_fill(0, 7, ''),
            );
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function filaVacia(): array
    {
        return array_fill(0, self::COL_COUNT, '');
    }

    /**
     * Fila que reserva el alto del logo. A1 no puede quedar vacía: Laravel Excel
     * escribe en bloques de 1000 filas y decide dónde continuar según exista A1;
     * si no existe, cada bloque vuelve a empezar en la fila 1 y pisa al anterior.
     *
     * @return list<string>
     */
    private function filaLogo(): array
    {
        $row = $this->filaVacia();
        $row[0] = ' ';

        return $row;
    }

    /**
     * @return list<string>
     */
    private function celdaUnica(string $texto): array
    {
        $row = $this->filaVacia();
        $row[0] = $texto;

        return $row;
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'G' => NumberFormat::FORMAT_TEXT,
            'H' => NumberFormat::FORMAT_TEXT,
            'K' => NumberFormat::FORMAT_TEXT,
            'N' => NumberFormat::FORMAT_NUMBER_00,
            'O' => NumberFormat::FORMAT_NUMBER_00,
            'P' => NumberFormat::FORMAT_TEXT,
            'Q' => NumberFormat::FORMAT_NUMBER_00,
            'R' => NumberFormat::FORMAT_NUMBER_00,
            'V' => NumberFormat::FORMAT_TEXT,
            'W' => NumberFormat::FORMAT_TEXT,
            'X' => NumberFormat::FORMAT_TEXT,
            'Y' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10, 'B' => 12, 'C' => 12, 'D' => 18, 'E' => 10, 'F' => 10,
            'G' => 14, 'H' => 10, 'I' => 18, 'J' => 10, 'K' => 12, 'L' => 28,
            'M' => 10, 'N' => 10, 'O' => 10, 'P' => 36, 'Q' => 10, 'R' => 10,
            'S' => 16, 'T' => 14, 'U' => 22, 'V' => 8, 'W' => 8, 'X' => 6, 'Y' => 6,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colUltima = self::COL_ULTIMA;

                $this->layout->aplicarLogos($sheet, $this->rutasLogosExcel);
                $this->layout->aplicarMetaEncabezado($sheet, $colUltima, $this->filaSubtituloExcel);
                $this->layout->aplicarEstiloThead($sheet, $colUltima);

                foreach ($this->filasHeaderEmpresaExcel as $filaHeader) {
                    $sheet->mergeCells('A'.$filaHeader.':'.$colUltima.$filaHeader);
                    $sheet->getStyle('A'.$filaHeader.':'.$colUltima.$filaHeader)->applyFromArray([
                        'font' => ['bold' => true, 'name' => 'Arial', 'color' => ['rgb' => '1B4F72']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'color' => ['rgb' => 'D6EAF8'],
                        ],
                    ]);
                }

                $this->layout->congelarDebajoThead($sheet);
            },
        ];
    }

    public function title(): string
    {
        return 'Analítico gastro';
    }
}
