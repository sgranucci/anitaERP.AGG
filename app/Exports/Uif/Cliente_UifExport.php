<?php

namespace App\Exports\Uif;

use App\Repositories\Uif\Cliente_UifRepositoryInterface;
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
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class Cliente_UifExport implements FromView, WithColumnFormatting, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;

	private $cliente_uifRepository;

	private $busqueda;

	private $flDesdeIndex = false;

	public function __construct(Cliente_UifRepositoryInterface $cliente_uifrepository)
	{
		$this->cliente_uifRepository = $cliente_uifrepository;
	}

	public function view(): View
	{
		if ($this->flDesdeIndex) {
			$busqueda = $this->busqueda;
			if (is_string($busqueda)) {
				$busqueda = trim($busqueda);
			}
			$cliente_uifs = $this->cliente_uifRepository->leeCliente_Uif($busqueda, false);

			return view('exports.uif.cliente_uifindex', compact('cliente_uifs'));
		}

		$cliente_uifs = collect();

		return view('exports.uif.cliente_uifindex', compact('cliente_uifs'));
	}

	public function columnFormats(): array
	{
		if ($this->flDesdeIndex) {
			return [
				'A' => NumberFormat::FORMAT_TEXT,
				'B' => NumberFormat::FORMAT_TEXT,
				'C' => NumberFormat::FORMAT_TEXT,
				'D' => NumberFormat::FORMAT_TEXT,
				'E' => NumberFormat::FORMAT_TEXT,
				'F' => NumberFormat::FORMAT_TEXT,
				'G' => NumberFormat::FORMAT_TEXT,
				'H' => NumberFormat::FORMAT_TEXT,
				'I' => NumberFormat::FORMAT_TEXT,
				'J' => NumberFormat::FORMAT_TEXT,
			];
		}

		return [];
	}

	public function styles(Worksheet $sheet)
	{
		if ($this->flDesdeIndex) {
			return [
				2 => [
					'font' => [
						'bold' => true,
						'color' => ['rgb' => '17202A'],
						'size' => 12,
						'name' => 'Arial',
					],
					'fill' => [
						'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
						'color' => ['rgb' => '85C1E9'],
					],
				],
				'B' => ['font' => ['bold' => true]],
				'C' => ['font' => ['bold' => true]],
				'D' => ['font' => ['bold' => true]],
				'E' => ['font' => ['bold' => true]],
				'F' => ['font' => ['bold' => true]],
				'G' => ['font' => ['bold' => true]],
				'H' => ['font' => ['bold' => true]],
				'I' => ['font' => ['bold' => true]],
				'J' => ['font' => ['bold' => true]],
			];
		}

		return [];
	}

	public function columnWidths(): array
	{
		if ($this->flDesdeIndex) {
			return [
				'A' => 10,
				'B' => 34,
				'C' => 12,
				'D' => 16,
				'E' => 28,
				'F' => 22,
				'G' => 18,
				'H' => 18,
				'I' => 14,
				'J' => 28,
			];
		}

		return [];
	}

	public function registerEvents(): array
	{
		return [
			AfterSheet::class => function (AfterSheet $event) {
				$event->sheet->getDelegate()->freezePane('A3');
			},
		];
	}

	public function title(): string
	{
		return 'Clientes UIF';
	}

	public function parametros($busqueda)
	{
		$this->busqueda = $busqueda;
		$this->flDesdeIndex = true;

		return $this;
	}
}
