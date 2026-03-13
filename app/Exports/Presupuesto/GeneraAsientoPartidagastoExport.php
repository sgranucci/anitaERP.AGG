<?php

namespace App\Exports\Presupuesto;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Services\Presupuesto\PartidagastoService;
use App\Models\Ventas\Vendedor;
use Carbon\Carbon;
use App\ApiAnita;

class GeneraAsientoPartidagastoExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;
	private $empresa_id, $presupuesto_id, $presupuesto_escenario_id;
	private $origen;
	private $partidagastoService;

	public function __construct(
								PartidagastoService $partidagastoService
								)
	{
		$this->partidagastoService = $partidagastoService;
	}

	public function view(): View
	{
		$datas = $this->partidagastoService->generaAsiento($this->empresa_id, $this->presupuesto_id, $this->presupuesto_escenario_id);

		return view('exports.presupuesto.generaasiento', ['asientos' => $datas]);
	}

	public function columnFormats(): array
    {
		return [
				'A' => NumberFormat::FORMAT_TEXT,
				'H' => NumberFormat::FORMAT_TEXT,
				'I' => NumberFormat::FORMAT_TEXT,
				'J' => NumberFormat::FORMAT_TEXT,
			];		
    }

	public function map($row): array
    {
        return [
        ];
    }

    public function styles(Worksheet $sheet)
    {
		return [
				2   => ['font' => ['bold' => true,
									'color' => array('rgb' => '17202A'),
									'size'  => 12,
									'name'  => 'Arial'
									],
						'fill' => [
									'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
									'color' => array('rgb' => '85C1E9'),
						]
						],
				'G' => ['font' => ['bold' => true]],
			];		
    }

	public function columnWidths(): array
    {
		return [
				'A' => 15,
			];
    }

	public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {

                $event->sheet->getDelegate()->freezePane('A3');

            },
        ];
    }

	public function title(): string
    {
        return 'Reporte de Asientos Generados de Partidas de Gastos';
    }

	public function parametros($empresa_id, $presupuesto_id, $presupuesto_escenario_id)
	{
		$this->empresa_id = $empresa_id;
		$this->presupuesto_id = $presupuesto_id;
		$this->presupuesto_escenario_id = $presupuesto_escenario_id;

		return $this;
	}
}
