<?php

namespace App\Exports\Ventas;

use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ControlAfipPedidosExport implements FromView, ShouldAutoSize, WithEvents
{
    /** @var array<string, mixed> */
    private array $informe;

    /**
     * @param  array<string, mixed>  $informe
     */
    public function parametros(array $informe): self
    {
        $this->informe = $informe;

        return $this;
    }

    public function view(): View
    {
        $filas = collect($this->informe['filas'] ?? []);
        $logos = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($filas);

        return view('exports.ventas.control_afip_pedidosindex', [
            'informe' => $this->informe,
            'filas' => $filas,
            'logosCabecera' => $logos,
            'reservarFilaLogoExcel' => $logos !== [],
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $logos = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion(
                    collect($this->informe['filas'] ?? [])
                );
                $filaTitulo = $logos !== [] ? 2 : 1;
                $filaCabecera = $logos !== [] ? 3 : 2;

                $sheet->mergeCells('A'.$filaTitulo.':J'.$filaTitulo);
                $sheet->getStyle('A'.$filaTitulo)->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A'.$filaCabecera.':J'.$filaCabecera)->applyFromArray([
                    'font' => ['bold' => true, 'name' => 'Arial', 'size' => 11, 'color' => ['rgb' => '17202A']],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '85C1E9'],
                    ],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'wrapText' => true],
                ]);
                $sheet->freezePane('A'.($filaCabecera + 1));
            },
        ];
    }
}
