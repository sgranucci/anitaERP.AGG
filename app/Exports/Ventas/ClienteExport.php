<?php

namespace App\Exports\Ventas;

use App\Queries\Ventas\ClienteQuery;
use App\Repositories\Ventas\TiposuspensionclienteRepositoryInterface;
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
use App\Models\Ventas\Vendedor;
use Carbon\Carbon;
use App\ApiAnita;

class ClienteExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;
	private $desdeCliente_id, $hastaCliente_id, $estado, $tipoSuspensionCliente_id;
	private $descripcionEstado, $desdeVendedor_id, $hastaVendedor_id;
		
	protected $dates = ['fecha'];
    private $clienteQuery, $tiposuspensionclienteRepository;

    public function __construct(
                                ClienteQuery $clientequery,
								TiposuspensionclienteRepositoryInterface $tiposuspensionclienterepository
								)
    {
        $this->clienteQuery = $clientequery;
		$this->tiposuspensionclienteRepository = $tiposuspensionclienterepository;
    }

	public function view(): View
	{
		// Lee informacion del listado
		$data = $this->clienteQuery->generaDatosRepCliente($this->desdeCliente_id, $this->hastaCliente_id,
			$this->estado, $this->tipoSuspensionCliente_id, $this->desdeVendedor_id, $this->hastaVendedor_id);

		return view('exports.ventas.reportecliente.reportecliente', 
					['clientes' => $data, 
					'desdecliente' => $this->desdeCliente_id,
					'hastacliente' => $this->hastaCliente_id,
					'desdevendedor' => $this->desdeVendedor_id,
					'hastavendedor' => $this->hastaVendedor_id,
					'estado' => $this->descripcionEstado,
					]);
	}

	public function columnFormats(): array
    {
        return [
            
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
            'H' => ['font' => ['bold' => true]],
        ];
    }

	public function columnWidths(): array
    {
        return [
            'A' => 8,
		];
    }

	public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {

                $event->sheet->getDelegate()->freezePane('A4');

            },
        ];
    }

	public function title(): string
    {
        return 'Reporte Maestro de Clientes';
    }

	public function parametros($desdecliente_id, $hastacliente_id,
							$estado, $tiposuspensioncliente_id, $desdevendedor_id,
							$hastavendedor_id)
	{
		$this->desdeCliente_id = $desdecliente_id;
		$this->hastaCliente_id = $hastacliente_id;
		$this->estado = $estado;
		$this->tipoSuspensionCliente_id = $tiposuspensioncliente_id;
		$this->desdeVendedor_id = $desdevendedor_id;
		$this->hastaVendedor_id = $hastavendedor_id;

		$this->descripcionEstado = $estado;
		if ($estado == 'SUSPENDIDOS')
		{
			if ($tiposuspensioncliente_id != 0)
			{
				$tiposuspensioncliente = $this->tiposuspensionclienteRepository->find($tiposuspensioncliente_id);

				if ($tiposuspensioncliente)
					$this->descripcionEstado .= ' SUSPENDIDO POR: '.$tiposuspensioncliente->nombre;
			}
			else	
				$this->descripcionEstado .= ' TODOS LOS ESTADOS DE SUSPENSION';
		}
		
		return $this;
	}
}
