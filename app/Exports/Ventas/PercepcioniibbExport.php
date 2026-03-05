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
use Carbon\Carbon;
use App\ApiAnita;

class PercepcioniibbExport implements FromView, WithColumnFormatting, WithMapping, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents, WithTitle
{
	use Exportable;
	private $desdefecha, $hastafecha;
	protected $dates = ['fecha'];

	public function view(): View
	{
		$fecha = strtotime($this->desdefecha);
		$desde_fecha = date('Ymd', $fecha);
		$fecha = strtotime($this->hastafecha);
		$hasta_fecha = date('Ym31', $fecha);

        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 
			'tabla' => 'venta, climae, outer retperibr, outer retpercaba',
            'campos' => '
				ven_fecha as fecha,
                ven_tipo as tipo,
                ven_letra as letra,
                ven_sucursal as sucursal,
                ven_nro as numero,
                ven_cliente as cliente,
                clim_nombre as nombre,
                clim_cuit as cuit,
                ven_gravado as gravado,
                (select max(veni_importe) from venibr
                                        where veni_tipo=ven_tipo and
                                        veni_letra=ven_letra and
                                        veni_sucursal=ven_sucursal and
                                        veni_nro=ven_nro and
                                        (veni_provincia=901 or veni_provincia=1)) as monto_caba,
                rpcaba_percepcion as tasa_padron_caba,
                (select max(veni_importe) from venibr
                                        where veni_tipo=ven_tipo and
                                        veni_letra=ven_letra and
                                        veni_sucursal=ven_sucursal and
                                        veni_nro=ven_nro and
                                        (veni_provincia=2 or veni_provincia=902)) as monto_bsas,
                rpibr_percepcion as tasa_padron_bsas
            ' , 
            'whereArmado' => " WHERE ven_cliente=clim_cliente AND
						(ven_tipo[1,1]='F' or ven_tipo[1,2]='NC' or ven_tipo='CIM') and
						ven_fecha>=".$desde_fecha." AND ven_fecha<=".$hasta_fecha." AND
                        rpibr_cuit=clim_cuit[1,2]||clim_cuit[4,11]||clim_cuit[13,13] and
                        rpcaba_cuit=clim_cuit[1,2]||clim_cuit[4,11]||clim_cuit[13,13] and
						rpcaba_desde_fecha>=".$desde_fecha." AND rpcaba_hasta_fecha<=".$hasta_fecha
        );
        $datas = json_decode($apiAnita->apiCall($data));

		return view('exports.ventas.percepcioniibb', ['comprobantes' => $datas]);
	}

	public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'F' => NumberFormat::FORMAT_GENERAL,
            'G' => NumberFormat::FORMAT_GENERAL,
            'H' => NumberFormat::FORMAT_GENERAL,
            'I' => NumberFormat::FORMAT_GENERAL,
            'J' => NumberFormat::FORMAT_GENERAL,
            'K' => NumberFormat::FORMAT_GENERAL,
            'L' => NumberFormat::FORMAT_GENERAL,
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
            'F' => ['font' => ['bold' => true]],
            'G' => ['font' => ['bold' => true]],
            'J' => ['font' => ['bold' => true]],
        ];
    }

	public function columnWidths(): array
    {
        return [
            'A' => 10,
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
        return 'Control de percepciones IIBB';
    }

	public function rangoFecha($desdefecha, $hastafecha)
	{
		$this->desdefecha = $desdefecha;
		$this->hastafecha = $hastafecha;

		return $this;
	}
}
