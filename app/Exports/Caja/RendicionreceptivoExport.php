<?php

namespace App\Exports\Caja;

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
use App\Repositories\Caja\RendicionreceptivoRepositoryInterface;
use Carbon\Carbon;
use App\ApiAnita;

class RendicionreceptivoExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;
	private $desdefecha, $hastafecha;
	private $origen;
	protected $dates = ['fecha'];
	private $RendicionreceptivoRepository;
	private $flDesdeIndex;

	public function __construct(
								RendicionreceptivoRepositoryInterface $rendicionreceptivorepository
								)
	{
		$this->rendicionreceptivoRepository = $rendicionreceptivorepository;
	}

	public function view(): View
	{
		if ($this->flDesdeIndex)
		{
			$rendicionreceptivos = $this->rendicionreceptivoRepository->leeRendicionreceptivo($this->busqueda, false);

			return view('exports.caja.rendicionreceptivoindex', ['rendicionreceptivos' => $rendicionreceptivos]);
		}
		else
		{
		}
	}

	public function columnFormats(): array
    {
		if ($this->flDesdeIndex)
			return [
				'A' => NumberFormat::FORMAT_TEXT,
				'B' => NumberFormat::FORMAT_TEXT,
				'E' => NumberFormat::FORMAT_GENERAL,
			];
    }

	public function map($row): array
    {
        return [
        ];
    }

    public function styles(Worksheet $sheet)
    {
		if ($this->flDesdeIndex)
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
				'B' => ['font' => ['bold' => true]],
				'C' => ['font' => ['bold' => true]],
				'E' => ['font' => ['bold' => true]],
				'F' => ['font' => ['bold' => true]],
			];
		else
			return [
				2   => ['font' => ['bold' => true,
									'color' => array('rgb' => '17202A'),
									'size'  => 12,
									'name'  => 'Arial'
									],
						],
				3   => ['font' => ['bold' => true,
									'color' => array('rgb' => '17202A'),
									'size'  => 12,
									'name'  => 'Arial'
									],
						'fill' => [
									'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
									'color' => array('rgb' => '85C1E9'),
						]
						],
				'B' => ['font' => ['bold' => true]],
				'G' => ['font' => ['bold' => true]],
				'H' => ['font' => ['bold' => true]],
				'J' => ['font' => ['bold' => true]],
				'M' => ['font' => ['bold' => true]],
			];		
    }

	public function columnWidths(): array
    {
		if ($this->flDesdeIndex)
			return [
				'A' => 8,
				'C' => 40,
				'D' => 10,
				'E' => 10,
			];
		else
			return [
				'A' => 10,
				'C' => 15,
				'D' => 10,
				'E' => 15,
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
        return 'Reporte de Rendición de receptivo';
    }

	public function rangoFecha($desdefecha, $hastafecha)
	{
		$this->desdefecha = $desdefecha;
		$this->hastafecha = $hastafecha;
		$this->flDesdeIndex = false;

		return $this;
	}

	public function parametros($busqueda)
	{
		$this->busqueda = $busqueda;
		$this->flDesdeIndex = true;

		return $this;
	}
}
