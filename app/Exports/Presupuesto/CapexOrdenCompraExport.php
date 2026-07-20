<?php

namespace App\Exports\Presupuesto;

use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Export\ExcelFormatoNumero;
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
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CapexOrdenCompraExport implements FromView, WithColumnFormatting, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;

	private const COL_ULTIMA = 'H';

	/** Congela Fecha OC y Nro. OC (A y B): freeze arranca en C. */
	private const COL_FREEZE = 'C';

	protected $ordencompras;

	protected $codigoproyecto;

	private bool $esCsv = false;

	private bool $hayFilaLogos = false;

	private int $filaTituloExcel = 1;

	private int $filaSubtituloExcel = 2;

	private int $filaCabecerasExcel = 3;

	private int $filaPrimeraDatosExcel = 4;

	/** @var list<string> */
	private array $rutasLogosExcel = [];

	public function __construct($ordencompra)
	{
		$this->ordencompras = $ordencompra;
	}

	public function view(): View
	{
		$this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($this->ordencompras);
		$this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
		$this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
		$this->filaSubtituloExcel = $this->filaTituloExcel + 1;
		$this->filaCabecerasExcel = $this->filaSubtituloExcel + 1;
		$this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

		return view('exports.presupuesto.capexordencompra', [
			'ordencompra' => $this->ordencompras,
			'codigoproyecto' => $this->codigoproyecto,
			'esExcel' => true,
			'reservarFilaLogoExcel' => $this->hayFilaLogos,
			'formatoNumero' => $this->formatoNumeroEfectivo(),
		]);
	}

	public function columnFormats(): array
	{
		$pref = ExcelFormatoNumero::preferenciaGlobal();

		return [
			'A' => NumberFormat::FORMAT_TEXT,
			// F = Cotización (4 dec); G = Monto (2 dec): números reales sumables/adaptables.
			'F' => ExcelFormatoNumero::codigoColumna($pref, 4),
			'G' => ExcelFormatoNumero::codigoColumna($pref, 2),
		];
	}

	public function styles(Worksheet $sheet): array
	{
		return [];
	}

	public function columnWidths(): array
	{
		return [
			'A' => 14,
			'B' => 16,
			'C' => 28,
			'D' => 10,
			'E' => 12,
			'F' => 14,
			'G' => 16,
			'H' => 40,
		];
	}

	public function registerEvents(): array
	{
		return [
			AfterSheet::class => function (AfterSheet $event) {
				$sheet = $event->sheet->getDelegate();
				$col = self::COL_ULTIMA;

				if ($this->hayFilaLogos && count($this->rutasLogosExcel) > 0) {
					$sheet->getRowDimension(1)->setRowHeight(54);
					$offsetX = 6;
					foreach ($this->rutasLogosExcel as $ruta) {
						if (! is_string($ruta) || ! is_readable($ruta)) {
							continue;
						}
						$drawing = new Drawing;
						$drawing->setName('Logo');
						$drawing->setDescription('Logo empresa');
						$drawing->setPath($ruta);
						$drawing->setResizeProportional(true);
						$drawing->setHeight(46);
						$drawing->setCoordinates('A1');
						$drawing->setOffsetX($offsetX);
						$drawing->setOffsetY(4);
						$drawing->setWorksheet($sheet);
						$offsetX += 160;
					}
				}

				$sheet->mergeCells('A'.$this->filaTituloExcel.':'.$col.$this->filaTituloExcel);
				$sheet->mergeCells('A'.$this->filaSubtituloExcel.':'.$col.$this->filaSubtituloExcel);
				$sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(28);
				$sheet->getStyle('A'.$this->filaTituloExcel)->getFont()->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');
				$sheet->getStyle('A'.$this->filaSubtituloExcel)->getFont()->setName('Arial')->setSize(10)->setBold(true)->getColor()->setRGB('444444');

				$rangoCab = 'A'.$this->filaCabecerasExcel.':'.$col.$this->filaCabecerasExcel;
				$sheet->getStyle($rangoCab)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('85C1E9');
				$sheet->getStyle($rangoCab)->getFont()->setName('Arial')->setSize(11)->setBold(true)->getColor()->setRGB('17202A');
				$sheet->getStyle($rangoCab)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

				$sheet->freezePane(self::COL_FREEZE.$this->filaPrimeraDatosExcel);
			},
		];
	}

	public function title(): string
	{
		return 'Reporte de Ordenes de Compra';
	}

	public function parametros($codigoproyecto, bool $esCsv = false)
	{
		$this->codigoproyecto = $codigoproyecto;
		$this->esCsv = $esCsv;

		return $this;
	}

	private function formatoNumeroEfectivo(): string
	{
		$global = ExcelFormatoNumero::preferenciaGlobal();

		return $this->esCsv ? ExcelFormatoNumero::paraCsv($global) : $global;
	}
}
