<?php

namespace App\Exports\Uif;

use App\Repositories\Uif\Cliente_Premio_UifRepositoryInterface;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Export\ExcelFormatoNumero;
use App\Support\Uif\ClientePremioUifListadoFiltros;
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
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class Cliente_Premio_UifExport implements FromView, WithColumnFormatting, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;

	private const COL_ULTIMA = 'J';

	/** Congela ID, Origen y Nombre (A–C): freeze arranca en D. */
	private const COL_FREEZE = 'D';

	private $cliente_premio_uifRepository;

	/** @var array<string, mixed>|string|null */
	private $filtros;

	private bool $flDesdeIndex = false;

	private bool $esCsv = false;

	private bool $hayFilaLogos = false;

	private int $filaTituloExcel = 1;

	private int $filaGeneradoExcel = 2;

	private ?int $filaFiltrosExcel = null;

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
		$filtrosArr = is_array($this->filtros) ? $this->filtros : [];
		$subtituloFiltros = $this->flDesdeIndex
			? ClientePremioUifListadoFiltros::subtituloFiltros($filtrosArr)
			: '';

		if (! $this->flDesdeIndex) {
			$this->hayFilaLogos = false;
			$this->calcularFilasEncabezado('');

			return view('exports.uif.cliente_premio_uifindex', [
				'cliente_premio_uifs' => collect(),
				'esExcel' => true,
				'reservarFilaLogoExcel' => false,
				'formatoNumero' => $this->formatoNumeroEfectivo(),
				'subtituloFiltros' => '',
			]);
		}

		$cliente_premio_uifs = $this->cliente_premio_uifRepository->leeCliente_Premio_Uif($this->filtros ?? [], false);

		$this->rutasLogosExcel = EmpresaLogoArchivo::rutasLogosCabeceraDesdeColeccion($cliente_premio_uifs);
		$this->hayFilaLogos = count($this->rutasLogosExcel) > 0;
		$this->calcularFilasEncabezado($subtituloFiltros);

		return view('exports.uif.cliente_premio_uifindex', [
			'cliente_premio_uifs' => $cliente_premio_uifs,
			'esExcel' => true,
			'reservarFilaLogoExcel' => $this->hayFilaLogos,
			'formatoNumero' => $this->formatoNumeroEfectivo(),
			'subtituloFiltros' => $subtituloFiltros,
		]);
	}

	private function calcularFilasEncabezado(string $subtituloFiltros): void
	{
		$offsetLogo = $this->hayFilaLogos ? 1 : 0;
		$this->filaTituloExcel = $offsetLogo + 1;
		$this->filaGeneradoExcel = $this->filaTituloExcel + 1;
		$fila = $this->filaGeneradoExcel;
		if (trim($subtituloFiltros) !== '') {
			$fila++;
			$this->filaFiltrosExcel = $fila;
		} else {
			$this->filaFiltrosExcel = null;
		}
		$this->filaCabecerasExcel = $fila + 1;
		$this->filaPrimeraDatosExcel = $this->filaCabecerasExcel + 1;
	}

	public function columnFormats(): array
	{
		if (! $this->flDesdeIndex) {
			return [];
		}

		// Texto: ID, Origen, Posición, TITO (TITO >15 dígitos no admite float de Excel).
		// G = Monto numérico sumable.
		return [
			'A' => NumberFormat::FORMAT_TEXT,
			'B' => NumberFormat::FORMAT_TEXT,
			'G' => ExcelFormatoNumero::codigoColumna(ExcelFormatoNumero::preferenciaGlobal(), 2),
			'H' => NumberFormat::FORMAT_TEXT,
			'I' => NumberFormat::FORMAT_TEXT,
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
			'B' => 16,
			'C' => 36,
			'D' => 14,
			'E' => 24,
			'F' => 16,
			'G' => 14,
			'H' => 12,
			'I' => 24,
			'J' => 18,
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
				$sheet->mergeCells('A'.$this->filaGeneradoExcel.':'.$col.$this->filaGeneradoExcel);
				$sheet->getRowDimension($this->filaTituloExcel)->setRowHeight(28);
				$sheet->getStyle('A'.$this->filaTituloExcel)->getFont()->setName('Arial')->setSize(16)->setBold(true)->getColor()->setRGB('17202A');
				$sheet->getStyle('A'.$this->filaGeneradoExcel)->getFont()->setName('Arial')->setSize(10)->setBold(true)->getColor()->setRGB('444444');
				if ($this->filaFiltrosExcel !== null) {
					$sheet->mergeCells('A'.$this->filaFiltrosExcel.':'.$col.$this->filaFiltrosExcel);
					$sheet->getRowDimension($this->filaFiltrosExcel)->setRowHeight(36);
					$sheet->getStyle('A'.$this->filaFiltrosExcel)->getFont()->setName('Arial')->setSize(10)->setBold(true)->getColor()->setRGB('444444');
					$sheet->getStyle('A'.$this->filaFiltrosExcel)->getAlignment()->setWrapText(true);
				}

				$rangoCab = 'A'.$this->filaCabecerasExcel.':'.$col.$this->filaCabecerasExcel;
				$sheet->getStyle($rangoCab)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('85C1E9');
				$sheet->getStyle($rangoCab)->getFont()->setName('Arial')->setSize(11)->setBold(true)->getColor()->setRGB('17202A');
				$sheet->getStyle($rangoCab)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

				$ultimaFila = max($this->filaPrimeraDatosExcel, (int) $sheet->getHighestRow());
				if ($ultimaFila >= $this->filaPrimeraDatosExcel) {
					$rangoDatos = 'A'.$this->filaPrimeraDatosExcel.':'.$col.$ultimaFila;
					$sheet->getStyle($rangoDatos)->getFont()->setName('Arial')->setSize(10);
					$sheet->getStyle('G'.$this->filaPrimeraDatosExcel.':G'.$ultimaFila)
						->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

					// ID / Posición / TITO: texto explícito (evita notación científica en TITO).
					foreach (['A', 'H', 'I'] as $colTexto) {
						$sheet->getStyle($colTexto.$this->filaPrimeraDatosExcel.':'.$colTexto.$ultimaFila)
							->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
						for ($row = $this->filaPrimeraDatosExcel; $row <= $ultimaFila; $row++) {
							$cell = $sheet->getCell($colTexto.$row);
							$raw = $cell->getValue();
							if ($raw === null || $raw === '') {
								continue;
							}
							$texto = ltrim((string) $raw, "\t'");
							$cell->setValueExplicit($texto, DataType::TYPE_STRING);
						}
					}
				}

				$sheet->freezePane(self::COL_FREEZE.$this->filaPrimeraDatosExcel);
			},
		];
	}

	public function title(): string
	{
		return 'Premios UIF';
	}

	/**
	 * @param  array<string, mixed>|string|null  $filtros
	 */
	public function parametros($filtros, bool $esCsv = false)
	{
		$this->filtros = $filtros;
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
