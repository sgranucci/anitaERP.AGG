<?php

declare(strict_types=1);

namespace App\Exports\Caja;

use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Export\ExcelFormatoNumero;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Informe presentable (Excel) de un archivo de pagos bancarios: Interbanking o Macro.
 */
class ArchivoPagoInformeExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    private bool $hayFilaLogos = false;

    private int $filaTituloExcel = 1;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    private int $filasMetaEncabezado = 3;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    private string $formatoNumero;

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<array{clave:string,titulo:string,tipo?:string,ancho?:int}>  $columnas
     */
    public function __construct(
        private readonly array $filas,
        private readonly array $columnas,
        private readonly string $titulo,
        private readonly string $subtitulo,
        private readonly float $total,
        private readonly string $nombreHoja = 'Pagos',
    ) {
        $this->formatoNumero = ExcelFormatoNumero::preferenciaGlobal();
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($this->filas);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $this->filasMetaEncabezado = $this->contarFilasMetaEncabezado();
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaTituloExcel = $offsetLogo + 1;
        $this->filaCabecerasExcel = $offsetLogo + $this->filasMetaEncabezado + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
    }

    public function view(): View
    {
        return view('exports.caja.archivo_pago_informe', [
            'filas' => $this->filas,
            'columnas' => $this->columnas,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'total' => $this->total,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'para_excel' => true,
            'formatearMonto' => ExcelFormatoNumero::formateadorMonto($this->formatoNumero),
        ]);
    }

    public function columnFormats(): array
    {
        $formatos = [];
        $mascaraImporte = ExcelFormatoNumero::codigoColumna($this->formatoNumero);
        foreach ($this->columnas as $indice => $columna) {
            $letra = Coordinate::stringFromColumnIndex($indice + 1);
            $formatos[$letra] = (($columna['tipo'] ?? 'texto') === 'importe')
                ? $mascaraImporte
                : '@';
        }

        return $formatos;
    }

    public function columnWidths(): array
    {
        $anchos = [];
        foreach ($this->columnas as $indice => $columna) {
            $letra = Coordinate::stringFromColumnIndex($indice + 1);
            $anchos[$letra] = (int) ($columna['ancho'] ?? 16);
        }

        return $anchos;
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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $ultima = $this->columnaUltima();

                if ($this->hayFilaLogos && $this->rutasLogosExcel !== []) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $offsetX = 6;
                    foreach ($this->rutasLogosExcel as $idx => $ruta) {
                        if (! is_string($ruta) || ! is_readable($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing;
                        $drawing->setName('Logo');
                        $drawing->setPath($ruta);
                        $drawing->setResizeProportional(true);
                        $drawing->setHeight(46);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($offsetX + $idx * 160);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }

                $filaTit = $this->filaTituloExcel;
                $ultimaMeta = $filaTit + $this->filasMetaEncabezado - 1;
                for ($f = $filaTit; $f <= $ultimaMeta; $f++) {
                    $sheet->mergeCells('A'.$f.':'.$ultima.$f);
                }
                $sheet->getRowDimension($filaTit)->setRowHeight(28);
                $sheet->getStyle('A'.$filaTit.':'.$ultima.$filaTit)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                if ($ultimaMeta > $filaTit) {
                    $sheet->getStyle('A'.($filaTit + 1).':'.$ultima.$ultimaMeta)->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10, 'name' => 'Arial', 'color' => ['rgb' => '444444']],
                    ]);
                }

                $sheet->getStyle('A'.$this->filaCabecerasExcel.':'.$ultima.$this->filaCabecerasExcel)
                    ->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '85C1E9']],
                        'font' => ['bold' => true, 'color' => ['rgb' => '17202A'], 'name' => 'Arial', 'size' => 11],
                    ]);

                $filaTotal = $this->filaPrimeraDatosExcel + count($this->filas);
                $sheet->getStyle('A'.$filaTotal.':'.$ultima.$filaTotal)->applyFromArray([
                    'font' => ['bold' => true, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D6EAF8']],
                ]);

                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
                $sheet->setAutoFilter('A'.$this->filaCabecerasExcel.':'.$ultima.$this->filaCabecerasExcel);
            },
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<array{clave:string,titulo:string,tipo?:string,ancho?:int}>  $columnas
     */
    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<array{clave:string,titulo:string,tipo?:string,ancho?:int}>  $columnas
     */
    public static function responder(
        string $formato,
        array $filas,
        array $columnas,
        string $titulo,
        string $subtitulo,
        float $total,
        string $nombre,
        string $nombreHoja,
    ) {
        switch (strtoupper($formato)) {
            case 'PDF':
                return self::descargarPdf($filas, $columnas, $titulo, $subtitulo, $total, $nombre);
            case 'EXCEL':
                return (new self($filas, $columnas, $titulo, $subtitulo, $total, $nombreHoja))
                    ->download($nombre.'.xlsx');
            case 'CSV':
                return (new self($filas, $columnas, $titulo, $subtitulo, $total, $nombreHoja))
                    ->download($nombre.'.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<array{clave:string,titulo:string,tipo?:string,ancho?:int}>  $columnas
     */
    public static function descargarPdf(
        array $filas,
        array $columnas,
        string $titulo,
        string $subtitulo,
        float $total,
        string $nombre,
    ): BinaryFileResponse {
        $html = view('exports.caja.archivo_pago_informe', [
            'filas' => $filas,
            'columnas' => $columnas,
            'titulo' => $titulo,
            'subtitulo' => $subtitulo,
            'total' => $total,
            'para_excel' => false,
        ])->render();

        $path = storage_path('pdf/listados');
        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }

        $archivo = $path.'/'.$nombre.'.pdf';
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('a4', 'landscape');
        $pdf->loadHTML($html, 'UTF-8')->save($archivo);

        return response()->download($archivo, $nombre.'.pdf')->deleteFileAfterSend(true);
    }

    public function title(): string
    {
        $titulo = trim($this->nombreHoja);

        return $titulo !== '' ? mb_substr($titulo, 0, 31) : 'Pagos';
    }

    private function contarFilasMetaEncabezado(): int
    {
        $filas = 2;
        if (trim($this->subtitulo) !== '') {
            $filas++;
        }
        $filas++;

        return $filas;
    }

    private function columnaUltima(): string
    {
        return Coordinate::stringFromColumnIndex(max(1, count($this->columnas)));
    }
}
