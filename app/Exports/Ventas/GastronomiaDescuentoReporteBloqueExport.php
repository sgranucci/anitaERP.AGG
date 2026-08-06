<?php

namespace App\Exports\Ventas;

use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Export\ExcelFormatoNumero;
use App\Support\Ventas\GastronomiaDescuentoReporteExcelLayout;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GastronomiaDescuentoReporteBloqueExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    private const COL_ULTIMA = 'G';

    /** @var array<string, mixed> */
    private array $bloque;

    private string $periodoTexto;

    private string $empresaNombre;

    private string $titulo = 'Reporte descuentos gastronomía';

    private string $subtitulo = '';

    private string $sheetTitle = 'Descuento';

    private bool $hayFilaLogos = false;

    private bool $esCsv = false;

    private GastronomiaDescuentoReporteExcelLayout $layout;

    private int $filaSubtituloExcel = 0;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  array<string, mixed>  $bloque
     */
    public function __construct(
        array $bloque,
        string $periodoTexto,
        string $empresaNombre,
        string $titulo = 'Reporte descuentos gastronomía',
        string $subtitulo = '',
        bool $esCsv = false,
    ) {
        $this->bloque = $bloque;
        $this->periodoTexto = $periodoTexto;
        $this->empresaNombre = $empresaNombre;
        $this->titulo = $titulo !== '' ? $titulo : 'Reporte descuentos gastronomía';
        $this->subtitulo = $subtitulo;
        $this->esCsv = $esCsv;
        $this->layout = new GastronomiaDescuentoReporteExcelLayout(false, 2);
    }

    public function setTitle(string $title): void
    {
        $this->sheetTitle = $title;
    }

    public function view(): View
    {
        $coleccionLogo = $this->empresaNombre !== ''
            ? collect([(object) ['nombreempresa' => $this->empresaNombre]])
            : collect();
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccionLogo);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;

        $tituloBloque = trim((string) (($this->bloque['codigo'] ?? '').' — '.($this->bloque['nombre'] ?? '')));
        $filasMeta = 2; // título reporte + generado
        if ($tituloBloque !== '' && $tituloBloque !== '—') {
            $filasMeta++;
        }
        if (trim($this->subtitulo) !== '') {
            $filasMeta++;
        } elseif (trim($this->periodoTexto) !== '' || trim($this->empresaNombre) !== '') {
            $filasMeta++;
        }

        $this->layout = new GastronomiaDescuentoReporteExcelLayout(
            $this->hayFilaLogos,
            $filasMeta,
            0,
            1,
        );
        $this->filaSubtituloExcel = trim($this->subtitulo) !== ''
            ? $this->layout->filaInicioMeta() + ($tituloBloque !== '' && $tituloBloque !== '—' ? 3 : 2)
            : 0;

        return view('exports.ventas.gastronomia_descuento_reporte_bloque', [
            'bloque' => $this->bloque,
            'periodo_texto' => $this->periodoTexto,
            'empresa_nombre' => $this->empresaNombre,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'formatoNumero' => $this->formatoNumeroEfectivo(),
        ]);
    }

    public function columnFormats(): array
    {
        $mascara = ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2);

        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'C' => $mascara,
            'D' => $mascara,
            'E' => $mascara,
            'F' => $mascara,
            'G' => $mascara,
        ];
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

    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 34,
            'C' => 12,
            'D' => 14,
            'E' => 14,
            'F' => 14,
            'G' => 14,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $this->layout->aplicarLogos($sheet, $this->rutasLogosExcel);
                $this->layout->aplicarMetaEncabezado($sheet, self::COL_ULTIMA, $this->filaSubtituloExcel);
                $this->layout->aplicarEstiloThead($sheet, self::COL_ULTIMA);
                $this->layout->congelarDebajoThead($sheet);

                $sheet->getStyle('B'.$this->layout->filaPrimeraDatosExcel().':B'.$sheet->getHighestRow())
                    ->getAlignment()
                    ->setWrapText(true);
            },
        ];
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }
}
