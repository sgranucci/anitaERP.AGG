<?php

namespace App\Exports\Sueldos;

use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Sueldos\EntregaPrendaListadoColumnas;
use App\Support\Sueldos\EntregaPrendaListadoFiltros;
use App\Support\Sueldos\EntregaPrendaReporteConsulta;
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
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class EntregaPrendaReporteExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    /** @var array<string, mixed> */
    private array $filtros = [];

    /** @var list<string> */
    private array $columnas = [];

    /** @var array<string, string> */
    private array $etiquetas = [];

    private bool $hayFilaLogos = false;

    private int $filaCabecerasExcel = 3;

    private int $filaPrimeraDatosExcel = 4;

    private int $filaTituloExcel = 1;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function view(): View
    {
        $datas = EntregaPrendaReporteConsulta::lee($this->filtros, false);
        if ($this->columnas === []) {
            $this->columnas = EntregaPrendaListadoColumnas::defaultsVisibles();
        }
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($datas);
        $this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
        $offset = $this->hayFilaLogos ? 1 : 0;
        $this->filaTituloExcel = $offset + 1;
        $this->filaCabecerasExcel = $offset + 3;
        $this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

        return view('exports.sueldos.entregaprendaindex', [
            'datas' => $datas,
            'reservarFilaLogoExcel' => $this->hayFilaLogos,
            'subtitulo' => EntregaPrendaListadoFiltros::subtitulo($this->filtros),
            'columnasVisibles' => $this->columnas,
            'etiquetasColumnas' => $this->etiquetas,
            'totalCantidad' => (float) $datas->sum('cantidad'),
        ]);
    }

    public function columnFormats(): array
    {
        $formats = [];
        $n = max(1, count($this->columnas));
        for ($i = 1; $i <= $n; $i++) {
            $formats[$this->columnaLetra($i)] = NumberFormat::FORMAT_TEXT;
        }

        return $formats;
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

    public function columnWidths(): array
    {
        $anchos = [];
        foreach ($this->columnas as $i => $key) {
            $anchos[$this->columnaLetra($i + 1)] = in_array($key, ['empleado', 'prenda', 'observacion', 'agrupamiento', 'empresa'], true) ? 28 : 16;
        }

        return $anchos;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                if ($this->hayFilaLogos) {
                    $sheet->getRowDimension(1)->setRowHeight(54);
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
                        $drawing->setOffsetX(6 + $idx * 160);
                        $drawing->setOffsetY(4);
                        $drawing->setWorksheet($sheet);
                    }
                }
                $filaTit = $this->filaTituloExcel;
                $ultima = $this->columnaLetra(max(1, count($this->columnas)));
                $sheet->mergeCells('A'.$filaTit.':'.$ultima.$filaTit);
                $sheet->mergeCells('A'.($filaTit + 1).':'.$ultima.($filaTit + 1));
                $sheet->getRowDimension($filaTit)->setRowHeight(28);
                $sheet->getStyle('A'.$filaTit.':'.$ultima.$filaTit)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'name' => 'Arial', 'color' => ['rgb' => '17202A']],
                ]);
                $sheet->freezePane('A'.$this->filaPrimeraDatosExcel);
            },
        ];
    }

    public function title(): string
    {
        return 'Entregas indumentaria';
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<string>|null  $columnas
     * @param  array<string, string>|null  $etiquetas
     */
    public function parametros(array $filtros, ?array $columnas = null, ?array $etiquetas = null): self
    {
        $this->filtros = $filtros;
        $this->columnas = $columnas ?? [];
        $this->etiquetas = $etiquetas ?? [];

        return $this;
    }

    private function columnaLetra(int $n): string
    {
        $letra = '';
        while ($n > 0) {
            $n--;
            $letra = chr(65 + ($n % 26)).$letra;
            $n = intdiv($n, 26);
        }

        return $letra !== '' ? $letra : 'A';
    }
}
