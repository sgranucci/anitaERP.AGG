<?php

namespace App\Exports\Compras;

use App\Models\Compras\ProgramaPago;
use App\Repositories\Compras\ProgramaPagoRepositoryInterface;
use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProgramaPagoExport implements FromView, ShouldAutoSize, WithEvents, WithStyles, WithTitle
{
    use Exportable;

    public function __construct(
        private ProgramaPagoRepositoryInterface $programaPagoRepository,
    ) {
    }

    /** @var array<string, mixed> */
    private array $filtros = [];

    private string $modo = 'listado';

    private ?ProgramaPago $programa = null;

    /** @var array<string, mixed> */
    private array $matriz = [];

    private int $filaCabecerasExcel = 2;

    private int $filaPrimeraDatosExcel = 3;

    public function parametrosListado(array $filtros): self
    {
        $this->modo = 'listado';
        $this->filtros = $filtros;

        return $this;
    }

    public function parametrosMatriz(ProgramaPago $programa, array $matriz): self
    {
        $this->modo = 'matriz';
        $this->programa = $programa;
        $this->matriz = $matriz;

        return $this;
    }

    public function view(): View
    {
        if ($this->modo === 'matriz') {
            $this->filaCabecerasExcel = 3;
            $this->filaPrimeraDatosExcel = 4;
            $coleccion = collect([(object) [
                'nombreempresa' => $this->programa?->empresas?->nombre ?? '',
            ]]);
            EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($coleccion);

            return view('exports.compras.programa_pago_matriz', [
                'data' => $this->programa,
                'matriz' => $this->matriz,
            ]);
        }

        $datas = $this->programaPagoRepository->leeProgramaPago($this->filtros, false);
        foreach ($datas as $fila) {
            $fila->nombreempresa = $fila->empresas->nombre ?? '';
        }
        $this->filaCabecerasExcel = 2;
        $this->filaPrimeraDatosExcel = 3;

        return view('exports.compras.programa_pagoindex', [
            'datas' => $datas,
        ]);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            $this->filaCabecerasExcel => [
                'font' => ['bold' => true, 'color' => ['rgb' => '17202A']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '85C1E9'],
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        $fila = $this->filaPrimeraDatosExcel;

        return [
            AfterSheet::class => function (AfterSheet $event) use ($fila) {
                $event->sheet->getDelegate()->freezePane('A'.$fila);
            },
        ];
    }

    public function title(): string
    {
        return $this->modo === 'matriz' ? 'Cashflow' : 'Programas';
    }
}
