<?php

namespace App\Exports\Configuracion;

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

class ConciliaRetencionimpositiva_ArcaExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;
	private $arrayNoEncontroEnArca, $arrayNoEncontroEnSistema;

	public function __construct()
	{
	}

	public function view(): View
	{
		return view('exports.configuracion.concilia_retencionimpositiva_arca', 
						['arrayNoEncontroEnArca' => $this->arrayNoEncontroEnArca,
						 'arrayNoEncontroEnSistema' => $this->arrayNoEncontroEnSistema]);
	}

	public function columnFormats(): array
    {
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
    }

	public function columnWidths(): array
    {
		return [
			'A' => 20
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
        return 'Concilia Retenciones ARCA';
    }

	public function parametros($arrayNoEncontroEnSistema, $arrayNoEncontroEnArca)
	{
		$this->arrayNoEncontroEnSistema = $arrayNoEncontroEnSistema;
		$this->arrayNoEncontroEnArca = $arrayNoEncontroEnArca;

		return $this;
	}
}
