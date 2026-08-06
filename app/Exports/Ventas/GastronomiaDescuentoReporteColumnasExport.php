<?php

namespace App\Exports\Ventas;

use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Export\ExcelFormatoNumero;
use App\Support\Ventas\GastronomiaDescuentoReporteExcelLayout;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GastronomiaDescuentoReporteColumnasExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private string $titulo = '';

    private string $subtitulo = '';

    private string $empresaNombre = '';

    /** @var array<string, mixed> */
    private array $resultado = [];

    private bool $hayFilaLogos = false;

    private bool $esCsv = false;

    private int $cantidadColumnas = 7;

    private GastronomiaDescuentoReporteExcelLayout $layout;

    private int $filaSubtituloExcel = 0;

    private string $colUltima = 'G';

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  array<string, mixed>  $resultado
     */
    public function parametros(
        array $resultado,
        string $titulo,
        string $subtitulo,
        string $empresaNombre,
        bool $esCsv = false,
    ): self {
        $this->resultado = $resultado;
        $this->titulo = $titulo;
        $this->subtitulo = $subtitulo;
        $this->empresaNombre = $empresaNombre;
        $this->esCsv = $esCsv;

        return $this;
    }

    public function view(): View
    {
        $vista = $this->resultado['vista_columnas'] ?? [];
        $columnas = $vista['columnas'] ?? [];
        $this->cantidadColumnas = max(4 + count($columnas) * 3, 7);
        $this->colUltima = Coordinate::stringFromColumnIndex($this->cantidadColumnas);

        $coleccionLogo = $this->empresaNombre !== ''
            ? collect([(object) ['nombreempresa' => $this->empresaNombre]])
            : collect();
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccionLogo);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;

        $filasMeta = GastronomiaDescuentoReporteExcelLayout::contarFilasMeta(
            $this->subtitulo,
            true,
            count($columnas) > 0,
        );
        $this->layout = new GastronomiaDescuentoReporteExcelLayout(
            $this->hayFilaLogos,
            $filasMeta,
            0,
            2,
        );
        $this->filaSubtituloExcel = trim($this->subtitulo) !== ''
            ? $this->layout->filaInicioMeta() + 2
            : 0;

        return view('exports.ventas.gastronomia_descuento_reporte_columnas', [
            'resultado' => $this->resultado,
            'vista' => $vista,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'formatoNumero' => $this->formatoNumeroEfectivo(),
        ]);
    }

    public function columnFormats(): array
    {
        $mascara = ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2);
        $formatos = [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
        ];

        for ($indice = 3; $indice <= $this->cantidadColumnas; $indice++) {
            $formatos[Coordinate::stringFromColumnIndex($indice)] = $mascara;
        }

        return $formatos;
    }

    private function formatoNumeroEfectivo(): string
    {
        $global = ExcelFormatoNumero::preferenciaGlobal();

        return $this->esCsv ? ExcelFormatoNumero::paraCsv($global) : $global;
    }

    public function styles(Worksheet $sheet)
    {
        return [
            $this->layout->filaCabecerasExcel() => [
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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $this->layout->aplicarLogos($sheet, $this->rutasLogosExcel);
                $this->layout->aplicarMetaEncabezado($sheet, $this->colUltima, $this->filaSubtituloExcel);
                $this->layout->aplicarEstiloThead($sheet, $this->colUltima);
                $this->layout->congelarDebajoThead($sheet);

                $sheet->getStyle('B'.$this->layout->filaPrimeraDatosExcel().':B'.$sheet->getHighestRow())
                    ->getAlignment()
                    ->setWrapText(true);
            },
        ];
    }

    public function title(): string
    {
        return 'Consolidado';
    }
}
