<?php

namespace App\Exports\Ventas;

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

class TotalPedidoExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;
	protected $dates = ['fecha'];

	public function view(): View
	{
		$fecha = strtotime($this->desdefecha);
		$desde_fecha = date('Ymd', $fecha);
		$fecha = strtotime($this->hastafecha);
		$hasta_fecha = date('Ymd', $fecha);

        $apiAnita = new ApiAnita();

        $data = array( 
           	'acc' => 'list', 
			'tabla' => 'pendmae, pendmov, vendedor, combinacion, stkmae, linmae, mventa',
           	'campos' => '
				penv_fecha as fecha,
               	penv_tipo as tipo,
               	penv_letra as letra,
               	penv_sucursal as sucursal,
               	penv_nro as numero,
               	penv_vendedor as vendedor,
               	vend_nombre as nombre,
				penv_articulo as articulo,
				penv_capellada as combinacion,
				comb_desc as desc_combinacion,
				(penv_cantidad) as cantidad,
				penm_estado as estado,
				mvta_desc as marca,
				linm_desc as linea,
				penv_nro_orden as nro_orden,
				(select sum(stkv_cantidad) from stkmov where
					stkv_ref_tipo="OT" and
					stkv_letra=penv_letra and
					stkv_ref_sucursal=0 and
					stkv_ref_nro=penv_nro_orden and
					stkv_cli_pro=penv_cliente and
					stkv_articulo=penv_articulo) as cantfact
           	' , 
           	'whereArmado' => " WHERE penv_tipo='PED' and
						penv_fecha>=".$desde_fecha." AND penv_fecha<=".$hasta_fecha." and
						penv_tipo=penm_tipo and penv_letra=penm_letra and penv_sucursal=penm_sucursal and
						penv_nro=penm_nro and
						penv_vendedor>=".$this->desdevendedor." and
						penv_vendedor<=".$this->hastavendedor." and
						penv_vendedor=vend_codigo and
						penv_articulo=stkm_articulo and
						stkm_linea=linm_linea and
						stkm_o_compra=mvta_mventa and
						penv_articulo=comb_articulo and
						penv_capellada=comb_combinacion ",
           	'orderBy' => " penv_vendedor, penv_fecha, penv_tipo, penv_nro"
        );
        $datas = json_decode($apiAnita->apiCall($data));

		return view('exports.ventas.reportetotalpedido.reportetotalpedido', ['comprobantes' => $datas, 'desdevendedor' => $this->desdevendedor, 'hastavendedor' => $this->hastavendedor, 'desdefecha' => $this->desdefecha, 'hastafecha' => $this->hastafecha, 'nombre_desdevendedor' => $this->nombre_desdevendedor, 'nombre_hastavendedor' => $this->nombre_hastavendedor]);
	}

	public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'H' => NumberFormat::FORMAT_GENERAL,
            'K' => NumberFormat::FORMAT_GENERAL,
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
            'F' => ['font' => ['bold' => true]],
            'G' => ['font' => ['bold' => true]],
            'I' => ['font' => ['bold' => true]],
            'L' => ['font' => ['bold' => true]],
        ];
    }

	public function columnWidths(): array
    {
        return [
            'A' => 10,
            'C' => 15,
            'D' => 15,
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
        return 'Reporte de Pedidos';
    }

	public function rangoFecha($desdefecha, $hastafecha)
	{
		$this->desdefecha = $desdefecha;
		$this->hastafecha = $hastafecha;

		return $this;
	}

	public function asignaRangoVendedor($desdevendedor, $hastavendedor)
	{
		$this->desdevendedor = $desdevendedor;
		$this->hastavendedor = $hastavendedor;

		$this->nombre_desdevendedor = '';
		$this->nombre_hastavendedor = '';

		return $this;
	}

}
