<!DOCTYPE html>
<html>
	<title>Ordenes de Compra Proyecto Capex {{$codigoproyecto}}</title>
	<head>
		<style>
			table {
				font-family: arial, sans-serif;
				border-collapse: collapse;
				width: 100%;
			}
			td, th {
				boder: 1px solid #dddddd;
				text-align: left;
				padding: 8px;
			}
			tr:nth-child(even) {
				background-color: #dddddd;
			}
		</style>
	</head>
	<body>
		<h2>Ordenes de Compra</h2>
		<table class="table table-striped table-bordered table-hover">
			<thead>
				<tr>
                    <th style="width: 10%;">Fecha OC</th>
                    <th style="width: 10%;">Nro. de OC</th>
                    <th>Proveedor</th>
                    <th style="width: 5%;">Mes</th>
                    <th style="width: 8%;">Moneda</th>
                    <th style="text-align: right; width: 10%;">Cotización</th>
                    <th style="text-align: right; width: 10%;">Monto</th>
                    <th>Detalle</th>
					<th class="width40" data-orderable="false"></th>
				</tr>
			</thead>
			<tbody>
				@foreach ($ordencompra as $data)
					<tr>
						<td>{{\Carbon\Carbon::parse($data->fechaordencompra)->format('d-m-Y')}}</td>
						<td>{{$data->movp_tipo}}-{{$data->movp_nro}}</td>
						<td>{{$data->nombreproveedor ?? '' }}</td>
						<td>{{$data->mes ?? ''}}</td>
						<td>
							@switch($data->moneda_id)
								@case(1)
									@php $nombremoneda = 'PESOS'; @endphp
									@break;
								@case(2)
									@php $nombremoneda = 'DOLARES'; @endphp
									@break;						
								@case(3)
									@php $nombremoneda = 'EUROS'; @endphp
									@break;
								@default
									@php $nombremoneda = 'PESOS'; @endphp
									@break;
							@endswitch
							{{$nombremoneda}}
						</td>
						<td style="text-align: right;">{{number_format($data->cotizacion, 4)}}</td>
						<td style="text-align: right;">{{number_format($data->total,2)}}</td>
						<td>{{$data->stkm_desc}}</td>
					</tr>
				@endforeach
			</tbody>
		</table>
	</body>
</html>