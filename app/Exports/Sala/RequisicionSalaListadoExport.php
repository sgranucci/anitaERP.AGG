<?php

namespace App\Exports\Sala;

use App\Queries\Sala\RequisicionSalaQueryInterface;
use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class RequisicionSalaListadoExport implements FromView, WithColumnFormatting, WithColumnWidths, WithEvents
{
    private $query;

    private $filtros = [];

    private $flDesdeIndex = false;

    public function __construct(RequisicionSalaQueryInterface $query)
    {
        $this->query = $query;
    }

    public function parametros($filtros): self
    {
        $this->filtros = is_array($filtros) ? $filtros : [];
        $this->flDesdeIndex = true;

        return $this;
    }

    public function view(): View
    {
        $filas = $this->query->listadoExport($this->filtros);
        foreach ($filas as $f) {
            $f->nombreempresa = $f->nombreempresa ?? '';
        }

        return view('exports.sala.requisicion_salaindex', [
            'filas' => $filas,
            'logos' => EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($filas),
            'reservarFilaLogoExcel' => true,
        ]);
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10, 'B' => 12, 'C' => 14, 'D' => 22, 'E' => 18,
            'F' => 18, 'G' => 14, 'H' => 14, 'I' => 14, 'J' => 16, 'K' => 40,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A3:K3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF85C1E9');
                $sheet->getStyle('A3:K3')->getFont()->setBold(true);
                $sheet->freezePane('A4');
            },
        ];
    }
}
