<h2> Ordenes de Compra Proyecto: {{$codigoproyecto}}</h2>
<table> 
	<thead>
		<tr>
			<th>Fecha OC</th>
			<th>Nro. de OC</th>
			<th>Proveedor</th>
			<th>Mes</th>
			<th>Moneda</th>
			<th>Cotización</th>
			<th>Monto</th>
			<th>Detalle</th>
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
					@case('1')
						@php $nombremoneda = 'PESOS'; @endphp
						@break;
					@case('2')
						@php $nombremoneda = 'DOLARES'; @endphp
						@break;						
					@case('3')
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
