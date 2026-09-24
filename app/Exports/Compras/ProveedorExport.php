<?php

namespace App\Exports\Compras;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\Exportable;
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
use App\Repositories\Compras\ProveedorRepositoryInterface;
use App\Support\Compras\ProveedorListadoBancarioSupport;
use App\Support\Compras\ProveedorListadoColumnas;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;

class ProveedorExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;
	private $proveedorRepository;
	private $filtros;
	/** @var list<string> */
	private $columnas = [];
	/** @var array<string, string> */
	private $etiquetas = [];

	public function __construct(
								ProveedorRepositoryInterface $proveedorRepository
								)
	{
		$this->proveedorRepository = $proveedorRepository;
	}

	public function view(): View
	{
		$columnas = $this->columnas !== []
			? ProveedorListadoColumnas::normalizarVisibles($this->columnas)
			: ProveedorListadoColumnas::defaultsVisibles();
		$columnasExport = array_values(array_filter(
			$columnas,
			static fn ($k) => ($meta = ProveedorListadoColumnas::catalogoActivo()[$k] ?? null) && ! empty($meta['export'])
		));
		if ($columnasExport === []) {
			$columnasExport = array_values(array_filter(
				ProveedorListadoColumnas::defaultsVisibles(),
				static fn ($k) => ! empty(ProveedorListadoColumnas::catalogoActivo()[$k]['export'])
			));
		}

		$datas = $this->proveedorRepository->leeProveedor($this->filtros, false);
		if (ProveedorListadoColumnas::requiereDatosBancarios($columnasExport)) {
			$datas = ProveedorListadoBancarioSupport::expandirConCbuAlias($datas);
		}

		$etiquetas = $this->etiquetas !== []
			? $this->etiquetas
			: ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
				ProveedorListadoColumnas::RECURSO,
				ProveedorListadoColumnas::catalogoActivo()
			);

		return view('exports.compras.listadoproveedor', [
			'proveedores' => $datas,
			'columnasVisibles' => $columnasExport,
			'etiquetasColumnas' => $etiquetas,
		]);
	}

	public function columnFormats(): array
    {
		$formats = ['A' => NumberFormat::FORMAT_TEXT];
		$letras = range('A', 'Z');
		$i = 0;
		foreach ($this->columnasEfectivasExport() as $key) {
			$letra = $letras[$i] ?? null;
			$i++;
			if ($letra === null) {
				continue;
			}
			if (in_array($key, ['id', 'codigo', 'numerodocumento', 'cbu', 'alias_cbu', 'estado'], true)) {
				$formats[$letra] = NumberFormat::FORMAT_TEXT;
			}
		}

		return $formats;
    }

	public function map($row): array
    {
        return [];
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
			];
    }

	public function columnWidths(): array
    {
		$widths = [];
		$letras = range('A', 'Z');
		$i = 0;
		foreach ($this->columnasEfectivasExport() as $key) {
			$letra = $letras[$i] ?? null;
			$i++;
			if ($letra === null) {
				continue;
			}
			$widths[$letra] = match ($key) {
				'id' => 10,
				'cbu' => 26,
				'alias_cbu' => 22,
				'nombre', 'fantasia', 'domicilio' => 28,
				default => 16,
			};
		}

		return $widths;
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
        return 'Reporte de Proveedores';
    }

	/**
	 * @param  array<string, mixed>  $filtros
	 * @param  list<string>|null  $columnas
	 * @param  array<string, string>|null  $etiquetas
	 */
	public function parametros($filtros, ?array $columnas = null, ?array $etiquetas = null)
	{
		$this->filtros = $filtros;
		$this->columnas = is_array($columnas) ? $columnas : [];
		$this->etiquetas = is_array($etiquetas) ? $etiquetas : [];

		return $this;
	}

	/**
	 * @return list<string>
	 */
	private function columnasEfectivasExport(): array
	{
		$columnas = $this->columnas !== []
			? ProveedorListadoColumnas::normalizarVisibles($this->columnas)
			: ProveedorListadoColumnas::defaultsVisibles();

		return array_values(array_filter(
			$columnas,
			static fn ($k) => ($meta = ProveedorListadoColumnas::catalogoActivo()[$k] ?? null) && ! empty($meta['export'])
		));
	}
}
