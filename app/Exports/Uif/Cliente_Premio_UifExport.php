<?php

namespace App\Exports\Uif;

use App\Repositories\Uif\Cliente_Premio_UifRepositoryInterface;
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

class Cliente_Premio_UifExport implements FromView, WithColumnFormatting, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;

	private const COL_ULTIMA = 'I';

	/** Congela ID y Nombre (A y B): freeze arranca en C. */
	private const COL_FREEZE = 'C';

	private $cliente_premio_uifRepository;

	private $busqueda;

	private bool $flDesdeIndex = false;

	private bool $esCsv = false;

	private bool $hayFilaLogos = false;

	private int $filaTituloExcel = 1;

	private int $filaSubtituloExcel = 2;

	private int $filaCabecerasExcel = 3;

	private int $filaPrimeraDatosExcel = 4;

	/** @var list<string> */
	private array $rutasLogosExcel = [];

	public function __construct(Cliente_Premio_UifRepositoryInterface $cliente_premio_uifrepository)
	{
		$this->cliente_premio_uifRepository = $cliente_premio_uifrepository;
	}

	public function view(): View
	{
		if (! $this->flDesdeIndex) {
			return view('exports.uif.cliente_premio_uifindex', [
				'cliente_premio_uifs' => collect(),
				'esExcel' => true,
				'reservarFilaLogoExcel' => false,
				'formatoNumero' => $this->formatoNumeroEfectivo(),
			]);
		}

		$busqueda = is_string($this->busqueda ?? null) ? trim($this->busqueda) : '';
		$cliente_premio_uifs = $this->cliente_premio_uifRepository->leeCliente_Premio_Uif($busqueda, false);

		$this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($cliente_premio_uifs);
		$this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
		$this->filaTituloExcel = $this->hayFilaLogos ? 2 : 1;
		$this->filaSubtituloExcel = $this->filaTituloExcel + 1;
		$this->filaCabecerasExcel = $this->filaSubtituloExcel + 1;
		$this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;

		return view('exports.uif.cliente_premio_uifindex', [
			'cliente_premio_uifs' => $cliente_premio_uifs,
			'esExcel' => true,
			'reservarFilaLogoExcel' => $this->hayFilaLogos,
			'formatoNumero' => $this->formatoNumeroEfectivo(),
		]);
	}

	public function columnFormats(): array
	{
		if (! $this->flDesdeIndex) {
			return [];
		}

		// A ID como texto; F = Monto con máscara neutra (sumable/adaptable).
		return [
			'A' => NumberFormat::FORMAT_TEXT,
			'F' => ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2),
			'H' => NumberFormat::FORMAT_TEXT,
		];
	}

	public function styles(Worksheet $sheet): array
	{
		return [];
	}

	public function columnWidths(): array
	{
		if (! $this->flDesdeIndex) {
			return [];
		}

		return [
			'A' => 10,
			'B' => 36,
			'C' => 22,
			'D' => 22,
			'E' => 20,
			'F' => 14,
			'G' => 12,
			'H' => 18,
			'I' => 22,
		];
	}

	public function registerEvents(): array
	{
		return [
			AfterSheet::class => function (AfterSheet $event) {
				if (! $this->flDesdeIndex) {
					return;
				}

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
		return 'Premios UIF';
	}

	public function parametros($busqueda, bool $esCsv = false)
	{
		$this->busqueda = $busqueda;
		$this->esCsv = $esCsv;
		$this->flDesdeIndex = true;

		return $this;
	}

	private function formatoNumeroEfectivo(): string
	{
		$global = ExcelFormatoNumero::preferenciaGlobal();

		return $this->esCsv ? ExcelFormatoNumero::paraCsv($global) : $global;
	}
}
