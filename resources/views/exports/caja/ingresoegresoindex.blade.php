<h2> Ingresos y Egresos </h2>
<table> 
	<thead>
	<tr>
		<th class="width20">ID</th>
		<th>Empresa</th>
		<th>Número</th>
		<th>Fecha</th>
		<th>Tipo de transacción</th>
		<th>Concepto</th>
		<th>Detalle</th>
		@if (config('app.empresa') == 'Iguassu Travel')
			<th>Orden de servicio</th>
		@endif
		<th>Monto en $</th>
		<th>Movimientos</th>
	</tr>
  	</thead>
    <tbody>
		@foreach ($caja_movimiento as $data)
		<tr>
			<td>{{$data->id}}</td>
			<td>{{$data->nombreempresa}}</td>
			<td>{{$data->numerotransaccion}}</td>
			<td>{{date("d/m/Y", strtotime($data->fecha ?? ''))}}</td>
			<td>{{$data->nombretipotransaccion_caja}}</td>
			<td>{{$data->nombreconceptogasto ?? ''}}</td>
			<td>{{$data->detalle ?? ''}}</td>
			@if (config('app.empresa') == 'Iguassu Travel')
				<td>{{$data->ordenservicio_id}}</td>
			@endif
			<td>
				@php $totalIngreso = 0; $totalEgreso= 0; @endphp
				@foreach($data->caja_movimiento_cuentacajas as $movimiento)
					@if ($movimiento->moneda_id > 1)
						@php $coef = $movimiento->cotizacion; @endphp
					@else
						@php $coef = 1.; @endphp
					@endif
					@php 
						$totalIngreso += ($movimiento->monto > 0 ? $movimiento->monto * $coef : 0);
						$totalEgreso += ($movimiento->monto < 0 ? abs($movimiento->monto * $coef) : 0);
					@endphp
				@endforeach
				@if ($totalIngreso != 0)
					{{number_format($totalIngreso,2)}}
				@else
					{{number_format($totalEgreso,2)}}
				@endif
			</td>
			<td>
				<ul>
				@foreach($data->caja_movimiento_cuentacajas as $movimiento)
					<li>{{ $movimiento->cuentacajas->nombre }} {{ $movimiento->monto > 0 ? number_format($movimiento->monto,2) : '' }} {{ $movimiento->monto < 0 ? number_format($movimiento->monto,2) : ''}}</li>
				@endforeach
				</ul>
			</td>
		</tr>
		@endforeach
	</tbody>
</table>
