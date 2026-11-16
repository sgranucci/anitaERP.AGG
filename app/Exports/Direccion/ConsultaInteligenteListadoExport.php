<?php

declare(strict_types=1);

namespace App\Exports\Direccion;

use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\ConsultaInteligente\EntidadSemantica;
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

final class ConsultaInteligenteListadoExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    /** @var list<object> */
    private array $filas = [];

    /** @var list<string> */
    private array $columnas = [];

    /** @var array<string, string> */
    private array $etiquetas = [];

    private string $titulo = 'Consulta Inteligente';

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    /**
     * @param  Collection<int, object>|iterable<int, object>  $filas
     * @param  list<string>  $columnas
     * @param  array<string, string>  $etiquetas
     */
    public function __construct(
        private readonly EntidadSemantica $entidad,
        iterable $filas,
        array $columnas,
        array $etiquetas,
    ) {
        $this->filas = is_array($filas) ? $filas : iterator_to_array($filas);
        $this->columnas = array_values($columnas);
        $this->etiquetas = $etiquetas;
        $this->titulo = 'CI · '.$entidad->nombre();
    }

    public function view(): View
    {
        $coleccion = collect($this->filas);
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccion);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $filasMeta = 2; // título + generado
        $offsetLogo = $this->hayFilaLogos ? 1 : 0;
        $this->filaCabecerasExcel = $offsetLogo + $filasMeta + 1;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        $matriz = [];
        foreach ($this->filas as $row) {
            $linea = [];
            foreach ($this->columnas as $key) {
                $linea[$key] = $this->valorCeldaExport($row, $key);
            }
            $matriz[] = $linea;
        }

        return view('exports.direccion.consulta_inteligenteindex', [
            'titulo' => $this->titulo,
            'columnas' => $this->columnas,
            'etiquetas' => $this->etiquetas,
            'matriz' => $matriz,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'totalFilas' => count($matriz),
        ]);
    }

    public function title(): string
    {
        return mb_substr($this->entidad->nombre(), 0, 31);
    }

    public function columnWidths(): array
    {
        $widths = [];
        $letra = 'A';
        foreach ($this->columnas as $_) {
            $widths[$letra] = 16;
            $letra++;
        }

        return $widths;
    }

    public function columnFormats(): array
    {
        $formats = [];
        $letra = 'A';
        foreach ($this->columnas as $key) {
            if ($this->esColumnaImporte($key)) {
                $formats[$letra] = '#,##0.00';
            } elseif ($this->esColumnaId($key)) {
                $formats[$letra] = NumberFormat::FORMAT_TEXT;
            }
            $letra++;
        }

        return $formats;
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colUltima = $this->columnaExcel(count($this->columnas));
                $offsetLogo = $this->hayFilaLogos ? 1 : 0;

                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
                    $x = 5;
                    foreach ($this->rutasLogosExcel as $ruta) {
                        if (! is_string($ruta) || $ruta === '' || ! is_file($ruta)) {
                            continue;
                        }
                        $drawing = new Drawing;
                        $drawing->setPath($ruta);
                        $drawing->setHeight(48);
                        $drawing->setCoordinates('A1');
                        $drawing->setOffsetX($x);
                        $drawing->setWorksheet($sheet);
                        $x += 90;
                    }
                }

                $filaTitulo = $offsetLogo + 1;
                $filaGenerado = $offsetLogo + 2;
                $sheet->mergeCells("A{$filaTitulo}:{$colUltima}{$filaTitulo}");
                $sheet->mergeCells("A{$filaGenerado}:{$colUltima}{$filaGenerado}");
                $sheet->getStyle("A{$filaTitulo}")->getFont()->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getRowDimension($filaTitulo)->setRowHeight(28);
                $sheet->getStyle("A{$filaGenerado}")->getFont()->setName('Arial')->setSize(10)->setBold(true)->getColor()->setRGB('444444');

                $rangoCab = "A{$this->filaCabecerasExcel}:{$colUltima}{$this->filaCabecerasExcel}";
                $sheet->getStyle($rangoCab)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('85C1E9');
                $sheet->getStyle($rangoCab)->getFont()->setName('Arial')->setSize(11)->setBold(true)->getColor()->setRGB('17202A');
                $sheet->getStyle($rangoCab)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    private function columnaExcel(int $indice1): string
    {
        $n = max(1, $indice1);
        $s = '';
        while ($n > 0) {
            $n--;
            $s = chr(65 + ($n % 26)).$s;
            $n = intdiv($n, 26);
        }

        return $s;
    }

    /**
     * @return float|string
     */
    private function valorCeldaExport(object $row, string $key)
    {
        $texto = trim(html_entity_decode(
            strip_tags($this->entidad->valorCelda($row, $key)),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        ));
        if (! $this->esColumnaImporte($key)) {
            return $texto;
        }
        $num = preg_replace('/[^\d,.\-]/', '', $texto) ?? '';
        if ($num === '' || $num === '-' || $num === '.' || $num === ',') {
            return $texto;
        }
        if (str_contains($num, ',') && str_contains($num, '.')) {
            $num = str_replace('.', '', $num);
            $num = str_replace(',', '.', $num);
        } elseif (str_contains($num, ',')) {
            $num = str_replace(',', '.', $num);
        }
        if (! is_numeric($num)) {
            return $texto;
        }

        return round((float) $num, 2);
    }

    private function esColumnaImporte(string $key): bool
    {
        $k = mb_strtolower($key);

        return (bool) preg_match('/(monto|importe|total|precio|saldo|debe|haber|neto|iva|costo)/u', $k);
    }

    private function esColumnaId(string $key): bool
    {
        $k = mb_strtolower($key);

        return $k === 'id' || str_ends_with($k, '_id') || $k === 'sku' || $k === 'codigo' || $k === 'cuit' || $k === 'cuil';
    }
}
