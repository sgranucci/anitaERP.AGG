<?php

namespace App\Exports\Uif;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UifConciliacionWigosExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use Exportable;

  private bool $flActivo = false;

    private string $titulo = '';

    private string $subtitulo = '';

    /** @var Collection<int, object> */
    private Collection $filas;

    /** @var list<string> */
    private array $rutasLogosExcel = [];

    public function __construct()
    {
        $this->filas = collect();
    }

    /**
     * @param  Collection<int, object>|iterable<int, object>  $filas
     */
    public function parametros(string $titulo, string $subtitulo, iterable $filas): self
    {
        $this->titulo = $titulo;
        $this->subtitulo = $subtitulo;
        $coleccion = $filas instanceof Collection ? $filas : collect($filas);
        $this->filas = $coleccion->map(function ($fila) {
            if (is_object($fila)) {
                $fila->nombreempresa = $this->subtitulo;

                return $fila;
            }

            return $fila;
        });
        $this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($this->filas);
        $this->flActivo = true;

        return $this;
    }

    public function view(): View
    {
        return view('exports.uif.conciliacion_wigos_unificadoindex', [
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'filas' => $this->filas,
            'rutasLogosExcel' => $this->rutasLogosExcel,
        ]);
    }

    public function columnFormats(): array
    {
        if (! $this->flActivo) {
            return [];
        }

        return [
            'E' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_NUMBER_00,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        if (! $this->flActivo) {
            return [];
        }

        $headerRow = count($this->rutasLogosExcel) > 0 ? 2 : 1;

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12, 'name' => 'Arial'],
            ],
            $headerRow => [
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
        if (! $this->flActivo) {
            return [];
        }

        return [
            'A' => 18,
            'B' => 18,
            'C' => 14,
            'D' => 12,
            'E' => 22,
            'F' => 14,
            'G' => 28,
        ];
    }

    public function title(): string
    {
        return 'Unificado';
    }

    public function registerEvents(): array
    {
        if (! $this->flActivo) {
            return [];
        }

        $headerRow = count($this->rutasLogosExcel) > 0 ? 2 : 1;
        $dataRow = $headerRow + 1;

        return [
            AfterSheet::class => function (AfterSheet $event) use ($headerRow, $dataRow) {
                $sheet = $event->sheet->getDelegate();
                $sheet->mergeCells('A1:G1');
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->freezePane('A'.$dataRow);
            },
        ];
    }
}
